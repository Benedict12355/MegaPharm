<?php
require_once __DIR__ . '/../includes/functions.php';

$cust_page   = $cust_page   ?? 'Home';
$cust_active = $cust_active ?? 'home';
$custUser    = $_SESSION['customer'] ?? null;
$cartCount   = array_sum(array_column($_SESSION['cart'] ?? [], 'qty'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($cust_page) ?> — MegaPharm</title>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/customer.css') ?>">
</head>
<body class="cust-body">

<header class="cust-nav">
  <div class="cust-nav-inner">
    <a class="cust-brand" href="<?= base_url('customer/index.php') ?>">
      <div class="brand-logo">MEGA<br><small>We care</small></div>
      <span class="cust-brand-name">MegaPharm</span>
    </a>
    <nav class="cust-links">
      <a href="<?= base_url('customer/index.php') ?>"     class="<?= $cust_active === 'home' ? 'active' : '' ?>">Home</a>
      <a href="<?= base_url('customer/products.php') ?>"  class="<?= $cust_active === 'products' ? 'active' : '' ?>">Products</a>
      <a href="<?= base_url('customer/products.php?view=categories') ?>" class="<?= $cust_active === 'categories' ? 'active' : '' ?>">Categories</a>
      <a href="<?= base_url('customer/orders.php') ?>"    class="<?= $cust_active === 'orders' ? 'active' : '' ?>">My Orders</a>
      <a href="<?= base_url('customer/cart.php') ?>"      class="<?= $cust_active === 'cart' ? 'active' : '' ?>">Cart<?= $cartCount ? " ($cartCount)" : '' ?></a>
      <a href="<?= base_url('customer/account.php') ?>"   class="<?= $cust_active === 'account' ? 'active' : '' ?>">My Account</a>
    </nav>
    <div class="cust-actions">
      <a href="<?= base_url('customer/notifications.php') ?>" class="cust-bell" title="Notifications">🔔</a>
      <span class="cust-hi">Hi, <?= e($custUser['full_name'] ?? 'Customer') ?></span>
    </div>
  </div>
</header>

<main class="cust-main">
  <?php foreach (get_flashes() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>