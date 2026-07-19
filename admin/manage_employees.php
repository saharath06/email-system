<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$db       = new Database();
$conn     = $db->getConnection();
$admin_id = $_SESSION['user_id'];
$message  = '';
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['action'])) {
    
    if ($_POST['action'] === 'add') {
        $username  = trim($_POST['username']);
        $password  = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        
        if (empty($username) || empty($password) || 
            empty($full_name)) {
            $error = 'يرجى ملء جميع الحقول';
        } else {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) FROM employees 
                 WHERE username = ?"
            );
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'اسم المستخدم موجود مسبقاً';
            } else {
                $hashed = password_hash(
                    $password, PASSWORD_DEFAULT
                );
                $stmt = $conn->prepare(
                    "INSERT INTO employees 
                     (admin_id, username, password, full_name) 
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $admin_id, $username, 
                    $hashed, $full_name
                ]);
                logActivity($conn, 'admin', $admin_id, 
                    'إضافة محلل', $full_name);
                $message = 'تم إضافة المحلل بنجاح';
            }
        }
    }
    
    if ($_POST['action'] === 'toggle') {
        $emp_id = $_POST['employee_id'];
        $stmt = $conn->prepare(
            "UPDATE employees SET is_active = NOT is_active 
             WHERE id = ? AND admin_id = ?"
        );
        $stmt->execute([$emp_id, $admin_id]);
        $message = 'تم تحديث حالة المحلل';
    }
    
    if ($_POST['action'] === 'delete') {
        $emp_id = $_POST['employee_id'];
        $stmt = $conn->prepare(
            "DELETE FROM employees 
             WHERE id = ? AND admin_id = ?"
        );
        $stmt->execute([$emp_id, $admin_id]);
        $message = 'تم حذف المحلل';
    }
    
    if ($_POST['action'] === 'reset_password') {
        $emp_id   = $_POST['employee_id'];
        $new_pass = $_POST['new_password'];
        $hashed   = password_hash(
            $new_pass, PASSWORD_DEFAULT
        );
        $stmt = $conn->prepare(
            "UPDATE employees SET password = ? 
             WHERE id = ? AND admin_id = ?"
        );
        $stmt->execute([$hashed, $emp_id, $admin_id]);
        $message = 'تم تغيير كلمة المرور';
    }
}

$stmt = $conn->prepare("
    SELECT e.*, 
        (SELECT COUNT(*) FROM trades 
         WHERE employee_id = e.id) as trade_count
    FROM employees e 
    WHERE e.admin_id = ? 
    ORDER BY e.created_at DESC
");
$stmt->execute([$admin_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" 
          content="width=device-width, initial-scale=1.0">
    <title>إدارة المحللين</title>
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
            <a href="manage_employees.php" class="active">
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
            <a href="settings.php">
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
        <h1>إدارة المحللين</h1>
        <p>إضافة وإدارة حسابات المحللين</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <?= $message ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> 
            <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="form-container wide mb-20">
        <h2 class="form-title">
            <i class="fas fa-user-plus"></i> 
            إضافة محلل جديد
        </h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label>
                        <i class="fas fa-user"></i> 
                        اسم المستخدم
                    </label>
                    <input type="text" name="username" 
                           placeholder="اسم المستخدم" 
                           required>
                </div>
                <div class="form-group">
                    <label>
                        <i class="fas fa-id-card"></i> 
                        الاسم الكامل
                    </label>
                    <input type="text" name="full_name" 
                           placeholder="الاسم الكامل" 
                           required>
                </div>
            </div>
            <div class="form-group">
                <label>
                    <i class="fas fa-lock"></i> 
                    كلمة المرور
                </label>
                <input type="password" name="password" 
                       placeholder="كلمة المرور" 
                       required minlength="6">
            </div>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-plus"></i> إضافة المحلل
            </button>
        </form>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h2>
                <i class="fas fa-users"></i> 
                قائمة المحللين (<?= count($employees) ?>)
            </h2>
        </div>
        <?php if (count($employees) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>المستخدم</th>
                    <th>الصفقات</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $emp): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars(
                            $emp['full_name']
                        ) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $emp['username']
                        ) ?>
                    </td>
                    <td>
                        <span class="badge badge-sent">
                            <?= $emp['trade_count'] ?> صفقة
                        </span>
                    </td>
                    <td>
                        <span class="badge 
                            <?= $emp['is_active'] 
                                ? 'badge-active' 
                                : 'badge-inactive' ?>">
                            <?= $emp['is_active'] 
                                ? 'نشط' : 'معطل' ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <form method="POST" 
                                  style="display:inline">
                                <input type="hidden" 
                                       name="action" 
                                       value="toggle">
                                <input type="hidden" 
                                       name="employee_id" 
                                       value="<?= $emp['id'] ?>">
                                <button type="submit" 
                                    class="btn btn-sm 
                                    <?= $emp['is_active'] 
                                        ? 'btn-danger' 
                                        : 'btn-success' ?>">
                                    <i class="fas 
                                        <?= $emp['is_active'] 
                                            ? 'fa-ban' 
                                            : 'fa-check' ?>">
                                    </i>
                                </button>
                            </form>
                            <form method="POST" 
                                  style="display:inline"
                                  onsubmit="return confirm(
                                      'حذف هذا المحلل؟')">
                                <input type="hidden" 
                                       name="action" 
                                       value="delete">
                                <input type="hidden" 
                                       name="employee_id" 
                                       value="<?= $emp['id'] ?>">
                                <button type="submit" 
                                        class="btn btn-sm 
                                               btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>لا يوجد محللين</h3>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>