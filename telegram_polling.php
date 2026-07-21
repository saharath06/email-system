<?php
require_once 'config/database.php';

$db   = new Database();
$conn = $db->getConnection();

// جلب Bot Token
$stmt = $conn->query("SELECT telegram_bot_token FROM admins LIMIT 1");
$admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
$bot_token  = $admin_data['telegram_bot_token'] ?? '';

if (empty($bot_token)) {
    echo "no token";
    exit;
}

// دالة إرسال رسالة
function sendTelegram($token, $chat_id, $text) {
    $url  = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id'    => $chat_id,
        'text'       => $text,
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
    curl_exec($ch);
    curl_close($ch);
}

// دالة معالجة الرسالة
function processMessage($conn, $token, $msg) {
    $chat_id   = $msg['chat']['id'];
    $text      = trim($msg['text'] ?? '');
    $username  = $msg['from']['username'] ?? '';
    $firstName = $msg['from']['first_name'] ?? '';
    $lastName  = $msg['from']['last_name'] ?? '';
    $fullName  = trim($firstName . ' ' . $lastName);

    if ($text === '/start') {
        // تحقق هل مسجل
        $stmt = $conn->prepare("
            SELECT * FROM receivers 
            WHERE telegram_chat_id = ? AND is_confirmed = 1
        ");
        $stmt->execute([$chat_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            sendTelegram($token, $chat_id,
                "✅ *مرحباً {$fullName}!*\n\n" .
                "أنت مسجل بالفعل.\n" .
                "ستستقبل الصفقات تلقائياً 📊"
            );
        } else {
            sendTelegram($token, $chat_id,
                "👋 *مرحباً {$fullName}!*\n\n" .
                "🔑 أرسل لي *رمز الدعوة*\n" .
                "الذي حصلت عليه من المدير."
            );
        }

    } elseif (strlen($text) >= 6 && strlen($text) <= 20) {
        $code = strtoupper(trim($text));

        // تحقق من الرمز
        $stmt = $conn->prepare("
            SELECT * FROM receivers 
            WHERE invite_code = ? AND is_confirmed = 0
        ");
        $stmt->execute([$code]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($invite) {
            // تأكيد الرمز
            $stmt = $conn->prepare("
                UPDATE receivers SET
                    telegram_chat_id = ?,
                    telegram_username = ?,
                    telegram_name = ?,
                    is_confirmed = 1,
                    is_active = 1,
                    confirmed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $chat_id,
                $username,
                $fullName,
                $invite['id']
            ]);

            sendTelegram($token, $chat_id,
                "🎉 *تم التأكيد بنجاح!*\n\n" .
                "✅ أنت مسجل الآن.\n" .
                "ستصلك الصفقات فور إرسالها 🚀"
            );

        } else {
            // تحقق هل الرمز مستخدم
            $stmt = $conn->prepare("
                SELECT * FROM receivers 
                WHERE invite_code = ? AND is_confirmed = 1
            ");
            $stmt->execute([$code]);
            $already = $stmt->fetch();

            if ($already) {
                sendTelegram($token, $chat_id,
                    "⚠️ هذا الرمز *مستخدم بالفعل*.\n\n" .
                    "تواصل مع المدير للحصول على رمز جديد."
                );
            } else {
                sendTelegram($token, $chat_id,
                    "❌ *رمز غير صحيح*\n\n" .
                    "تأكد من الرمز وأعد المحاولة."
                );
            }
        }
    } else {
        sendTelegram($token, $chat_id,
            "🔑 أرسل رمز الدعوة للتسجيل.\n" .
            "أو اكتب /start"
        );
    }
}

// جلب آخر update_id محفوظ
$stmt = $conn->query("
    SELECT option_value FROM bot_options 
    WHERE option_key = 'last_update_id' 
    LIMIT 1
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$last_update_id = $row ? (int)$row['option_value'] : 0;

// جلب التحديثات من Telegram
$url = "https://api.telegram.org/bot{$bot_token}/getUpdates?offset=" . ($last_update_id + 1) . "&limit=100&timeout=0";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!$data || !$data['ok']) {
    echo "error getting updates";
    exit;
}

$updates = $data['result'];

if (empty($updates)) {
    echo "no new updates";
    exit;
}

foreach ($updates as $update) {
    $update_id = $update['update_id'];

    if (isset($update['message'])) {
        processMessage($conn, $bot_token, $update['message']);
    }

    // حفظ آخر update_id
    $last_update_id = $update_id;
}

// تحديث last_update_id
$stmt = $conn->prepare("
    INSERT INTO bot_options (option_key, option_value)
    VALUES ('last_update_id', ?)
    ON DUPLICATE KEY UPDATE option_value = ?
");
$stmt->execute([$last_update_id, $last_update_id]);

echo "processed " . count($updates) . " updates";
?>