<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) redirect(base_url('admin/dashboard.php'));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if ($u === '' || $p === '') {
        $error = 'Please enter username and password.';
    } elseif (attempt_login($u, $p)) {
        redirect(base_url('admin/dashboard.php'));
    } else {
        $error = 'Invalid credentials or inactive account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MegaPharm — Sign In</title>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.css">
<style>
body.login-body{margin:0;font-family:'Segoe UI',Arial,sans-serif;background:#fff;min-height:100vh}
.login-wrap{display:grid;grid-template-columns:1fr 1fr;min-height:100vh}
.login-left{background:linear-gradient(135deg,#1e40af 0%,#2563eb 60%,#3b82f6 100%);color:#fff;padding:60px 70px;position:relative;overflow:hidden}
.login-left::after{content:"";position:absolute;right:-100px;bottom:-100px;width:400px;height:400px;border-radius:50%;background:rgba(255,255,255,.06)}
.brand{display:flex;align-items:center;gap:14px;margin-bottom:60px}
.brand-logo{background:#0b1e3b;color:#fff;padding:12px 14px;border-radius:8px;font-weight:700;font-size:11px;line-height:1.1;text-align:center;letter-spacing:.5px}
.brand-logo small{font-weight:400;color:#7dd3fc;font-size:9px}
.brand h1{margin:0;font-size:26px}
.brand p{margin:2px 0 0;font-size:13px;color:#bfdbfe}
.login-left h2{font-size:36px;margin:0 0 8px}
.login-left .lead{color:#dbeafe;font-size:15px;margin-bottom:40px}
.feat{display:flex;gap:14px;margin-bottom:26px;max-width:420px}
.feat-icon{width:52px;height:52px;border-radius:12px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:22px}
.feat h3{margin:0 0 4px;font-size:16px}
.feat p{margin:0;font-size:13px;color:#cbd5e1;line-height:1.5}
.login-right{display:flex;align-items:center;justify-content:center;padding:40px}
.login-card{width:100%;max-width:440px;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(15,23,42,.12);padding:40px 40px 34px}
.avatar{width:88px;height:88px;border-radius:50%;background:#e0edff;color:#2563eb;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:40px}
.login-card h2{text-align:center;margin:0 0 6px;font-size:22px;color:#0f172a}
.login-card .sub{text-align:center;color:#64748b;font-size:13px;margin:0 0 26px}
.field{margin-bottom:18px}
.field label{display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px}
.input-wrap{position:relative}
.input-wrap input{width:100%;padding:12px 40px 12px 40px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;transition:.15s;box-sizing:border-box}
.input-wrap input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.input-wrap .icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:16px}
.input-wrap .eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#94a3b8;background:none;border:0;font-size:16px}
.row-between{display:flex;justify-content:space-between;align-items:center;margin:12px 0 22px;font-size:13px}
.row-between label{display:flex;align-items:center;gap:6px;color:#475569}
.row-between a{color:#2563eb;text-decoration:none}
.btn-primary{width:100%;background:#2563eb;color:#fff;border:0;padding:13px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:.15s}
.btn-primary:hover{background:#1d4ed8}
.divider{display:flex;align-items:center;gap:12px;color:#94a3b8;font-size:12px;margin:22px 0}
.divider::before,.divider::after{content:"";flex:1;height:1px;background:#e2e8f0}
.btn-outline{display:block;text-align:center;width:100%;padding:12px;border:1px solid #bfdbfe;color:#2563eb;background:#fff;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;box-sizing:border-box}
.btn-outline:hover{background:#eff6ff}
.alert-error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.login-footer{text-align:center;padding:14px;color:#94a3b8;font-size:12px}
@media(max-width:900px){.login-wrap{grid-template-columns:1fr}.login-left{display:none}}
</style>
</head>
<body class="login-body">
<div class="login-wrap">
  <div class="login-left">
    <div class="brand">
      <div class="brand-logo">MEGA<br><small>We care</small></div>
      <div><h1>MegaPharm</h1><p>Mega Life Pharmacy Management System</p></div>
    </div>
    <h2>Welcome Back</h2>
    <p class="lead">Manage your pharmacy inventory, orders, sales, and suppliers in one system.</p>
    <div class="feat"><div class="feat-icon">📋</div><div><h3>Real-time Inventory Tracking</h3><p>Monitor stock levels and movements in real time to avoid shortage and overstocking.</p></div></div>
    <div class="feat"><div class="feat-icon">🛡️</div><div><h3>Smart Restock Management</h3><p>Set minimum stock alerts and manage restock requests efficiently.</p></div></div>
    <div class="feat"><div class="feat-icon">📊</div><div><h3>Reports &amp; Insights</h3><p>Get detailed reports and insights to help you make better decisions.</p></div></div>
  </div>
  <div class="login-right">
    <div class="login-card">
      <div class="avatar">👤</div>
      <h2>Welcome Back!</h2>
      <p class="sub">Sign in to continue to your account</p>
      <?php if ($error): ?><div class="alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="field">
          <label>Username</label>
          <div class="input-wrap">
            <span class="icon">👤</span>
            <input type="text" name="username" placeholder="Enter your username" required autofocus>
          </div>
        </div>
        <div class="field">
          <label>Password</label>
          <div class="input-wrap">
            <span class="icon">🔒</span>
            <input type="password" name="password" id="pw" placeholder="Enter your password" required>
            <button type="button" class="eye" onclick="togglePw()">👁</button>
          </div>
        </div>
        <div class="row-between">
          <label><input type="checkbox" name="remember"> Remember me</label>
          <a href="#">Forgot Password?</a>
        </div>
        <button class="btn-primary" type="submit">🔒 Sign In</button>
        <div class="divider">OR</div>
        <a class="btn-outline" href="#">👥 Sign in as Staff</a>
      </form>
    </div>
  </div>
</div>
<div class="login-footer">© 2026 MegaPharm — Mega Life Pharmacy Management System. All rights reserved.</div>
<script>
function togglePw(){const p=document.getElementById('pw');p.type=p.type==='password'?'text':'password';}
</script>
</body>
</html>