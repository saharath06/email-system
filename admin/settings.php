<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$db       = new Database();
$conn     = $db->getConnection();
$admin_id = $_SESSION['user_id'];
$message  = '';
$error    = '';

// إضافة عمود auto_send إذا لم يكن موجوداً
try {
    $conn->exec(
        "ALTER TABLE admins 
         ADD COLUMN auto_send TINYINT(1) DEFAULT 1"
    );
} catch (Exception $e) {
    // موجود مسبقاً
}

$stmt = $conn->prepare(
    "SELECT * FROM admins WHERE id = ?"
);
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['update_smtp'])) {
        $stmt = $conn->prepare("
            UPDATE admins SET
                email          = ?,
                smtp_host      = ?,
                smtp_port      = ?,
                smtp_username  = ?,
                smtp_password  = ?,
                auto_send      = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($_POST['email']),
            trim($_POST['smtp_host']),
            (int)$_POST['smtp_port'],
            trim($_POST['smtp_username']),
            trim($_POST['smtp_password']),
            isset($_POST['auto_send']) ? 1 : 0,
            $admin_id
        ]);
        $message = '✅ تم حفظ الإعدادات بنجاح';

        // تحديث البيانات
        $stmt = $conn->prepare(
            "SELECT * FROM admins WHERE id = ?"
        );
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
            $error = 'كلمة المرور الجديدة غير متطابقة';
        } elseif (strlen($new) < 6) {
            $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "UPDATE admins SET password = ? WHERE id = ?"
            );
            $stmt->execute([$hashed, $admin_id]);
            $message = '✅ تم تغيير كلمة المرور بنجاح';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>الإعدادات - Trading Signals</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
    <div class="logo">
        <i class="fas fa-chart-line"></i>
        <span>Trading Signals</span>
    </div>
    <ul class="nav-links">
        <li>
            <a href="dashboard.php">
                <i class="fas fa-home"></i> الرئيسية
            </a>
        </li>
        <li>
            <a href="manage_employees.php">
                <i class="fas fa-users"></i> المحللين
            </a>
        </li>
        <li>
            <a href="manage_receivers.php">
                <i class="fas fa-envelope"></i> المستقبلين
            </a>
        </li>
        <li>
            <a href="view_trades.php">
                <i class="fas fa-exchange-alt"></i> الصفقات
            </a>
        </li>
        <li>
            <a href="settings.php" class="active">
                <i class="fas fa-cog"></i> الإعدادات
            </a>
        </li>
        <li>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> خروج
            </a>
        </li>
    </ul>
</nav>

<div class="main-content">
    <div class="page-header">
        <h1>
            <i class="fas fa-cog"></i> الإعدادات
        </h1>
        <p>إعدادات البريد الإلكتروني وكلمة المرور</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;
                grid-template-columns:1fr 1fr;
                gap:20px;">

        <!-- إعدادات SMTP -->
        <div class="form-container" style="max-width:100%;">
            <h2 class="form-title">
                <i class="fas fa-server"></i>
                إعدادات البريد (SMTP)
            </h2>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                استخدم App Password من Gmail وليس
                كلمة المرور العادية
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>
                        <i class="fas fa-at"></i>
                        البريد الإلكتروني
                    </label>
                    <input type="email"
                           name="email"
                           value="<?= htmlspecialchars(
                               $admin['email'] ?? ''
                           ) ?>"
                           required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-server"></i>
                            SMTP Host
                        </label>
                        <input type="text"
                               name="smtp_host"
                               value="<?= htmlspecialchars(
                                   $admin['smtp_host']
                                   ?? 'smtp.gmail.com'
                               ) ?>"
                               required>
                    </div>
                    <div class="form-group">
                        <label>
                            <i class="fas fa-hashtag"></i>
                            SMTP Port
                        </label>
                        <input type="number"
                               name="smtp_port"
                               value="<?= $admin['smtp_port']
                                   ?? 587 ?>"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-user"></i>
                        SMTP Username (إيميلك)
                    </label>
                    <input type="text"
                           name="smtp_username"
                           value="<?= htmlspecialchars(
                               $admin['smtp_username'] ?? ''
                           ) ?>"
                           placeholder="your@gmail.com">
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-key"></i>
                        SMTP Password (App Password)
                    </label>
                    <input type="password"
                           name="smtp_password"
                           value="<?= htmlspecialchars(
                               $admin['smtp_password'] ?? ''
                           ) ?>"
                           placeholder="xxxx xxxx xxxx xxxx">
                </div>

                <div class="form-group"
                     style="background:rgba(0,150,255,0.05);
                            padding:15px;
                            border-radius:10px;
                            border:1px solid 
                            rgba(0,150,255,0.1);">
                    <label style="display:flex;
                                  align-items:center;
                                  gap:10px;
                                  cursor:pointer;">
                        <input type="checkbox"
                               name="auto_send"
                               style="width:20px;
                                      height:20px;"
                               <?= ($admin['auto_send'] ?? 1)
                                   ? 'checked' : '' ?>>
                        <div>
                            <strong>إرسال تلقائي</strong>
                            <p style="color:#6a6a8a;
                                      font-size:0.85rem;
                                      margin:3px 0 0 0;">
                                إذا كان مفعلاً، تُرسل
                                الصفقة فوراً بدون
                                موافقة المدير
                            </p>
                        </div>
                    </label>
                </div>

                <button type="submit"
                        name="update_smtp"
                        class="btn btn-primary"
                        style="width:100%;
                               margin-top:10px;">
                    <i class="fas fa-save"></i>
                    حفظ الإعدادات
                </button>
            </form>
        </div>

        <!-- تغيير كلمة المرور -->
        <div class="form-container" style="max-width:100%;">
            <h2 class="form-title">
                <i class="fas fa-lock"></i>
                تغيير كلمة المرور
            </h2>

            <form method="POST">
                <div class="form-group">
                    <label>
                        <i class="fas fa-lock"></i>
                        كلمة المرور الحالية
                    </label>
                    <input type="password"
                           name="current_password"
                           placeholder="كلمة المرور الحالية"
                           required>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-key"></i>
                        كلمة المرور الجديدة
                    </label>
                    <input type="password"
                           name="new_password"
                           placeholder="6 أحرف على الأقل"
                           required
                           minlength="6">
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-key"></i>
                        تأكيد كلمة المرور
                    </label>
                    <input type="password"
                           name="confirm_password"
                           placeholder="أعد كتابة كلمة المرور"
                           required
                           minlength="6">
                </div>

                <button type="submit"
                        name="change_password"
                        class="btn btn-success"
                        style="width:100%;
                               margin-top:10px;">
                    <i class="fas fa-save"></i>
                    تغيير كلمة المرور
                </button>
            </form>

            <!-- معلومات الحساب -->
            <div style="margin-top:30px;
                        padding:20px;
                        background:rgba(0,0,0,0.2);
                        border-radius:10px;">
                <h3 style="color:#8a8aaa;
                           font-size:0.9rem;
                           margin-bottom:15px;">
                    <i class="fas fa-info-circle"></i>
                    معلومات الحساب
                </h3>
                <p style="color:#6a6a8a;
                           font-size:0.85rem;
                           margin:5px 0;">
                    اسم المستخدم:
                    <strong style="color:#e0e0e0;">
                        <?= htmlspecialchars(
                            $_SESSION['username']
                        ) ?>
                    </strong>
                </p>
                <p style="color:#6a6a8a;
                           font-size:0.85rem;
                           margin:5px 0;">
                    البريد:
                    <strong style="color:#e0e0e0;">
                        <?= htmlspecialchars(
                            $admin['email'] ?? '-'
                        ) ?>
                    </strong>
                </p>
                <p style="color:#6a6a8a;
                           font-size:0.85rem;
                           margin:5px 0;">
                    حالة الإرسال التلقائي:
                    <strong style="color:<?= 
                        ($admin['auto_send'] ?? 1)
                        ? '#00c896' : '#ff5555' ?>;">
                        <?= ($admin['auto_send'] ?? 1)
                            ? '✅ مفعّل'
                            : '❌ معطّل' ?>
                    </strong>
                </p>
            </div>
        </div>
    </div>

    <!-- كيفية الحصول على App Password -->
    <div class="form-container wide"
         style="margin-top:20px;">
        <h2 class="form-title">
            <i class="fas fa-question-circle"></i>
            كيف تحصل على App Password من Gmail؟
        </h2>
        <div style="display:grid;
                    grid-template-columns:
                    repeat(auto-fit,minmax(200px,1fr));
                    gap:15px;margin-top:10px;">
            <div style="background:rgba(0,150,255,0.05);
                        padding:15px;border-radius:10px;
                        border:1px solid 
                        rgba(0,150,255,0.1);">
                <strong style="color:#00a8ff;">
                    1️⃣ الخطوة الأولى
                </strong>
                <p style="color:#8a8aaa;
                           font-size:0.9rem;
                           margin-top:8px;">
                    اذهب إلى:
                    <br>
                    <a href="https://myaccount.google.com/security"
                       target="_blank"
                       style="color:#00a8ff;">
                        myaccount.google.com/security
                    </a>
                </p>
            </div>
            <div style="background:rgba(0,150,255,0.05);
                        padding:15px;border-radius:10px;
                        border:1px solid 
                        rgba(0,150,255,0.1);">
                <strong style="color:#00a8ff;">
                    2️⃣ الخطوة الثانية
                </strong>
                <p style="color:#8a8aaa;
                           font-size:0.9rem;
                           margin-top:8px;">
                    فعّل التحقق بخطوتين
                    (2-Step Verification)
                </p>
            </div>
            <div style="background:rgba(0,150,255,0.05);
                        padding:15px;border-radius:10px;
                        border:1px solid 
                        rgba(0,150,255,0.1);">
                <strong style="color:#00a8ff;">
                    3️⃣ الخطوة الثالثة
                </strong>
                <p style="color:#8a8aaa;
                           font-size:0.9rem;
                           margin-top:8px;">
                    ابحث عن "App passwords"
                    وأنشئ كلمة مرور جديدة
                </p>
            </div>
            <div style="background:rgba(0,150,255,0.05);
                        padding:15px;border-radius:10px;
                        border:1px solid 
                        rgba(0,150,255,0.1);">
                <strong style="color:#00a8ff;">
                    4️⃣ الخطوة الرابعة
                </strong>
                <p style="color:#8a8aaa;
                           font-size:0.9rem;
                           margin-top:8px;">
                    انسخ الـ 16 حرف والصقها
                    في خانة SMTP Password
                </p>
            </div>
        </div>
    </div>
</div>
</body>
</html>