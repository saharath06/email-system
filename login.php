<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

if (isAdmin()) {
    header('Location: admin/dashboard.php');
    exit;
}
if (isEmployee()) {
    header('Location: employee/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $login_type = $_POST['login_type'] ?? 'employee';
    
    if (empty($username) || empty($password)) {
        $error = 'يرجى ملء جميع الحقول';
    } else {
        $db   = new Database();
        $conn = $db->getConnection();
        
        if ($login_type === 'admin') {
            $stmt = $conn->prepare(
                "SELECT * FROM admins WHERE username = ?"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify(
                $password, $user['password']
            )) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_type'] = 'admin';
                $_SESSION['username']  = $user['username'];
                logActivity($conn, 'admin', $user['id'], 
                    'تسجيل دخول');
                header('Location: admin/dashboard.php');
                exit;
            } else {
                $error = 'بيانات الدخول خاطئة';
            }
        } else {
            $stmt = $conn->prepare(
                "SELECT * FROM employees 
                 WHERE username = ? AND is_active = 1"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify(
                $password, $user['password']
            )) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_type'] = 'employee';
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['admin_id']  = $user['admin_id'];
                logActivity($conn, 'employee', $user['id'], 
                    'تسجيل دخول');
                header('Location: employee/dashboard.php');
                exit;
            } else {
                $error = 'بيانات خاطئة أو الحساب معطل';
            }
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
    <title>تسجيل الدخول - Trading Signals</title>
    <?php include 'includes/load_style.php'; ?>
    <link rel="stylesheet" 
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <i class="fas fa-chart-line"></i>
            <h1>Trading Signals</h1>
            <p>نظام إدارة صفقات التداول</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>
                    <i class="fas fa-user-tag"></i> 
                    نوع الحساب
                </label>
                <select name="login_type">
                    <option value="employee">محلل</option>
                    <option value="admin">مدير</option>
                </select>
            </div>
            <div class="form-group">
                <label>
                    <i class="fas fa-user"></i> 
                    اسم المستخدم
                </label>
                <input type="text" name="username" 
                       placeholder="اسم المستخدم" required>
            </div>
            <div class="form-group">
                <label>
                    <i class="fas fa-lock"></i> 
                    كلمة المرور
                </label>
                <input type="password" name="password" 
                       placeholder="كلمة المرور" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                تسجيل الدخول
            </button>
        </form>
    </div>
</div>
</body>
</html>