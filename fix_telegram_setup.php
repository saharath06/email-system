<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "<pre style='background:#111;color:#0f0;padding:20px;font-family:monospace;'>";

$queries = [
    // admins
    "ALTER TABLE admins ADD COLUMN telegram_bot_token VARCHAR(255) NULL",
    "ALTER TABLE admins ADD COLUMN auto_send TINYINT(1) DEFAULT 1",

    // receivers
    "ALTER TABLE receivers MODIFY email VARCHAR(255) NULL",
    "ALTER TABLE receivers ADD COLUMN invite_code VARCHAR(20) NULL",
    "ALTER TABLE receivers ADD COLUMN telegram_chat_id VARCHAR(50) NULL",
    "ALTER TABLE receivers ADD COLUMN telegram_username VARCHAR(100) NULL",
    "ALTER TABLE receivers ADD COLUMN telegram_name VARCHAR(255) NULL",
    "ALTER TABLE receivers ADD COLUMN is_confirmed TINYINT(1) DEFAULT 0",
    "ALTER TABLE receivers ADD COLUMN confirmed_at TIMESTAMP NULL DEFAULT NULL"
];

foreach ($queries as $sql) {
    try {
        $conn->exec($sql);
        echo "OK: $sql\n";
    } catch (Exception $e) {
        echo "SKIP: " . $e->getMessage() . "\n";
    }
}

try {
    $conn->exec("
        UPDATE receivers
        SET invite_code = UPPER(SUBSTRING(MD5(CONCAT(id, RAND())), 1, 8))
        WHERE invite_code IS NULL OR invite_code = ''
    ");
    echo "OK: invite_code updated\n";
} catch (Exception $e) {
    echo "ERR update invite_code: " . $e->getMessage() . "\n";
}

try {
    $conn->exec("ALTER TABLE receivers ADD UNIQUE KEY uniq_invite_code (invite_code)");
    echo "OK: unique key added\n";
} catch (Exception $e) {
    echo "SKIP unique key: " . $e->getMessage() . "\n";
}

echo "\nDONE\n";
echo "</pre>";
?>