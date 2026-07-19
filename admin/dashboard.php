<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$db       = new Database();
$conn     = $db->getConnection();
$admin_id = $_SESSION['user_id'];

// إحصائيات
$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM employees WHERE admin_id = ?"
);
$stmt->execute([$admin_id]);
$total_employees = $stmt->fetchColumn();

$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM receivers WHERE admin_id = ?"
);
$stmt->execute([$admin_id]);
$total_receivers = $stmt->fetchColumn();

$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM trades WHERE admin_id = ?"
);
$stmt->execute([$admin_id]);
$total_trades = $stmt->fetchColumn();

$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM trades 
     WHERE admin_id = ? AND DATE(created_at) = CURDATE()"
);
$stmt->execute([$admin_id]);
$today_trades = $stmt->fetchColumn();

$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM trades 
     WHERE admin_id = ? AND status = 'pending'"
);
$stmt->execute([$admin_id]);
$pending_trades = $stmt->fetchColumn();

$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM trades 
     WHERE admin_id = ? AND status = 'sent'"
);
$stmt->execute([$admin_id]);
$sent_trades = $stmt->fetchColumn();

// آخر الصفقات
$stmt = $conn->prepare(
    "SELECT t.*, e.full_name as employee_name 
     FROM trades t 
     JOIN employees e ON t.employee_id = e.id 
     WHERE t.admin_id = ? 
     ORDER BY t.created_at DESC 
     LIMIT 10"
);
$stmt->execute([$admin_id]);
$recent_trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" 
          content="width=device-width, initial-scale=1.0">
    <title>لوحة المدير - Trading Signals</title>
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
            <a href="dashboard.php" class="active">
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
        <h1>
            <i class="fas fa-chart-line"></i>
            مرحباً، <?= htmlspecialchars($_SESSION['username']) ?>
        </h1>
        <p>لوحة تحكم صفقات التداول</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?= $total_employees ?></h3>
                <p>المحللين</p>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?= $pending_trades ?></h3>
                <p>صفقات معلقة</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?= $sent_trades ?></h3>
                <p>صفقات تم إرسالها</p>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-info">
                <h3><?= $today_trades ?></h3>
                <p>صفقات اليوم</p>
            </div>
        </div>
    </div>

    <!-- الصفقات المعلقة -->
    <div class="table-container">
        <div class="table-header">
            <h2>
                <i class="fas fa-clock" 
                   style="color:#ff8c00;"></i> 
                آخر الصفقات
            </h2>
            <a href="view_trades.php" 
               class="btn btn-primary btn-sm">
                <i class="fas fa-eye"></i> عرض الكل
            </a>
        </div>
        <?php if (count($recent_trades) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>المحلل</th>
                    <th>الزوج</th>
                    <th>النوع</th>
                    <th>سعر الدخول</th>
                    <th>وقف الخسارة</th>
                    <th>الهدف</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent_trades as $trade): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars(
                            $trade['employee_name']
                        ) ?>
                    </td>
                    <td>
                        <strong style="color:#00a8ff;">
                            <?= htmlspecialchars(
                                $trade['pair']
                            ) ?>
                        </strong>
                    </td>
                    <td>
                        <span class="badge 
                            <?= $trade['trade_type'] === 'BUY' 
                                ? 'badge-active' 
                                : 'badge-inactive' ?>">
                            <?= $trade['trade_type'] === 'BUY' 
                                ? '🟢 شراء' 
                                : '🔴 بيع' ?>
                        </span>
                    </td>
                    <td><?= $trade['entry_price'] ?></td>
                    <td>
                        <span style="color:#ff5555;">
                            <?= $trade['stop_loss'] ?: '-' ?>
                        </span>
                    </td>
                    <td>
                        <span style="color:#00c896;">
                            <?= $trade['take_profit1'] ?: '-' ?>
                        </span>
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
                            <?= $sc[$trade['status']] ?>">
                            <?= $st[$trade['status']] ?>
                        </span>
                    </td>
                    <td>
                        <?= date('H:i - m/d', 
                            strtotime($trade['created_at'])
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chart-bar"></i>
            <h3>لا توجد صفقات بعد</h3>
            <p>ستظهر الصفقات هنا عندما يقوم المحللون 
               بإرسالها</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>