<?php
require_once __DIR__ . '/../includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $name  = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$name || !$email || !$pass) $error = 'All fields are required.';
    elseif (strlen($pass) < 6) $error = 'Password must be at least 6 characters.';
    else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $baseUser = explode('@', $email)[0];
            $username = $baseUser;
            $i = 1;
            while (db()->query("SELECT id FROM users WHERE username=" . db()->quote($username))->fetchColumn()) {
                $username = $baseUser . $i++;
            }
            db()->prepare('INSERT INTO users (full_name, username, email, password_hash, role, status)
                VALUES (?,?,?,?,?,?)')->execute([$name, $username, $email, $hash, 'customer', 'active']);
            $uid = (int)db()->lastInsertId();
            $code = next_number('customers', 'customer_code', 'CUST-');
            db()->prepare('INSERT INTO customers (user_id, customer_code, full_name, email) VALUES (?,?,?,?)')
               ->execute([$uid, $code, $name, $email]);
            flash('success', 'Account created. Please log in.');
            redirect(base_url('customer/login.php'));
        } catch (PDOException $e) {
            $error = 'Email already registered.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sign Up — MegaPharm</title>
<link rel="stylesheet" href="<?= base_url('assets/css/customer.css') ?>">
<style>
body { margin: 0; background: #fff; font-family: 'Segoe UI', Arial, sans-serif; }
.login-bg { background: #1e3a8a; padding: 60px 20px; display: flex; justify-content: center; min-height: 100vh; }
.login-card { background: #fff; border-radius: 12px; padding: 34px 40px; width: 100%; max-width: 460px; }
.login-card h2 { margin: 0 0 22px; }
.login-card label { display: block; font-size: 14px; font-weight: 600; margin: 16px 0 6px; }
.login-card input { width: 100%; padding: 13px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
.btn-login { width: 100%; background: #2563eb; color: #fff; border: 0; padding: 14px; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 20px; }
</style>
</head>
<body>
<div class="login-bg">
  <div class="login-card">
    <h2>Sign Up</h2>
    <?php if ($error): ?>
      <div style="background:#fef2f2;color:#b91c1c;padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:10px"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <label>Full Name</label><input name="full_name" required>
      <label>Email</label><input type="email" name="email" required>
      <label>Password</label><input type="password" name="password" minlength="6" required>
      <button class="btn-login">CREATE ACCOUNT</button>
    </form>
    <p style="text-align:center;font-size:13px;margin-top:20px">
      Already have an account? <a href="<?= base_url('customer/login.php') ?>" style="color:#2563eb;font-weight:600">Log In</a>
    </p>
  </div>
</div>
</body>
</html>