<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['user_type']);
}

function isAdmin() {
    return isLoggedIn() && 
           $_SESSION['user_type'] === 'admin';
}

function isEmployee() {
    return isLoggedIn() && 
           $_SESSION['user_type'] === 'employee';
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /login.php');
        exit;
    }
}

function requireEmployee() {
    if (!isEmployee()) {
        header('Location: /login.php');
        exit;
    }
}

function logActivity($conn, $type, $id, $action, $details = '') {
    $stmt = $conn->prepare("
        INSERT INTO activity_log 
        (user_type, user_id, action, details) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$type, $id, $action, $details]);
}
?>