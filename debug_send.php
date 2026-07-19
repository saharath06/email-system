<?php
require_once 'config/database.php';
$db   = new Database();
$conn = $db->getConnection();
echo "<pre style='background:#111;color:#0f0;padding:20px;font-family:monospace;'>";
echo "=== فحص الصفقات ===\n";
$stmt = $conn->query("SELECT id, pair, status FROM trades LIMIT 5");
$trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($trades);
echo "\n=== فحص API KEY ===\n";
echo "Key Length: " . strlen(getenv('BREVO_API_KEY')) . "\n";
echo "</pre>";
?>
