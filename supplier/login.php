<?php
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['supplier'])) {
    redirect(base_url('supplier/index.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND role = 'supplier' LIMIT 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($pass, $u['password_hash'])) {
        $error = 'Invalid email or password.';
    } elseif ($u['status'] !== 'active') {
        $error = 'Your account is not active.';
    } else {
        $_SESSION['supplier'] = [
            'id'        => (int)$u['id'],
            'full_name' => $u['full_name'],
            'email'     => $u['email'],
        ];
        db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$u['id']]);
        audit('Login', 'Supplier', 'Logged in');
        redirect(base_url('supplier/index.php'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MegaPharm Login</title>
<link rel="stylesheet" href="<?= base_url('assets/css/supplier.css') ?>">
<style>
body { margin: 0; background: #fff; font-family: 'Segoe UI', Arial, sans-serif; }
.top-bar { padding: 20px 40px; }
.top-brand { display: flex; align-items: center; gap: 14px; }
.top-brand .brand-logo { background: #0b1e3b; color: #fff; padding: 12px 16px; border-radius: 10px; font-weight: 700; font-size: 12px; text-align: center; letter-spacing: .5px; }
.top-brand .brand-logo small { font-weight: 400; color: #7dd3fc; font-size: 9px; display: block; }
.top-brand h1 { margin: 0; font-size: 34px; color: #1e3a8a; font-weight: 400; }
.login-bg { background: #1e3a8a; padding: 60px 20px; display: flex; justify-content: center; }
.login-card { background: #fff; border-radius: 12px; padding: 34px 40px; width: 100%; max-width: 560px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
.login-card h2 { margin: 0 0 22px; font-size: 22px; }
.login-card label { display: block; font-size: 14px; font-weight: 600; margin: 16px 0 6px; }
.login-card input { width: 100%; padding: 13px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
.login-card .row-between { display: flex; justify-content: space-between; align-items: center; margin: 18px 0; font-size: 13px; }
.login-card .btn-login { width: 100%; background: #2563eb; color: #fff; border: 0; padding: 14px; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; letter-spacing: .5px; }
.login-card .foot { text-align: center; font-size: 12px; color: #64748b; margin-top: 26px; line-height: 1.6; }
.login-card .foot a { color: #2563eb; }
.login-card .social { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 18px; }
.login-card .social button { padding: 12px; border: 1.5px solid #2563eb; background: #fff; color: #2563eb; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
.login-card .divider { display: flex; align-items: center; gap: 12px; margin: 22px 0; color: #94a3b8; font-size: 12px; }
.login-card .divider::before, .login-card .divider::after { content: ""; flex: 1; height: 1px; background: #e2e8f0; }
</style>
</head>
<body>
<div class="top-bar">
  <div class="top-brand">
    <div class="brand-logo">MEGA<br><small>We care</small></div>
    <h1>MegaPharm <span style="color:#0f172a">Login</span></h1>
  </div>
</div>

<div class="login-bg">
  <div class="login-card">
    <h2 style="text-align:center">Welcome Back!</h2>
    <p style="text-align:center;color:#64748b;font-size:13px;margin:-10px 0 22px">
      Sign in to continue to your account
    </p>

    <?php if ($error): ?>
      <div style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:14px">
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <label>Email</label>
      <input type="email" name="email" placeholder="Enter your email" required autofocus>
      <label>Password</label>
      <input type="password" name="password" placeholder="Enter your password" required>
      <div class="row-between">
        <label style="margin:0;font-weight:400;display:flex;align-items:center;gap:6px">
          <input type="checkbox" name="remember" style="margin:0;width:auto"> Remember me
        </label>
        <a href="#" style="color:#2563eb;text-decoration:none">Forgot Password?</a>
      </div>
      <button class="btn-login">🔒 Sign In</button>
      <div class="divider">OR</div>
      <div class="social">
        <button type="button">📘 FACEBOOK</button>
        <button type="button">🇬 GOOGLE</button>
      </div>
    </form>
    <p class="foot">By logging in, you agree to MegaPharm's <a href="#">Terms of Service</a><br>&amp; <a href="#">Privacy Policy</a></p>
  </div>
</div>
</body>
</html>