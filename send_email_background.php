<?php
ignore_user_abort(true);
set_time_limit(300);

require_once 'config/database.php';

$db   = new Database();
$conn = $db->getConnection();

// جلب الصفقات المعلقة
$stmt = $conn->prepare("
    SELECT t.*, 
           a.smtp_password as brevo_api_key,
           a.smtp_username as brevo_sender,
           a.email as admin_email,
           a.auto_send,
           e.full_name as employee_name
    FROM trades t
    JOIN admins a ON t.admin_id = a.id
    JOIN employees e ON t.employee_id = e.id
    WHERE (
        t.status = 'approved' 
        OR (t.status = 'pending' AND a.auto_send = 1)
    )
    ORDER BY t.created_at ASC
    LIMIT 10
");
$stmt->execute();
$trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($trades as $trade) {
    // جلب المستقبلين
    $stmt2 = $conn->prepare("
        SELECT email, name FROM receivers 
        WHERE admin_id = ? AND is_active = 1
    ");
    $stmt2->execute([$trade['admin_id']]);
    $receivers = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    if (empty($receivers)) {
        $conn->prepare(
            "UPDATE trades SET status = 'failed' 
             WHERE id = ?"
        )->execute([$trade['id']]);
        continue;
    }

    // بناء الإيميل
    $type_color = $trade['trade_type'] === 'BUY'
                  ? '#00c896' : '#ff5555';
    $type_icon  = $trade['trade_type'] === 'BUY'
                  ? '🟢' : '🔴';
    $type_text  = $trade['trade_type'] === 'BUY'
                  ? 'شراء (BUY)' : 'بيع (SELL)';

    $html = "
    <div style='font-family:Tahoma,Arial;
                direction:rtl;
                max-width:600px;
                margin:0 auto;
                background:#1a1a2e;
                color:#e0e0e0;
                border-radius:12px;
                overflow:hidden;'>

        <div style='background:linear-gradient(
                    135deg,#0a0a1a,#1a1a3e);
                    padding:25px;
                    text-align:center;
                    border-bottom:3px solid {$type_color};'>
            <h1 style='margin:0;color:#fff;
                       font-size:22px;'>
                📊 إشارة تداول جديدة
            </h1>
            <p style='color:#8a8aaa;margin-top:5px;'>
                بواسطة: {$trade['employee_name']}
            </p>
        </div>

        <div style='padding:25px;'>
            <div style='background:#0a0a1a;
                        border-radius:10px;
                        padding:20px;
                        margin-bottom:20px;
                        text-align:center;'>
                <h2 style='color:#00a8ff;
                           font-size:32px;
                           margin:0 0 10px 0;'>
                    {$trade['pair']}
                </h2>
                <span style='background:{$type_color};
                             color:#fff;
                             padding:8px 30px;
                             border-radius:20px;
                             font-size:18px;
                             font-weight:bold;'>
                    {$type_icon} {$type_text}
                </span>
            </div>

            <table style='width:100%;
                          border-collapse:collapse;'>
                <tr style='border-bottom:1px solid 
                           rgba(255,255,255,0.05);'>
                    <td style='padding:15px;
                               color:#8a8aaa;
                               font-size:15px;'>
                        📍 سعر الدخول
                    </td>
                    <td style='padding:15px;
                               color:#ffffff;
                               font-weight:bold;
                               font-size:20px;
                               text-align:left;'>
                        {$trade['entry_price']}
                    </td>
                </tr>
                <tr style='border-bottom:1px solid 
                           rgba(255,255,255,0.05);'>
                    <td style='padding:15px;
                               color:#ff5555;
                               font-size:15px;'>
                        🛡️ وقف الخسارة (SL)
                    </td>
                    <td style='padding:15px;
                               color:#ff5555;
                               font-weight:bold;
                               font-size:20px;
                               text-align:left;'>
                        " . ($trade['stop_loss'] ?: '-') . "
                    </td>
                </tr>
                <tr style='border-bottom:1px solid 
                           rgba(255,255,255,0.05);'>
                    <td style='padding:15px;
                               color:#00c896;
                               font-size:15px;'>
                        🎯 الهدف الأول (TP1)
                    </td>
                    <td style='padding:15px;
                               color:#00c896;
                               font-weight:bold;
                               font-size:20px;
                               text-align:left;'>
                        " . ($trade['take_profit1'] ?: '-') . "
                    </td>
                </tr>
                <tr style='border-bottom:1px solid 
                           rgba(255,255,255,0.05);'>
                    <td style='padding:15px;
                               color:#00c896;
                               font-size:15px;'>
                        🎯 الهدف الثاني (TP2)
                    </td>
                    <td style='padding:15px;
                               color:#00c896;
                               font-weight:bold;
                               font-size:20px;
                               text-align:left;'>
                        " . ($trade['take_profit2'] ?: '-') . "
                    </td>
                </tr>
                <tr>
                    <td style='padding:15px;
                               color:#00c896;
                               font-size:15px;'>
                        🎯 الهدف الثالث (TP3)
                    </td>
                    <td style='padding:15px;
                               color:#00c896;
                               font-weight:bold;
                               font-size:20px;
                               text-align:left;'>
                        " . ($trade['take_profit3'] ?: '-') . "
                    </td>
                </tr>
            </table>";

    if (!empty($trade['notes'])) {
        $html .= "
            <div style='margin-top:15px;
                        padding:15px;
                        background:rgba(0,150,255,0.1);
                        border-radius:8px;
                        border-right:3px solid #00a8ff;'>
                <strong style='color:#00a8ff;'>
                    📝 ملاحظات:
                </strong><br><br>
                " . nl2br(htmlspecialchars(
                    $trade['notes'])) . "
            </div>";
    }

    $html .= "
        </div>

        <div style='background:#0a0a1a;
                    padding:15px;
                    text-align:center;
                    color:#6a6a8a;
                    font-size:12px;'>
            Trading Signals System | " .
            date('Y/m/d H:i') . "
        </div>
    </div>";

    // بناء قائمة المستقبلين
    $to_list = [];
    foreach ($receivers as $r) {
        $to_list[] = [
            'email' => $r['email'],
            'name'  => $r['name'] ?: $r['email']
        ];
    }

    $subject = $type_icon . ' ' .
               $trade['trade_type'] . ' ' .
               $trade['pair'] . ' @ ' .
               $trade['entry_price'];

    // إرسال عبر Brevo API
    $api_key = getenv('BREVO_API_KEY');
    $sender_email = $trade['admin_email'];

    $data = [
        'sender' => [
            'name'  => 'Trading Signals',
            'email' => $sender_email
        ],
        'to'          => $to_list,
        'subject'     => $subject,
        'htmlContent' => $html
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            =>
            'https://api.brevo.com/v3/smtp/email',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . $api_key,
            'content-type: application/json'
        ],
        CURLOPT_TIMEOUT        => 30
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $success = ($http_code === 201);

    // تحديث حالة الصفقة
    $status  = $success ? 'sent' : 'failed';
    $sent_to = implode(', ', array_column(
        $receivers, 'email'
    ));

    $conn->prepare("
        UPDATE trades
        SET status = ?, sent_to = ?
        WHERE id = ?
    ")->execute([$status, $sent_to, $trade['id']]);

    if (!$success) {
        error_log(
            "Brevo Error: " . $response
        );
    }
}

echo "done";
?>