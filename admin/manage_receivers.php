<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$db       = new Database();
$conn     = $db->getConnection();
$admin_id = $_SESSION['user_id'];
$message  = '';
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate') {
        $count = min((int)($_POST['count'] ?? 1), 20);
        $generated = [];

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
            try {
                $stmt = $conn->prepare("INSERT INTO receivers (admin_id, invite_code) VALUES (?, ?)");
                $stmt->execute([$admin_id, $code]);
                $generated[] = $code;
            } catch (Exception $e) {}
        }
        $message = 'تم إنشاء ' . count($generated) . ' رمز دعوة';
    }

    if ($_POST['action'] === 'toggle') {
        $stmt = $conn->prepare("UPDATE receivers SET is_active = NOT is_active WHERE id = ? AND admin_id = ?");
        $stmt->execute([$_POST['receiver_id'], $admin_id]);
        $message = 'تم التحديث';
    }

    if ($_POST['action'] === 'delete') {
        $stmt = $conn->prepare("DELETE FROM receivers WHERE id = ? AND admin_id = ?");
        $stmt->execute([$_POST['receiver_id'], $admin_id]);
        $message = 'تم الحذف';
    }
}

// جلب المستقبلين
$stmt = $conn->prepare("SELECT * FROM receivers WHERE admin_id = ? ORDER BY created_at DESC");
$stmt->execute([$admin_id]);
$receivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$confirmed   = array_filter($receivers, function($r) { return $r['is_confirmed'] == 1; });
$unconfirmed = array_filter($receivers, function($r) { return $r['is_confirmed'] == 0; });
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستقبلين</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
    <div class="logo"><i class="fas fa-chart-line"></i><span>Trading Signals</span></div>
    <ul class="nav-links">
        <li><a href="dashboard.php"><i class="fas fa-home"></i> الرئيسية</a></li>
        <li><a href="manage_employees.php"><i class="fas fa-users"></i> المحللين</a></li>
        <li><a href="manage_receivers.php" class="active"><i class="fas fa-envelope"></i> المستقبلين</a></li>
        <li><a href="view_trades.php"><i class="fas fa-exchange-alt"></i> الصفقات</a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a></li>
        <li><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> خروج</a></li>
    </ul>
</nav>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fab fa-telegram"></i> إدارة المستقبلين</h1>
        <p>إنشاء رموز دعوة وإدارة مستقبلي الصفقات عبر Telegram</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div>
    <?php endif; ?>

    <!-- إنشاء رموز -->
    <div class="form-container wide mb-20">
        <h2 class="form-title"><i class="fas fa-plus-circle"></i> إنشاء رموز دعوة</h2>
        <p style="color:#6a6a8a;margin-bottom:15px;">
            أنشئ رمز دعوة وأعطه للمستلم. سيدخل الرمز في البوت للتأكيد.
        </p>
        <form method="POST" style="display:flex;gap:15px;align-items:flex-end;">
            <input type="hidden" name="action" value="generate">
            <div class="form-group" style="margin-bottom:0;flex:1;">
                <label>عدد الرموز</label>
                <select name="count">
                    <option value="1">1 رمز</option>
                    <option value="3">3 رموز</option>
                    <option value="5">5 رموز</option>
                    <option value="10">10 رموز</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success" style="height:45px;">
                <i class="fas fa-key"></i> إنشاء رموز
            </button>
        </form>
    </div>

    <!-- رموز غير مؤكدة -->
    <div class="table-container mb-20">
        <div class="table-header">
            <h2><i class="fas fa-clock" style="color:#ff8c00;"></i> رموز في انتظار التأكيد (<?= count($unconfirmed) ?>)</h2>
        </div>
        <?php if (count($unconfirmed) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>الرمز</th>
                    <th>الحالة</th>
                    <th>تاريخ الإنشاء</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($unconfirmed as $r): ?>
            <tr>
                <td>
                    <strong style="color:#00a8ff;font-size:1.2rem;letter-spacing:3px;">
                        <?= htmlspecialchars($r['invite_code']) ?>
                    </strong>
                </td>
                <td><span class="badge badge-pending">⏳ ينتظر التأكيد</span></td>
                <td><?= date('Y/m/d H:i', strtotime($r['created_at'])) ?></td>
                <td>
                    <form method="POST" style="display:inline" onsubmit="return confirm('حذف هذا الرمز؟')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="receiver_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-key"></i>
            <h3>لا توجد رموز معلقة</h3>
            <p>أنشئ رمز دعوة جديد من الأعلى</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- مستقبلين مؤكدين -->
    <div class="table-container">
        <div class="table-header">
            <h2><i class="fab fa-telegram" style="color:#00a8ff;"></i> المستقبلين المؤكدين (<?= count($confirmed) ?>)</h2>
        </div>
        <?php if (count($confirmed) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>المعرف</th>
                    <th>Chat ID</th>
                    <th>الرمز</th>
                    <th>الحالة</th>
                    <th>تاريخ التأكيد</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($confirmed as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['telegram_name'] ?? '-') ?></td>
                <td style="color:#00a8ff;">@<?= htmlspecialchars($r['telegram_username'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['telegram_chat_id'] ?? '-') ?></td>
                <td><span style="color:#6a6a8a;"><?= $r['invite_code'] ?></span></td>
                <td>
                    <span class="badge <?= $r['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $r['is_active'] ? '✅ نشط' : '❌ معطل' ?>
                    </span>
                </td>
                <td><?= $r['confirmed_at'] ? date('Y/m/d H:i', strtotime($r['confirmed_at'])) : '-' ?></td>
                <td>
                    <div class="actions">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="receiver_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $r['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                                <i class="fas <?= $r['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                            </button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('حذف؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="receiver_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fab fa-telegram"></i>
            <h3>لا يوجد مستقبلين مؤكدين</h3>
            <p>أنشئ رمز دعوة وأعطه للمستلم ليؤكد عبر البوت</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>