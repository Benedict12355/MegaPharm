<?php
require_once __DIR__ . '/functions.php';
require_login();
$u = current_user();
$page_title = $page_title ?? 'Dashboard';
$page_sub   = $page_sub ?? 'Mega Life Pharmacy Management System';
$active     = $active ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> — MegaPharm</title>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="main">
    <header class="topbar">
      <div class="topbar-title">
        <h1><?= e($page_title) ?></h1>
        <p><?= e($page_sub) ?></p>
      </div>
      <div class="topbar-actions">
        <a href="<?= base_url('admin/settings.php') ?>" class="icon-btn" title="Settings">⚙️</a>
        <a href="<?= base_url('admin/notifications.php') ?>" class="icon-btn" title="Notifications">🔔<span class="badge-dot"></span></a>
        <div class="user-chip">
          <div class="user-avatar"><?= strtoupper(substr($u['full_name'], 0, 1)) ?></div>
          <div>
            <div class="user-name"><?= e($u['full_name']) ?></div>
            <div class="user-role"><?= e(ucfirst($u['role'])) ?></div>
          </div>
        </div>
      </div>
    </header>
    <div class="content">
      <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>