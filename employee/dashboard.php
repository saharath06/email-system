<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireEmployee();

$db          = new Database();
$conn        = $db->getConnection();
$employee_id = $_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM trades WHERE employee_id = ?"
);
$stmt->execute([$employee_id]);
$total = $stmt->fetchColumn();

$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM trades 
     WHERE employee_id = ? AND status = 'sent'"
);
$stmt->execute([$employee_id]);
$sent = $stmt->fetchColumn();

$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM trades 
     WHERE employee_id = ? AND status = 'pending'"
);
$stmt->execute([$employee_id]);
$pending = $stmt->fetchColumn();

$stmt = $conn->prepare(
    "SELECT * FROM trades 
     WHERE employee_id = ? 
     ORDER BY created_at DESC LIMIT 10"
);
$stmt->execute([$employee_id]);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" 
          content="width=device-width, initial-scale=1.0">
    <title>لوحة المحلل</title>
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
        <li><a href="dashboard.php" class="active">
            <i class="fas fa-home"></i> الرئيسية</a></li>
        <li><a href="send_trade.php">
            <i class="fas fa-plus-circle"></i> صفقة جديدة</a></li>
        <li><a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> خروج</a></li>
    </ul>
</nav>

<div class="main-content">
    <div class="page-header">
        <h1>مرحباً، <?= htmlspecialchars(
            $_SESSION['full_name']) ?></h1>
        <p>لوحة تحكم المحلل</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="stat-info">
                <h3><?= $total ?></h3>
                <p>إجمالي صفقاتك</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?= $sent ?></h3>
                <p>تم إرسالها</p>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?= $pending ?></h3>
                <p>معلقة</p>
            </div>
        </div>
    </div>

    <div class="text-center mb-20">
        <a href="send_trade.php" class="btn btn-primary" 
           style="font-size:1.1rem;padding:15px 40px;">
            <i class="fas fa-plus-circle"></i> 
            إضافة صفقة جديدة
        </a>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h2><i class="fas fa-clock"></i> آخر صفقاتك</h2>
        </div>
        <?php if (count($recent) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>الزوج</th>
                    <th>النوع</th>
                    <th>الدخول</th>
                    <th>SL</th>
                    <th>TP1</th>
                    <th>الحالة</th>
                    <th>الوقت</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $t): ?>
                <tr>
                    <td>
                        <strong style="color:#00a8ff;">
                            <?= htmlspecialchars(
                                $t['pair']) ?>
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
                            strtotime($t['created_at'])) ?>
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