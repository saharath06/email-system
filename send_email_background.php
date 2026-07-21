<?php
ignore_user_abort(true);
set_time_limit(300);

require_once 'config/database.php';

$db   = new Database();
$conn = $db->getConnection();

// جلب Bot Token
$stmt = $conn->query(
    "SELECT telegram_bot_token, auto_send FROM admins LIMIT 1"
);
$admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
$bot_token  = $admin_data['telegram_bot_token'] ?? '';
$auto_send  = $admin_data['auto_send'] ?? 1;

if (empty($bot_token)) {
    echo "no token";
    exit;
}

$stmt = $conn->prepare("
    SELECT t.*, e.full_name as employee_name
    FROM trades t
    JOIN employees e ON t.employee_id = e.id
    WHERE (
        t.status = 'approved' 
        OR (t.status = 'pending' AND ? = 1)
    )
    ORDER BY t.created_at ASC
    LIMIT 10
");
$stmt->execute([$auto_send]);
$trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($trades as $trade) {
    $stmt2 = $conn->prepare("
        SELECT telegram_chat_id FROM receivers 
        WHERE admin_id = ? 
        AND is_confirmed = 1 
        AND is_active = 1
        AND telegram_chat_id IS NOT NULL
    ");
    $stmt2->execute([$trade['admin_id']]);
    $receivers = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    if (empty($receivers)) {
        $conn->prepare(
            "UPDATE trades SET status = 'failed' WHERE id = ?"
        )->execute([$trade['id']]);
        continue;
    }

    $type_emoji = $trade['trade_type'] === 'BUY' ? '🟢' : '🔴';
    $type_text  = $trade['trade_type'] === 'BUY' 
                  ? 'شراء (BUY)' : 'بيع (SELL)';

    $sl  = !empty($trade['stop_loss'])    ? $trade['stop_loss']    : '-';
    $tp1 = !empty($trade['take_profit1']) ? $trade['take_profit1'] : '-';
    $tp2 = !empty($trade['take_profit2']) ? $trade['take_profit2'] : '-';
    $tp3 = !empty($trade['take_profit3']) ? $trade['take_profit3'] : '-';
    $notes = !empty($trade['notes']) ? $trade['notes'] : 'لا توجد';

    $message =
        "📊 *إشارة تداول جديدة*\n\n" .
        "*{$type_emoji} {$trade['pair']}*\n" .
        "*النوع:* {$type_text}\n\n" .
        "📍 *سعر الدخول:* `{$trade['entry_price']}`\n" .
        "🛡️ *وقف الخسارة:* `{$sl}`\n" .
        "🎯 *الهدف 1:* `{$tp1}`\n" .
        "🎯 *الهدف 2:* `{$tp2}`\n" .
        "🎯 *الهدف 3:* `{$tp3}`\n\n" .
        "👤 *المحلل:* {$trade['employee_name']}\n" .
        "⏰ " . date('Y-m-d H:i') . "\n\n" .
        "📝 *ملاحظات:* {$notes}";

    $success   = false;
    $sent_list = [];

    foreach ($receivers as $chat_id) {
        if (empty($chat_id)) continue;

        $url  = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        $data = [
            'chat_id'    => $chat_id,
            'text'       => $message,
            'parse_mode' => 'Markdown'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10
        ]);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $success     = true;
            $sent_list[] = $chat_id;
        }
    }

    $status  = $success ? 'sent' : 'failed';
    $sent_to = implode(', ', $sent_list);

    $conn->prepare("
        UPDATE trades SET status = ?, sent_to = ? WHERE id = ?
    ")->execute([$status, $sent_to, $trade['id']]);
}

echo "done";
?>