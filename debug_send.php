<?php
require_once 'config/database.php';

$db   = new Database();
$conn = $db->getConnection();

echo "<pre style='background:#111;color:#0f0;
      padding:20px;font-family:monospace;'>";

// ===== فحص 1: الصفقات المعلقة =====
echo "=== الصفقات المعلقة ===\n";
$stmt = $conn->query("
    SELECT t.id, t.pair, t.trade_type, 
           t.status, t.created_at,
           a.smtp_password as brevo_key_length,
           a.auto_send
    FROM trades t
    JOIN admins a ON t.admin_id = a.id
    LIMIT 10
");
$trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($trades)) {
    echo "❌ لا توجد صفقات!\n";
} else {
    foreach ($trades as $t) {
        echo "ID: {$t['id']} | ";
        echo "الزوج: {$t['pair']} | ";
        echo "الحالة: {$t['status']} | ";
        echo "auto_send: {$t['auto_send']} | ";
        echo "API Key: " . 
             (empty($t['brevo_key_length']) 
              ? '❌ فارغ' 
              : '✅ موجود') . "\n";
    }
}

// ===== فحص 2: المستقبلين =====
echo "\n=== الإيميلات المستقبلة ===\n";
$stmt = $conn->query("
    SELECT email, is_active FROM receivers
");
$receivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($receivers)) {
    echo "❌ لا توجد إيميلات مستقبلة!\n";
} else {
    foreach ($receivers as $r) {
        echo "📧 {$r['email']} | ";
        echo ($r['is_active'] 
              ? '✅ نشط' : '❌ معطل') . "\n";
    }
}

// ===== فحص 3: BREVO API KEY =====
echo "\n=== فحص BREVO API KEY ===\n";
$api_key = getenv('BREVO_API_KEY');
if (empty($api_key)) {
    echo "❌ BREVO_API_KEY غير موجود في Environment!\n";
    echo "يجب إضافته في Render → Environment\n";
} else {
    echo "✅ BREVO_API_KEY موجود (" . 
         strlen($api_key) . " حرف)\n";
}

// ===== فحص 4: تشغيل الإرسال مباشرة =====
echo "\n=== تشغيل الإرسال الآن ===\n";

$stmt = $conn->prepare("
    SELECT t.*, 
           a.email as admin_email,
           a.auto_send
    FROM trades t
    JOIN admins a ON t.admin_id = a.id
    WHERE t.status IN ('pending', 'approved')
    LIMIT 1
");
$stmt->execute();
$trade = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trade) {
    echo "❌ لا توجد صفقات للإرسال\n";
    echo "السبب: ربما كلها status = sent أو rejected\n";
} else {
    echo "✅ وجدنا صفقة: ID={$trade['id']}\n";
    echo "الحالة الحالية: {$trade['status']}\n";
    echo "auto_send: {$trade['auto_send']}\n";
    
    // جلب المستقبلين
    $stmt2 = $conn->prepare("
        SELECT email FROM receivers 
        WHERE admin_id = ? AND is_active = 1
    ");
    $stmt2->execute([$trade['admin_id']]);
    $receivers = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($receivers)) {
        echo "❌ لا توجد إيميلات مستقبلة!\n";
        echo "يجب إضافة إيميلات من لوحة المدير\n";
    } else {
        echo "المستقبلين: " . 
             implode(', ', $receivers) . "\n";
        
        if (empty($api_key)) {
            echo "❌ لا يمكن الإرسال: API Key مفقود\n";
        } else {
            // محاولة الإرسال
            echo "جاري الإرسال...\n";
            
            $to_list = [];
            foreach ($receivers as $email) {
                $to_list[] = ['email' => $email];
            }
            
            $data = [
                'sender' => [
                    'name'  => 'Trading Signals',
                    'email' => $trade['admin_email']
                ],
                'to'          => $to_list,
                'subject'     => 
                    '🔥 TEST: ' . $trade['trade_type'] . 
                    ' ' . $trade['pair'],
                'htmlContent' => 
                    '<h1>اختبار إرسال</h1>
                     <p>الصفقة: ' . $trade['pair'] . 
                    '</p>'
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 
                    'https://api.brevo.com/v3/smtp/email',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => 
                    json_encode($data),
                CURLOPT_HTTPHEADER     => [
                    'accept: application/json',
                    'api-key: ' . $api_key,
                    'content-type: application/json'
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response  = curl_exec($ch);
            $http_code = curl_getinfo(
                $ch, CURLINFO_HTTP_CODE
            );
            curl_close($ch);
            
            echo "HTTP Code: {$http_code}\n";
            echo "Response: {$response}\n";
            
            if ($http_code === 201) {
                echo "✅ تم الإرسال بنجاح!\n";
                
                // تحديث حالة الصفقة
                $conn->prepare("
                    UPDATE trades 
                    SET status = 'sent',
                        sent_to = ?
                    WHERE id = ?
                ")->execute([
                    implode(', ', $receivers),
                    $trade['id']
                ]);
                echo "✅ تم تحديث حالة الصفقة إلى sent\n";
            } else {
                echo "❌ فشل الإرسال\n";
            }
        }
    }
}

echo "</pre>";
?>