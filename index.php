<?php
require_once 'includes/auth.php';

if (isAdmin()) {
    header('Location: admin/dashboard.php');
} elseif (isEmployee()) {
    header('Location: employee/dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>