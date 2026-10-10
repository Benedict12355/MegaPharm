<?php
require_once __DIR__ . '/../includes/functions.php';

$custUser = $_SESSION['customer'] ?? null;
if (!$custUser) redirect(base_url('customer/login.php'));

$tab = $_GET['tab'] ?? 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'profile') {
        db()->prepare('UPDATE users SET full_name=?, email=? WHERE id=?')
           ->execute([trim($_POST['full_name']), trim($_POST['email']), $custUser['id']]);
        db()->prepare('UPDATE customers SET full_name=?, email=?, phone=? WHERE user_id=?')
           ->execute([trim($_POST['full_name']), trim($_POST['email']), trim($_POST['phone'] ?? ''), $custUser['id']]);
        $_SESSION['customer']['full_name'] = trim($_POST['full_name']);
        $_SESSION['customer']['email'] = trim($_POST['email']);
        flash('success', 'Profile updated.');
    } elseif ($act === 'address') {
        db()->prepare('UPDATE customers SET address=? WHERE user_id=?')
           ->execute([trim($_POST['address']), $custUser['id']]);
        flash('success', 'Address updated.');
    } elseif ($act === 'password') {
        $row = db()->prepare('SELECT password_hash FROM users WHERE id=?');
        $row->execute([$custUser['id']]);
        $hash = $row->fetchColumn();
        if (!password_verify($_POST['current_password'], $hash)) flash('error', 'Current password is incorrect.');
        elseif ($_POST['new_password'] !== $_POST['confirm_password']) flash('error', 'New passwords do not match.');
        elseif (strlen($_POST['new_password']) < 6) flash('error', 'Password must be at least 6 characters.');
        else {
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')
               ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $custUser['id']]);
            flash('success', 'Password updated.');
        }
    }
    redirect(base_url('customer/account.php?tab=' . $act));
}

$me = db()->prepare('SELECT * FROM customers WHERE user_id = ?');
$me->execute([$custUser['id']]);
$me = $me->fetch() ?: [];

$cust_page = 'My Account';
$cust_active = 'account';
require_once __DIR__ . '/../customer-includes/header.php';
?>
<h1 style="display:none">My Account</h1>
<h2 style="font-size:20px;margin:0 0 18px">My Account</h2>

<div class="account-grid">
  <aside class="account-side">
    <div class="account-avatar">👤</div>
    <div class="account-name"><?= e($custUser['full_name']) ?></div>
    <div class="account-email"><?= e($custUser['email']) ?></div>
    <nav class="account-nav">
      <a href="?tab=profile"  class="<?= $tab === 'profile' ? 'active' : '' ?>">My Profile</a>
      <a href="?tab=address"  class="<?= $tab === 'address' ? 'active' : '' ?>">Delivery Address</a>
      <a href="<?= base_url('customer/orders.php') ?>">Order History</a>
      <a href="?tab=password" class="<?= $tab === 'password' ? 'active' : '' ?>">Change Password</a>
      <a href="#" onclick="event.preventDefault(); document.getElementById('custLogout').classList.add('show')">Logout</a>
    </nav>
  </aside>

  <section>
    <?php if ($tab === 'profile'): ?>
      <div class="form-card">
        <h3>Profile</h3>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="act" value="profile">
          <div class="form-grid">
            <div class="field"><label>Full Name</label><input name="full_name" value="<?= e($custUser['full_name']) ?>" required></div>
            <div class="field"><label>Email</label><input name="email" type="email" value="<?= e($custUser['email']) ?>" required></div>
            <div class="field full"><label>Phone Number</label><input name="phone" value="<?= e($me['phone'] ?? '') ?>"></div>
          </div>
          <div style="text-align:right;margin-top:12px"><button class="btn btn-primary">Save Changes</button></div>
        </form>
      </div>
      <div class="form-card">
        <h3>Delivery Address</h3>
        <div style="background:#e0edff;padding:18px;border-radius:8px;font-size:13px;line-height:1.6">
          <b>Default Address</b><br>
          <?= nl2br(e($me['address'] ?? 'No address set.')) ?><br>
          Contact: <?= e($me['phone'] ?? '—') ?>
        </div>
      </div>

    <?php elseif ($tab === 'address'): ?>
      <div class="form-card">
        <h3>Delivery Address</h3>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="act" value="address">
          <div class="field"><label>Full Address</label>
            <textarea name="address" rows="4"><?= e($me['address'] ?? '') ?></textarea></div>
          <div style="text-align:right;margin-top:12px"><button class="btn btn-primary">Save Address</button></div>
        </form>
      </div>

    <?php elseif ($tab === 'password'): ?>
      <div class="form-card">
        <h3>Change Password</h3>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="act" value="password">
          <div class="field"><label>Current Password</label><input type="password" name="current_password" required></div>
          <div class="form-grid">
            <div class="field"><label>New Password</label><input type="password" name="new_password" minlength="6" required></div>
            <div class="field"><label>Confirm New Password</label><input type="password" name="confirm_password" minlength="6" required></div>
          </div>
          <div style="text-align:right;margin-top:12px"><button class="btn btn-primary">Update Password</button></div>
        </form>
      </div>
    <?php endif; ?>
  </section>
</div>

<div class="cust-modal-overlay" id="custLogout">
  <div class="cust-modal" style="max-width:420px;text-align:center">
    <div style="font-size:50px;margin-bottom:6px">🚪</div>
    <h3 style="text-align:center">Confirm Logout</h3>
    <p class="text-muted">Are you sure you want to logout from the system?</p>
    <div style="display:flex;gap:10px;justify-content:center;margin-top:20px">
      <button class="btn" onclick="document.getElementById('custLogout').classList.remove('show')">Cancel</button>
      <a class="btn btn-primary" href="<?= base_url('customer/logout.php') ?>">Logout</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../customer-includes/footer.php'; ?>