<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$db       = new Database();
$conn     = $db->getConnection();
$admin_id = $_SESSION['user_id'];
$message  = '';

// معالجة الإجراءات (موافقة / رفض)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['action'])) {
    
    $trade_id = $_POST['trade_id'];
    
    if ($_POST['action'] === 'approve') {
        $stmt = $conn->prepare(
            "UPDATE trades 
             SET status = 'approved', reviewed_at = NOW() 
             WHERE id = ? AND admin_id = ?"
        );
        $stmt->execute([$trade_id, $admin_id]);
        logActivity($conn, 'admin', $admin_id, 
            'موافقة على صفقة', "Trade #$trade_id");
        $message = 'تمت الموافقة على الصفقة ✅';
    }
    
    if ($_POST['action'] === 'reject') {
        $stmt = $conn->prepare(
            "UPDATE trades 
             SET status = 'rejected', reviewed_at = NOW() 
             WHERE id = ? AND admin_id = ?"
        );
        $stmt->execute([$trade_id, $admin_id]);
        $message = 'تم رفض الصفقة ❌';
    }
}

// فلترة
$filter_status   = $_GET['status'] ?? '';
$filter_employee = $_GET['employee'] ?? '';

$where  = "WHERE t.admin_id = ?";
$params = [$admin_id];

if ($filter_status) {
    $where .= " AND t.status = ?";
    $params[] = $filter_status;
}
if ($filter_employee) {
    $where .= " AND t.employee_id = ?";
    $params[] = $filter_employee;
}

$stmt = $conn->prepare("
    SELECT t.*, e.full_name as employee_name 
    FROM trades t 
    JOIN employees e ON t.employee_id = e.id 
    $where
    ORDER BY t.created_at DESC
");
$stmt->execute($params);
$trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// قائمة الموظفين للفلترة
$stmt = $conn->prepare(
    "SELECT id, full_name FROM employees 
     WHERE admin_id = ?"
);
$stmt->execute([$admin_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" 
          content="width=device-width, initial-scale=1.0">
    <title>إدارة الصفقات</title>
    <link rel="stylesheet" href="/assets/style.css?v=1">
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
            <a href="view_trades.php" class="active">
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
        <h1>إدارة الصفقات</h1>
        <p>مراجعة والموافقة على صفقات المحللين</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- فلترة -->
    <div class="form-container wide mb-20">
        <form method="GET" style="display:flex;
              gap:15px;align-items:flex-end;
              flex-wrap:wrap;">
            <div class="form-group" 
                 style="margin-bottom:0;flex:1;
                        min-width:150px;">
                <label>المحلل</label>
                <select name="employee">
                    <option value="">الكل</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" 
                        <?= $filter_employee == $emp['id'] 
                            ? 'selected' : '' ?>>
                        <?= htmlspecialchars(
                            $emp['full_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" 
                 style="margin-bottom:0;flex:1;
                        min-width:150px;">
                <label>الحالة</label>
                <select name="status">
                    <option value="">الكل</option>
                    <option value="pending" 
                        <?= $filter_status === 'pending' 
                            ? 'selected' : '' ?>>
                        معلقة
                    </option>
                    <option value="approved" 
                        <?= $filter_status === 'approved' 
                            ? 'selected' : '' ?>>
                        موافق عليها
                    </option>
                    <option value="sent" 
                        <?= $filter_status === 'sent' 
                            ? 'selected' : '' ?>>
                        مُرسلة
                    </option>
                    <option value="rejected" 
                        <?= $filter_status === 'rejected' 
                            ? 'selected' : '' ?>>
                        مرفوضة
                    </option>
                </select>
            </div>
            <button type="submit" 
                    class="btn btn-primary btn-sm" 
                    style="height:45px;">
                <i class="fas fa-search"></i> بحث
            </button>
            <a href="view_trades.php" 
               class="btn btn-danger btn-sm" 
               style="height:45px;">
                <i class="fas fa-times"></i> مسح
            </a>
        </form>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h2>
                <i class="fas fa-exchange-alt"></i> 
                الصفقات (<?= count($trades) ?>)
            </h2>
        </div>
        <?php if (count($trades) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>المحلل</th>
                    <th>الزوج</th>
                    <th>النوع</th>
                    <th>الدخول</th>
                    <th>وقف الخسارة</th>
                    <th>TP1</th>
                    <th>TP2</th>
                    <th>TP3</th>
                    <th>الحالة</th>
                    <th>الوقت</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($trades as $t): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars(
                            $t['employee_name']
                        ) ?>
                    </td>
                    <td>
                        <strong style="color:#00a8ff;">
                            <?= htmlspecialchars(
                                $t['pair']
                            ) ?>
                        </strong>
                    </td>
                    <td>
                        <span class="badge 
                            <?= $t['trade_type'] === 'BUY' 
                                ? 'badge-active' 
                                : 'badge-inactive' ?>">
                            <?= $t['trade_type'] === 'BUY' 
                                ? '🟢 BUY' 
                                : '🔴 SELL' ?>
                        </span>
                    </td>
                    <td><?= $t['entry_price'] ?></td>
                    <td style="color:#ff5555;">
                        <?= $t['stop_loss'] ?: '-' ?>
                    </td>
                    <td style="color:#00c896;">
                        <?= $t['take_profit1'] ?: '-' ?>
                    </td>
                    <td style="color:#00c896;">
                        <?= $t['take_profit2'] ?: '-' ?>
                    </td>
                    <td style="color:#00c896;">
                        <?= $t['take_profit3'] ?: '-' ?>
                    </td>
                    <td>
                        <?php
                        $sc = [
                            'pending'  => 'badge-pending',
                            'approved' => 'badge-sent',
                            'sent'     => 'badge-sent',
                            'rejected' => 'badge-failed',
                            'failed'   => 'badge-failed'
                        ];
                        $st = [
                            'pending'  => '⏳ معلقة',
                            'approved' => '✅ موافق',
                            'sent'     => '📨 مُرسلة',
                            'rejected' => '❌ مرفوضة',
                            'failed'   => '⚠️ فشل'
                        ];
                        ?>
                        <span class="badge 
                            <?= $sc[$t['status']] ?>">
                            <?= $st[$t['status']] ?>
                        </span>
                    </td>
                    <td>
                        <?= date('H:i - m/d', 
                            strtotime($t['created_at'])
                        ) ?>
                    </td>
                    <td>
                        <?php if ($t['status'] === 'pending'): ?>
                        <div class="actions">
                            <form method="POST" 
                                  style="display:inline">
                                <input type="hidden" 
                                       name="action" 
                                       value="approve">
                                <input type="hidden" 
                                       name="trade_id" 
                                       value="<?= $t['id'] ?>">
                                <button type="submit" 
                                    class="btn btn-sm 
                                           btn-success"
                                    title="موافقة وإرسال">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <form method="POST" 
                                  style="display:inline">
                                <input type="hidden" 
                                       name="action" 
                                       value="reject">
                                <input type="hidden" 
                                       name="trade_id" 
                                       value="<?= $t['id'] ?>">
                                <button type="submit" 
                                    class="btn btn-sm 
                                           btn-danger"
                                    title="رفض">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                            <span style="color:#6a6a8a;">
                                تمت المراجعة
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chart-bar"></i>
            <h3>لا توجد صفقات</h3>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>