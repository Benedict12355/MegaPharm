<?php
require_once __DIR__ . '/../includes/functions.php';

// Require supplier login
if (empty($_SESSION['supplier'])) {
    redirect(base_url('supplier/login.php'));
}

$sup_page   = $sup_page   ?? 'Dashboard';
$sup_active = $sup_active ?? 'dashboard';
$supUser    = $_SESSION['supplier'];

// Fetch supplier record
$supStmt = db()->prepare('SELECT * FROM suppliers WHERE user_id = ?');
$supStmt->execute([$supUser['id']]);
$supInfo = $supStmt->fetch() ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($sup_page) ?> — MegaPharm Supplier</title>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/supplier.css') ?>">
</head>
<body class="app">

<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">MEGA<br><small>We care</small></div>
    <div>
      <div class="brand-name">MegaPharm</div>
      <div class="brand-sub">Management Portal</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item <?= $sup_active === 'dashboard' ? 'active' : '' ?>" href="<?= base_url('supplier/index.php') ?>">
      <span class="nav-icon">🏠</span><span>Dashboard</span>
    </a>
    <a class="nav-item <?= $sup_active === 'purchase' ? 'active' : '' ?>" href="<?= base_url('supplier/purchase-orders.php') ?>">
      <span class="nav-icon">🛒</span><span>Purchase Orders</span>
    </a>
    <a class="nav-item <?= $sup_active === 'products' ? 'active' : '' ?>" href="<?= base_url('supplier/products.php') ?>">
      <span class="nav-icon">📦</span><span>Products</span>
    </a>
    <a class="nav-item <?= $sup_active === 'deliveries' ? 'active' : '' ?>" href="<?= base_url('supplier/deliveries.php') ?>">
      <span class="nav-icon">🚚</span><span>Deliveries</span>
    </a>
    <a class="nav-item <?= $sup_active === 'invoices' ? 'active' : '' ?>" href="<?= base_url('supplier/invoices.php') ?>">
      <span class="nav-icon">🧾</span><span>Invoices</span>
    </a>
    <a class="nav-item <?= $sup_active === 'payments' ? 'active' : '' ?>" href="<?= base_url('supplier/payments.php') ?>">
      <span class="nav-icon">💳</span><span>Payments</span>
    </a>
    <a class="nav-item <?= $sup_active === 'customers' ? 'active' : '' ?>" href="<?= base_url('supplier/customers.php') ?>">
      <span class="nav-icon">👥</span><span>Customers</span>
    </a>
    <a class="nav-item <?= $sup_active === 'notifications' ? 'active' : '' ?>" href="<?= base_url('supplier/notifications.php') ?>">
      <span class="nav-icon">🔔</span><span>Notifications</span>
    </a>
    <a class="nav-item <?= $sup_active === 'messages' ? 'active' : '' ?>" href="<?= base_url('supplier/messages.php') ?>">
      <span class="nav-icon">💬</span><span>Messages</span>
    </a>
    <a class="nav-item <?= $sup_active === 'reports' ? 'active' : '' ?>" href="<?= base_url('supplier/reports.php') ?>">
      <span class="nav-icon">📊</span><span>Reports</span>
    </a>
    <a class="nav-item <?= $sup_active === 'profile' ? 'active' : '' ?>" href="<?= base_url('supplier/profile.php') ?>">
      <span class="nav-icon">👤</span><span>My Profile</span>
    </a>
    <a class="nav-item nav-logout" href="#" onclick="confirmLogout(event)">
      <span class="nav-icon">🚪</span><span>Logout</span>
    </a>
  </nav>
</aside>

<main class="main">
  <header class="topbar">
    <div class="topbar-title">
      <h1><?= e($sup_page) ?></h1>
      <p>Mega Life Pharmacy Management System</p>
    </div>
    <div class="topbar-actions">
      <a href="<?= base_url('supplier/profile.php') ?>" class="icon-btn" title="Settings">⚙️</a>
      <a href="<?= base_url('supplier/notifications.php') ?>" class="icon-btn" title="Notifications">🔔</a>
      <div class="user-chip">
        <div class="user-avatar">S</div>
        <div>
          <div class="user-name">Supplier</div>
          <div class="user-role">System Administrator</div>
        </div>
      </div>
    </div>
  </header>

  <div class="content">
    <?php foreach (get_flashes() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>