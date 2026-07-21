<?php
// تسجيل كل شيء
$input = file_get_contents('php://input');
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . "\n" . $input . "\n\n", FILE_APPEND);
require_once 'config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['message'])) {
    exit;
}

$msg       = $input['message'];
$chat_id   = $msg['chat']['id'];
$text      = trim($msg['text'] ?? '');
$username  = $msg['from']['username'] ?? '';
$firstName = $msg['from']['first_name'] ?? '';
$lastName  = $msg['from']['last_name'] ?? '';
$fullName  = trim($firstName . ' ' . $lastName);

// جلب Bot Token من قاعدة البيانات
$db   = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("SELECT telegram_bot_token FROM admins LIMIT 1");
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$bot_token = $admin['telegram_bot_token'] ?? '';

if (empty($bot_token)) {
    exit;
}

// دالة إرسال رسالة
function sendTelegram($token, $chat_id, $text) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
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

// التعامل مع الأوامر
if ($text === '/start') {
    // تحقق هل هو مسجل بالفعل
    $stmt = $conn->prepare("SELECT * FROM receivers WHERE telegram_chat_id = ? AND is_confirmed = 1");
    $stmt->execute([$chat_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        sendTelegram($bot_token, $chat_id,
            "✅ *مرحباً {$fullName}!*\n\n" .
            "أنت مسجل بالفعل وستستقبل الصفقات تلقائياً.\n\n" .
            "📊 انتظر إشارات التداول القادمة!"
        );
    } else {
        sendTelegram($bot_token, $chat_id,
            "👋 *مرحباً {$fullName}!*\n\n" .
            "🔑 أرسل لي *رمز الدعوة* الذي حصلت عليه من المدير.\n\n" .
            "مثال: `ABC12345`"
        );
    }
} elseif (strlen($text) >= 6 && strlen($text) <= 20) {
    // محاولة التحقق من رمز الدعوة
    $code = strtoupper(trim($text));

    $stmt = $conn->prepare("
        SELECT * FROM receivers 
        WHERE invite_code = ? 
        AND is_confirmed = 0
    ");
    $stmt->execute([$code]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invite) {
        // تأكيد الرمز وحفظ Chat ID
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

        sendTelegram($bot_token, $chat_id,
            "🎉 *تم التأكيد بنجاح!*\n\n" .
            "✅ أنت الآن مسجل لاستقبال إشارات التداول.\n\n" .
            "📊 ستصلك الصفقات فور إرسالها من المحللين.\n\n" .
            "بالتوفيق! 🚀"
        );
    } else {
        // تحقق هل الرمز مؤكد بالفعل
        $stmt = $conn->prepare("SELECT * FROM receivers WHERE invite_code = ? AND is_confirmed = 1");
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
                "تأكد من الرمز وأعد المحاولة.\n" .
                "أو تواصل مع المدير للحصول على رمز صحيح."
            );
        }
    }
} else {
    sendTelegram($bot_token, $chat_id,
        "🔑 أرسل لي *رمز الدعوة* للتسجيل.\n\n" .
        "أو اكتب /start للبدء."
    );
}
?>