<?php
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['message'])) {
    exit;
}

require_once 'config/database.php';

$db   = new Database();
$conn = $db->getConnection();

$stmt = $conn->query(
    "SELECT telegram_bot_token FROM admins LIMIT 1"
);
$admin     = $stmt->fetch(PDO::FETCH_ASSOC);
$bot_token = $admin['telegram_bot_token'] ?? '';

if (empty($bot_token)) exit;

$msg       = $input['message'];
$chat_id   = $msg['chat']['id'];
$text      = trim($msg['text'] ?? '');
$username  = $msg['from']['username'] ?? '';
$firstName = $msg['from']['first_name'] ?? '';
$lastName  = $msg['from']['last_name'] ?? '';
$fullName  = trim($firstName . ' ' . $lastName);

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

if ($text === '/start') {
    $stmt = $conn->prepare("
        SELECT * FROM receivers 
        WHERE telegram_chat_id = ? AND is_confirmed = 1
    ");
    $stmt->execute([$chat_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        sendTelegram($bot_token, $chat_id,
            "✅ *مرحباً {$fullName}!*\n\n" .
            "أنت مسجل بالفعل.\n" .
            "ستستقبل الصفقات تلقائياً 📊"
        );
    } else {
        sendTelegram($bot_token, $chat_id,
            "👋 *مرحباً {$fullName}!*\n\n" .
            "🔑 أرسل لي *رمز الدعوة*\n" .
            "الذي حصلت عليه من المدير."
        );
    }

} elseif (strlen($text) >= 6 && strlen($text) <= 20) {
    $code = strtoupper(trim($text));

    $stmt = $conn->prepare("
        SELECT * FROM receivers 
        WHERE invite_code = ? AND is_confirmed = 0
    ");
    $stmt->execute([$code]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invite) {
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
            $chat_id, $username,
            $fullName, $invite['id']
        ]);

        // إرسال الصفقات المعلقة للمستخدم الجديد فوراً
        sendTelegram($bot_token, $chat_id,
            "🎉 *تم التأكيد بنجاح!*\n\n" .
            "✅ أنت مسجل الآن.\n" .
            "ستصلك الصفقات فور إرسالها 🚀"
        );

        // تشغيل الإرسال فوراً
        $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') .
                    '://' . $_SERVER['HTTP_HOST'];
        @file_get_contents(
            $base_url . '/send_email_background.php',
            false,
            stream_context_create(['http' => ['timeout' => 1]])
        );

    } else {
        $stmt = $conn->prepare("
            SELECT * FROM receivers 
            WHERE invite_code = ? AND is_confirmed = 1
        ");
        $stmt->execute([$code]);
        $already = $stmt->fetch();

        if ($already) {
            sendTelegram($bot_token, $chat_id,
                "⚠️ هذا الرمز *مستخدم بالفعل*.\n\n" .
                "تواصل مع المدير للحصول على رمز جديد."
            );
        } else {
            sendTelegram($bot_token, $chat_id,
                "❌ *رمز غير صحيح*\n\n" .
                "تأكد من الرمز وأعد المحاولة."
            );
        }
    }
} else {
    sendTelegram($bot_token, $chat_id,
        "🔑 أرسل رمز الدعوة للتسجيل.\n" .
        "أو اكتب /start"
    );
}
?>