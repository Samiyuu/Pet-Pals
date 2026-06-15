<?php
if (!defined('APP_RUN')) exit('No direct access allowed');

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetPal - Rental Management System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <span>🐾</span>
                <div>
                    <h2>PetPal</h2>
                    <p>Rental System</p>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Main</div>
            <a href="../pages/dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span> Dashboard
            </a>

            <div class="nav-section-label">Management</div>
            <a href="../pages/pets.php" class="<?= $current_page == 'pets.php' ? 'active' : '' ?>">
                <span class="nav-icon">🐶</span> Pets
            </a>
            <a href="../pages/customers.php" class="<?= $current_page == 'customers.php' ? 'active' : '' ?>">
                <span class="nav-icon">👤</span> Customers
            </a>
            <a href="../pages/rentals.php" class="<?= $current_page == 'rentals.php' ? 'active' : '' ?>">
                <span class="nav-icon">📝</span> Rentals
            </a>
            <a href="../pages/returns.php" class="<?= $current_page == 'returns.php' ? 'active' : '' ?>">
                <span class="nav-icon">↩️</span> Returns
            </a>
            <a href="../pages/payments.php" class="<?= $current_page == 'payments.php' ? 'active' : '' ?>">
                <span class="nav-icon">💳</span> Payments
            </a>

            <?php if (isAdmin()): ?>
            <div class="nav-section-label">Admin / Analytics</div>
            <a href="../pages/analytics.php" class="<?= $current_page == 'analytics.php' ? 'active' : '' ?>">
                <span class="nav-icon">📈</span> Analytics
            </a>
            <a href="../pages/reports.php" class="<?= $current_page == 'reports.php' ? 'active' : '' ?>">
                <span class="nav-icon">📄</span> Reports
            </a>
            <a href="../pages/recommendations.php" class="<?= $current_page == 'recommendations.php' ? 'active' : '' ?>">
                <span class="nav-icon">💡</span> Recommendations
            </a>
            <a href="../pages/users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>">
                <span class="nav-icon">🔑</span> Users
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php">🚪 Logout</a>
        </div>
    </aside>
    <main class="main-content">

        <div class="topbar">
            <div class="topbar-title">
                <?= ucfirst(str_replace('.php', '', $current_page)) ?>
            </div>
            <div class="topbar-right">
                <div class="user-badge">
                    <span>👤 <?= htmlspecialchars(currentUser()) ?></span>
                    <span class="role-tag"><?= $_SESSION['role'] ?? 'staff' ?></span>
                </div>
            </div>
        </div>
        <div class="page-body">