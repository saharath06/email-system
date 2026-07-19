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
        $email = trim($_POST['email']);
        $name  = trim($_POST['name']);
        
        if (empty($email) || 
            !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'بريد إلكتروني غير صحيح';
        } else {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) FROM receivers 
                 WHERE email = ? AND admin_id = ?"
            );
            $stmt->execute([$email, $admin_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'البريد موجود مسبقاً';
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO receivers 
                     (admin_id, email, name) 
                     VALUES (?, ?, ?)"
                );
                $stmt->execute([$admin_id, $email, $name]);
                $message = 'تمت الإضافة بنجاح';
            }
        }
    }
    
    if ($_POST['action'] === 'add_bulk') {
        $emails = array_filter(array_map('trim', 
            explode("\n", $_POST['emails_bulk'])));
        $added   = 0;
        $skipped = 0;
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }
            $stmt = $conn->prepare(
                "SELECT COUNT(*) FROM receivers 
                 WHERE email = ? AND admin_id = ?"
            );
            $stmt->execute([$email, $admin_id]);
            if ($stmt->fetchColumn() > 0) {
                $skipped++;
                continue;
            }
            $stmt = $conn->prepare(
                "INSERT INTO receivers (admin_id, email) 
                 VALUES (?, ?)"
            );
            $stmt->execute([$admin_id, $email]);
            $added++;
        }
        $message = "تم إضافة {$added}. تم تخطي {$skipped}.";
    }
    
    if ($_POST['action'] === 'toggle') {
        $stmt = $conn->prepare(
            "UPDATE receivers 
             SET is_active = NOT is_active 
             WHERE id = ? AND admin_id = ?"
        );
        $stmt->execute([$_POST['receiver_id'], $admin_id]);
        $message = 'تم التحديث';
    }
    
    if ($_POST['action'] === 'delete') {
        $stmt = $conn->prepare(
            "DELETE FROM receivers 
             WHERE id = ? AND admin_id = ?"
        );
        $stmt->execute([$_POST['receiver_id'], $admin_id]);
        $message = 'تم الحذف';
    }
}

$stmt = $conn->prepare(
    "SELECT * FROM receivers 
     WHERE admin_id = ? ORDER BY created_at DESC"
);
$stmt->execute([$admin_id]);
$receivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" 
          content="width=device-width, initial-scale=1.0">
    <title>إدارة المستقبلين</title>
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
        <li><a href="dashboard.php">
            <i class="fas fa-home"></i> الرئيسية</a></li>
        <li><a href="manage_employees.php">
            <i class="fas fa-users"></i> المحللين</a></li>
        <li><a href="manage_receivers.php" class="active">
            <i class="fas fa-envelope"></i> المستقبلين</a></li>
        <li><a href="view_trades.php">
            <i class="fas fa-exchange-alt"></i> الصفقات</a></li>
        <li><a href="settings.php">
            <i class="fas fa-cog"></i> الإعدادات</a></li>
        <li><a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> خروج</a></li>
    </ul>
</nav>

<div class="main-content">
    <div class="page-header">
        <h1>إدارة الإيميلات المستقبلة</h1>
        <p>الإيميلات التي ستستقبل صفقات التداول</p>
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

    <div style="display:grid;
                grid-template-columns:1fr 1fr;
                gap:20px;margin-bottom:30px;">
        <div class="form-container" 
             style="max-width:100%;">
            <h2 class="form-title">
                <i class="fas fa-plus-circle"></i> 
                إضافة بريد
            </h2>
            <form method="POST">
                <input type="hidden" 
                       name="action" value="add">
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" 
                           placeholder="example@email.com" 
                           required>
                </div>
                <div class="form-group">
                    <label>الاسم (اختياري)</label>
                    <input type="text" name="name" 
                           placeholder="اسم المستقبل">
                </div>
                <button type="submit" 
                        class="btn btn-success">
                    <i class="fas fa-plus"></i> إضافة
                </button>
            </form>
        </div>

        <div class="form-container" 
             style="max-width:100%;">
            <h2 class="form-title">
                <i class="fas fa-list"></i> 
                إضافة جماعية
            </h2>
            <form method="POST">
                <input type="hidden" 
                       name="action" value="add_bulk">
                <div class="form-group">
                    <label>كل إيميل في سطر</label>
                    <textarea name="emails_bulk" rows="6" 
                        placeholder="email1@example.com&#10;email2@example.com" 
                        required></textarea>
                </div>
                <button type="submit" 
                        class="btn btn-primary">
                    <i class="fas fa-upload"></i> إضافة الكل
                </button>
            </form>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h2>
                <i class="fas fa-at"></i> 
                المستقبلين (<?= count($receivers) ?>)
            </h2>
        </div>
        <?php if (count($receivers) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>البريد</th>
                    <th>الاسم</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($receivers as $rec): ?>
                <tr>
                    <td><?= htmlspecialchars(
                        $rec['email']) ?></td>
                    <td><?= htmlspecialchars(
                        $rec['name'] ?? '-') ?></td>
                    <td>
                        <span class="badge 
                            <?= $rec['is_active'] 
                                ? 'badge-active' 
                                : 'badge-inactive' ?>">
                            <?= $rec['is_active'] 
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
                                    name="receiver_id" 
                                    value="<?= $rec['id'] ?>">
                                <button type="submit" 
                                    class="btn btn-sm 
                                    <?= $rec['is_active'] 
                                        ? 'btn-danger' 
                                        : 'btn-success' ?>">
                                    <i class="fas 
                                        <?= $rec['is_active'] 
                                            ? 'fa-ban' 
                                            : 'fa-check' ?>">
                                    </i>
                                </button>
                            </form>
                            <form method="POST" 
                                  style="display:inline"
                                  onsubmit="return confirm(
                                      'حذف هذا البريد؟')">
                                <input type="hidden" 
                                    name="action" 
                                    value="delete">
                                <input type="hidden" 
                                    name="receiver_id" 
                                    value="<?= $rec['id'] ?>">
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
            <i class="fas fa-envelope"></i>
            <h3>لا توجد إيميلات</h3>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>