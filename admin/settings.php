<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$db       = new Database();
$conn     = $db->getConnection();
$admin_id = $_SESSION['user_id'];
$message  = '';
$error    = '';

// إضافة الأعمدة إذا لم تكن موجودة
try {
    $conn->exec("ALTER TABLE admins ADD COLUMN telegram_bot_token VARCHAR(255) NULL");
} catch (Exception $e) {}
try {
    $conn->exec("ALTER TABLE admins ADD COLUMN auto_send TINYINT(1) DEFAULT 1");
} catch (Exception $e) {}

$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_telegram'])) {
        $token = trim($_POST['telegram_bot_token']);
        $auto  = isset($_POST['auto_send']) ? 1 : 0;

        $stmt = $conn->prepare("
            UPDATE admins SET 
                telegram_bot_token = ?,
                auto_send = ?
            WHERE id = ?
        ");
        $stmt->execute([$token, $auto, $admin_id]);
        $message = '✅ تم حفظ الإعدادات';

        $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new     = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (!password_verify($current, $admin['password'])) {
            $error = 'كلمة المرور الحالية خاطئة';
        } elseif ($new !== $confirm) {
            $error = 'كلمة المرور غير متطابقة';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $admin_id]);
            $message = '✅ تم تغيير كلمة المرور';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإعدادات - Trading Signals</title>
    <?php include '../includes/load_style.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
    <div class="logo"><i class="fas fa-chart-line"></i><span>Trading Signals</span></div>
    <ul class="nav-links">
        <li><a href="dashboard.php"><i class="fas fa-home"></i> الرئيسية</a></li>
        <li><a href="manage_employees.php"><i class="fas fa-users"></i> المحللين</a></li>
        <li><a href="manage_receivers.php"><i class="fas fa-envelope"></i> المستقبلين</a></li>
        <li><a href="view_trades.php"><i class="fas fa-exchange-alt"></i> الصفقات</a></li>
        <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> الإعدادات</a></li>
        <li><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> خروج</a></li>
    </ul>
</nav>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-cog"></i> الإعدادات</h1>
        <p>إعدادات Telegram Bot</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <!-- إعدادات Telegram -->
        <div class="form-container" style="max-width:100%;">
            <h2 class="form-title"><i class="fab fa-telegram"></i> إعدادات Telegram Bot</h2>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                أنشئ بوت من @BotFather في Telegram وانسخ الـ Token هنا
            </div>

            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Bot Token</label>
                    <input type="text" name="telegram_bot_token"
                           value="<?= htmlspecialchars($admin['telegram_bot_token'] ?? '') ?>"
                           placeholder="123456789:ABCdefGHI..." required>
                </div>

                <div class="form-group" style="background:rgba(0,150,255,0.05);padding:15px;border-radius:10px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <input type="checkbox" name="auto_send" style="width:20px;height:20px;"
                               <?= ($admin['auto_send'] ?? 1) ? 'checked' : '' ?>>
                        <div>
                            <strong>إرسال تلقائي</strong>
                            <p style="color:#6a6a8a;font-size:0.85rem;margin:3px 0 0 0;">
                                الصفقة تُرسل فوراً بدون موافقة المدير
                            </p>
                        </div>
                    </label>
                </div>

                <button type="submit" name="update_telegram" class="btn btn-primary" style="width:100%;margin-top:10px;">
                    <i class="fas fa-save"></i> حفظ الإعدادات
                </button>
            </form>

            <!-- خطوات إنشاء البوت -->
            <div style="margin-top:20px;padding:15px;background:rgba(0,0,0,0.2);border-radius:10px;">
                <h3 style="color:#8a8aaa;font-size:0.9rem;">كيف تنشئ Telegram Bot؟</h3>
                <p style="color:#6a6a8a;font-size:0.85rem;">1. افتح Telegram وابحث عن @BotFather</p>
                <p style="color:#6a6a8a;font-size:0.85rem;">2. أرسل /newbot</p>
                <p style="color:#6a6a8a;font-size:0.85rem;">3. أعطه اسماً</p>
                <p style="color:#6a6a8a;font-size:0.85rem;">4. انسخ الـ Token والصقه هنا</p>
            </div>
        </div>

        <!-- تغيير كلمة المرور -->
        <div class="form-container" style="max-width:100%;">
            <h2 class="form-title"><i class="fas fa-lock"></i> تغيير كلمة المرور</h2>
            <form method="POST">
                <div class="form-group">
                    <label>كلمة المرور الحالية</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>كلمة المرور الجديدة</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" name="change_password" class="btn btn-success" style="width:100%;margin-top:10px;">
                    <i class="fas fa-save"></i> تغيير كلمة المرور
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>