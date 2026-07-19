<?php
require_once 'config/database.php';

if (($_GET['key'] ?? '') !== 'setup2025') {
    die('غير مصرح');
}

$db   = new Database();
$conn = $db->getConnection();

// إنشاء جدول admins
$conn->exec("CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    smtp_host VARCHAR(255) DEFAULT 'smtp.gmail.com',
    smtp_port INT DEFAULT 587,
    smtp_username VARCHAR(255),
    smtp_password VARCHAR(255),
    auto_send TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// إضافة عمود auto_send إذا لم يكن موجوداً
try {
    $conn->exec(
        "ALTER TABLE admins 
         ADD COLUMN auto_send TINYINT(1) DEFAULT 1"
    );
} catch (Exception $e) {
    // العمود موجود مسبقاً
}

// إنشاء جدول employees
$conn->exec("CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) 
        REFERENCES admins(id) ON DELETE CASCADE
)");

// إنشاء جدول receivers
$conn->exec("CREATE TABLE IF NOT EXISTS receivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) 
        REFERENCES admins(id) ON DELETE CASCADE
)");

// إنشاء جدول trades
$conn->exec("CREATE TABLE IF NOT EXISTS trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    admin_id INT NOT NULL,
    pair VARCHAR(20) NOT NULL,
    trade_type ENUM('BUY','SELL') NOT NULL,
    entry_price DECIMAL(15,5) NOT NULL,
    stop_loss DECIMAL(15,5),
    take_profit1 DECIMAL(15,5),
    take_profit2 DECIMAL(15,5),
    take_profit3 DECIMAL(15,5),
    chart_image VARCHAR(500),
    notes TEXT,
    status ENUM(
        'pending',
        'approved',
        'sent',
        'rejected',
        'failed'
    ) DEFAULT 'pending',
    sent_to TEXT,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) 
        REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) 
        REFERENCES admins(id) ON DELETE CASCADE
)");

// إنشاء جدول activity_log
$conn->exec("CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin','employee') NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// إنشاء المدير
$username = 'admin';
$password = 'admin123';
$email    = 'your@gmail.com';
$hashed   = password_hash($password, PASSWORD_DEFAULT);

$conn->exec("DELETE FROM admins WHERE username = 'admin'");

$stmt = $conn->prepare("
    INSERT INTO admins (
        username, password, email,
        smtp_host, smtp_port,
        smtp_username, smtp_password,
        auto_send
    ) VALUES (
        ?, ?, ?,
        'smtp.gmail.com', 587,
        ?, '',
        1
    )
");
$stmt->execute([
    $username,
    $hashed,
    $email,
    $email
]);

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
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .box {
            background: rgba(20,20,45,0.9);
            border: 1px solid rgba(0,150,255,0.2);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
        }
        h2 { color: #00c896; }
        p { color: #a0a0c0; margin: 10px 0; }
        strong { color: #00a8ff; }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: linear-gradient(
                135deg, #00a8ff, #0070cc
            );
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
        }
        .warn {
            color: #ff5555;
            font-size: 0.85rem;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class='box'>
        <h2>✅ تم الإعداد بنجاح!</h2>
        <p>اسم المستخدم: <strong>admin</strong></p>
        <p>كلمة المرور: <strong>admin123</strong></p>
        <a href='login.php'>تسجيل الدخول</a>
        <p class='warn'>
            ⚠️ احذف هذا الملف بعد تسجيل الدخول!
        </p>
    </div>
</body>
</html>
";
?>