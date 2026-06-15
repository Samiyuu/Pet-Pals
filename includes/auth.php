<?php
session_start();
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
}
function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: ../pages/dashboard.php");
        exit();
    }
}
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function currentUser() {
    return $_SESSION['full_name'] ?? 'Unknown';
}
?>