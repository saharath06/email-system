<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireEmployee();

$db          = new Database();
$conn        = $db->getConnection();
$employee_id = $_SESSION['user_id'];
$admin_id    = $_SESSION['admin_id'];
$message     = '';
$error       = '';

// أزواج العملات الشائعة
$pairs = [
    'EUR/USD', 'GBP/USD', 'USD/JPY', 'USD/CHF',
    'AUD/USD', 'NZD/USD', 'USD/CAD', 'EUR/GBP',
    'EUR/JPY', 'GBP/JPY', 'XAU/USD', 'XAG/USD',
    'BTC/USD', 'ETH/USD', 'US30', 'NAS100',
    'SP500', 'UK100', 'GER40', 'OIL'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pair         = trim($_POST['pair']);
    $trade_type   = $_POST['trade_type'];
    $entry_price  = (float)$_POST['entry_price'];
    $stop_loss    = !empty($_POST['stop_loss']) 
                    ? (float)$_POST['stop_loss'] : null;
    $take_profit1 = !empty($_POST['take_profit1']) 
                    ? (float)$_POST['take_profit1'] : null;
    $take_profit2 = !empty($_POST['take_profit2']) 
                    ? (float)$_POST['take_profit2'] : null;
    $take_profit3 = !empty($_POST['take_profit3']) 
                    ? (float)$_POST['take_profit3'] : null;
    $notes        = trim($_POST['notes'] ?? '');
    
    // رفع صورة الشارت
    $chart_image = '';
    if (isset($_FILES['chart_image']) && 
        $_FILES['chart_image']['size'] > 0) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = pathinfo(
            $_FILES['chart_image']['name'], 
            PATHINFO_EXTENSION
        );
        $filename = 'chart_' . time() . '_' . 
                    rand(1000,9999) . '.' . $ext;
        if (move_uploaded_file(
            $_FILES['chart_image']['tmp_name'], 
            $upload_dir . $filename
        )) {
            $chart_image = $filename;
        }
    }
    
    if (empty($pair) || empty($entry_price)) {
        $error = 'يرجى ملء الزوج وسعر الدخول';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO trades (
                employee_id, admin_id, pair, 
                trade_type, entry_price, stop_loss,
                take_profit1, take_profit2, take_profit3,
                chart_image, notes, status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
            )
        ");
        $stmt->execute([
            $employee_id, $admin_id, $pair,
            $trade_type, $entry_price, $stop_loss,
            $take_profit1, $take_profit2, $take_profit3,
            $chart_image, $notes
        ]);
        
        logActivity($conn, 'employee', $employee_id, 
            'إرسال صفقة', "$trade_type $pair @ $entry_price");
        
        // محاولة تشغيل الإرسال في الخلفية
        $base_url = (isset($_SERVER['HTTPS']) ? 
            'https' : 'http') . 
            '://' . $_SERVER['HTTP_HOST'];
        @file_get_contents(
            $base_url . '/send_email_background.php',
            false,
            stream_context_create([
                'http' => ['timeout' => 1]
            ])
        );
        
        $message = 'تم إرسال الصفقة بنجاح ✅';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" 
          content="width=device-width, initial-scale=1.0">
    <title>صفقة جديدة</title>
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
        <li><a href="dashboard.php">
            <i class="fas fa-home"></i> الرئيسية</a></li>
        <li><a href="send_trade.php" class="active">
            <i class="fas fa-plus-circle"></i> صفقة جديدة</a></li>
        <li><a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> خروج</a></li>
    </ul>
</nav>

<div class="main-content">
    <div class="page-header">
        <h1>
            <i class="fas fa-plus-circle"></i> 
            إضافة صفقة جديدة
        </h1>
        <p>أدخل تفاصيل الصفقة</p>
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

    <div class="form-container wide">
        <h2 class="form-title">
            <i class="fas fa-chart-bar"></i> 
            تفاصيل الصفقة
        </h2>
        <form method="POST" 
              enctype="multipart/form-data">
            
            <div class="form-row">
                <div class="form-group">
                    <label>
                        <i class="fas fa-coins"></i> 
                        زوج العملات *
                    </label>
                    <select name="pair" required>
                        <option value="">اختر الزوج</option>
                        <?php foreach ($pairs as $p): ?>
                        <option value="<?= $p ?>">
                            <?= $p ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <i class="fas fa-arrow-up"></i> 
                        نوع الصفقة *
                    </label>
                    <select name="trade_type" required>
                        <option value="BUY">
                            🟢 شراء (BUY)
                        </option>
                        <option value="SELL">
                            🔴 بيع (SELL)
                        </option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>
                        <i class="fas fa-tag"></i> 
                        سعر الدخول *
                    </label>
                    <input type="number" 
                           name="entry_price" 
                           step="0.00001" 
                           placeholder="1.08500" 
                           required>
                </div>
                <div class="form-group">
                    <label>
                        <i class="fas fa-shield-alt" 
                           style="color:#ff5555;"></i> 
                        وقف الخسارة (SL)
                    </label>
                    <input type="number" 
                           name="stop_loss" 
                           step="0.00001" 
                           placeholder="1.08000">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>
                        <i class="fas fa-bullseye" 
                           style="color:#00c896;"></i> 
                        الهدف الأول (TP1)
                    </label>
                    <input type="number" 
                           name="take_profit1" 
                           step="0.00001" 
                           placeholder="1.09000">
                </div>
                <div class="form-group">
                    <label>
                        <i class="fas fa-bullseye" 
                           style="color:#00c896;"></i> 
                        الهدف الثاني (TP2)
                    </label>
                    <input type="number" 
                           name="take_profit2" 
                           step="0.00001" 
                           placeholder="1.09500">
                </div>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-bullseye" 
                       style="color:#00c896;"></i> 
                    الهدف الثالث (TP3)
                </label>
                <input type="number" 
                       name="take_profit3" 
                       step="0.00001" 
                       placeholder="1.10000">
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-image"></i> 
                    صورة الشارت (اختياري)
                </label>
                <input type="file" name="chart_image" 
                       accept="image/*" 
                       style="padding:10px;">
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-sticky-note"></i> 
                    ملاحظات
                </label>
                <textarea name="notes" rows="3" 
                    placeholder="أي ملاحظات إضافية..."
                ></textarea>
            </div>

            <div class="text-center mt-20">
                <button type="submit" 
                        class="btn btn-success" 
                        style="font-size:1.1rem;
                               padding:15px 50px;">
                    <i class="fas fa-paper-plane"></i> 
                    إرسال الصفقة
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>