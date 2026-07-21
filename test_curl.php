<?php
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://api.telegram.org',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false
]);
$response = curl_exec($ch);
$error    = curl_error($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $code . "\n";
echo "Error: " . $error . "\n";
echo "Response: " . substr($response, 0, 200);
?>