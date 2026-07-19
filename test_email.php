<?php
// =============================
// غيّر هذه البيانات فقط
$api_key = getenv('BREVO_API_KEY');
$sender_email = 'saharath06@gmail.com';
$test_to      = 'saharath06@gmail.com';
// =============================

echo "
<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <style>
        body {
            font-family: Tahoma;
            background: #0a0a1a;
            color: #e0e0e0;
            padding: 30px;
        }
        .box {
            background: rgba(20,20,45,0.9);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .ok  { color: #00c896; }
        .err { color: #ff5555; }
        .inf { color: #00a8ff; }
        pre  {
            background: rgba(0,0,0,0.3);
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.85rem;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
<h1 style='color:#00a8ff;'>
    🔍 اختبار Brevo API
</h1>
";

echo "<div class='box'>
<h2>1️⃣ إرسال اختباري</h2>";

$data = [
    'sender' => [
        'name'  => 'Trading Signals',
        'email' => $sender_email
    ],
    'to' => [
        ['email' => $test_to, 'name' => 'Test']
    ],
    'subject'     => '✅ اختبار Brevo API',
    'htmlContent' => "
        <div style='font-family:Tahoma;
                    direction:rtl;
                    padding:20px;
                    background:#1a1a2e;
                    color:#e0e0e0;
                    border-radius:10px;'>
            <h2 style='color:#00c896;'>
                ✅ الإرسال يعمل!
            </h2>
            <p>تم الإرسال عبر Brevo API</p>
            <p>الوقت: " . date('Y/m/d H:i:s') . "</p>
        </div>
    "
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

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p class='inf'>HTTP Code: {$http_code}</p>";
echo "<pre>" . 
     htmlspecialchars($response) . 
     "</pre>";

if ($http_code === 201) {
    echo "<p class='ok' style='font-size:1.3rem;'>
              ✅ تم الإرسال بنجاح!
          </p>
          <p class='ok'>
              تحقق من بريدك: {$test_to}
          </p>";
} else {
    echo "<p class='err' style='font-size:1.3rem;'>
              ❌ فشل الإرسال
          </p>";

    $res = json_decode($response, true);
    if (isset($res['message'])) {
        echo "<p class='err'>
                  السبب: {$res['message']}
              </p>";
    }
}

echo "</div></body></html>";
?>