<?php
// admin/settings.php
require_once __DIR__ . '/../includes/functions.php';
require_login();

// Fetch current user BEFORE using $u
$u = current_user();

$active = 'settings';
$page_title = 'Settings';

$isAdmin = is_admin();

/* ---------- SAVE PERSONAL INFO (all roles) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'profile') {
    csrf_check($_POST['csrf'] ?? '');
    db()->prepare('UPDATE users SET full_name=?, username=?, email=? WHERE id=?')
       ->execute([
           trim($_POST['full_name']),
           trim($_POST['username']),
           trim($_POST['email']),
           $u['id'],
       ]);
    $_SESSION['user']['full_name'] = trim($_POST['full_name']);
    $_SESSION['user']['username']  = trim($_POST['username']);
    $_SESSION['user']['email']     = trim($_POST['email']);
    audit('Update', 'Profile', 'Updated own profile');
    flash('success', 'Profile updated.');
    redirect(base_url('admin/settings.php'));
}

/* ---------- CHANGE PASSWORD (all roles) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'password') {
    csrf_check($_POST['csrf'] ?? '');
    $old  = $_POST['old_password'] ?? '';
    $new  = $_POST['new_password'] ?? '';
    $conf = $_POST['confirm_password'] ?? '';

    $row = db()->prepare('SELECT password_hash FROM users WHERE id=?');
    $row->execute([$u['id']]);
    $hash = $row->fetchColumn();

    if (!password_verify($old, $hash)) {
        flash('error', 'Old password is incorrect.');
    } elseif ($new !== $conf) {
        flash('error', 'New passwords do not match.');
    } elseif (strlen($new) < 6) {
        flash('error', 'Password must be at least 6 characters.');
    } else {
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')
           ->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        audit('Update', 'Password', 'Changed own password');
        flash('success', 'Password changed.');
    }
    redirect(base_url('admin/settings.php'));
}

/* ---------- SAVE SYSTEM SETTINGS (admin only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'system') {
    require_role('admin');
    csrf_check($_POST['csrf'] ?? '');
    foreach ($_POST['settings'] ?? [] as $k => $v) {
        db()->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)')
           ->execute([$k, $v, $u['id']]);
    }
    audit('Update', 'Settings', 'System settings updated');
    flash('success', 'System settings saved.');
    redirect(base_url('admin/settings.php'));
}

// Fresh user record for display
$stmt = db()->prepare('SELECT * FROM users WHERE id=?');
$stmt->execute([$u['id']]);
$me = $stmt->fetch();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Settings</h1>

<?php if (!$isAdmin): ?>

  <!-- ============ STAFF / NON-ADMIN VIEW ============ -->
  <div class="card">
    <h3 class="card-title">Personal Information</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="profile">
      <div class="form-grid">
        <div class="field">
          <label>Full Name</label>
          <input name="full_name" value="<?= e($me['full_name']) ?>" required>
        </div>
        <div class="field">
          <label>Role</label>
          <input value="<?= e(ucfirst($me['role'])) ?>" disabled style="background:#f1f5f9">
        </div>
        <div class="field">
          <label>Username</label>
          <input name="username" value="<?= e($me['username']) ?>" required>
        </div>
        <div class="field">
          <label>Status</label>
          <input value="<?= e(ucfirst($me['status'])) ?>" disabled style="background:#f1f5f9">
        </div>
        <div class="field">
          <label>Email</label>
          <input name="email" type="email" value="<?= e($me['email']) ?>" required>
        </div>
        <div class="field">
          <label>Date Created</label>
          <input value="<?= e(date('F j, Y', strtotime($me['created_at']))) ?>" disabled style="background:#f1f5f9">
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3 class="card-title">Password</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="password">
      <div class="form-grid">
        <div class="field">
          <label>Old Password</label>
          <input name="old_password" type="password" required>
        </div>
        <div class="field">
          <label>New Password</label>
          <input name="new_password" type="password" minlength="6" required>
        </div>
        <div class="field full">
          <label>Confirm Password</label>
          <input name="confirm_password" type="password" minlength="6" required>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>

<?php else: ?>

  <!-- ============ ADMIN VIEW ============ -->
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="act" value="system">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">

      <div class="card">
        <h3 class="card-title">GENERAL SETTINGS</h3>
        <div class="form-grid">
          <div class="field"><label>Pharmacy Name</label>
            <input name="settings[pharmacy_name]" value="<?= e(setting('pharmacy_name')) ?>"></div>
          <div class="field"><label>Contact Number</label>
            <input name="settings[contact_number]" value="<?= e(setting('contact_number')) ?>"></div>
          <div class="field full"><label>Address</label>
            <textarea name="settings[address]" rows="2"><?= e(setting('address')) ?></textarea></div>
          <div class="field full"><label>Email Address</label>
            <input name="settings[email]" value="<?= e(setting('email')) ?>"></div>
        </div>
      </div>

      <div class="card">
        <h3 class="card-title">INVENTORY SETTINGS</h3>
        <div class="form-grid">
          <div class="field"><label>Default Reorder Level</label>
            <input name="settings[default_reorder_level]" type="number" value="<?= e(setting('default_reorder_level', 20)) ?>"></div>
          <div class="field"><label>Alert Before Expiry (days)</label>
            <input name="settings[expiry_alert_days]" type="number" value="<?= e(setting('expiry_alert_days', 30)) ?>"></div>
          <div class="field"><label>Low Stock Alert</label>
            <select name="settings[low_stock_alert]">
              <option value="1" <?= setting('low_stock_alert') === '1' ? 'selected' : '' ?>>On</option>
              <option value="0" <?= setting('low_stock_alert') === '0' ? 'selected' : '' ?>>Off</option>
            </select>
          </div>
          <div class="field"><label>Expiry Alert</label>
            <select name="settings[expiry_alert]">
              <option value="1" <?= setting('expiry_alert') === '1' ? 'selected' : '' ?>>On</option>
              <option value="0" <?= setting('expiry_alert') === '0' ? 'selected' : '' ?>>Off</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card">
        <h3 class="card-title">Order Settings</h3>
        <div class="form-grid">
          <div class="field"><label>Require Staff Confirmation</label>
            <select name="settings[require_staff_order_confirmation]">
              <option value="1" <?= setting('require_staff_order_confirmation') === '1' ? 'selected' : '' ?>>On</option>
              <option value="0" <?= setting('require_staff_order_confirmation') === '0' ? 'selected' : '' ?>>Off</option>
            </select>
          </div>
          <div class="field"><label>Allow Customer Cancellation</label>
            <select name="settings[allow_customer_cancellation]">
              <option value="1" <?= setting('allow_customer_cancellation') === '1' ? 'selected' : '' ?>>On</option>
              <option value="0" <?= setting('allow_customer_cancellation') === '0' ? 'selected' : '' ?>>Off</option>
            </select>
          </div>
          <div class="field full"><label>Cancellation Time Limit (minutes)</label>
            <input name="settings[customer_cancellation_limit_minutes]" type="number"
                   value="<?= e(setting('customer_cancellation_limit_minutes', 30)) ?>"></div>
        </div>
      </div>

      <div class="card">
        <h3 class="card-title">Payment Settings</h3>
        <div class="form-grid">
          <div class="field"><label>Cash on Delivery</label>
            <select name="settings[payment_cod]">
              <option value="1" <?= setting('payment_cod') === '1' ? 'selected' : '' ?>>On</option>
              <option value="0" <?= setting('payment_cod') === '0' ? 'selected' : '' ?>>Off</option>
            </select>
          </div>
          <div class="field"><label>Online Payment</label>
            <select name="settings[payment_online]">
              <option value="1" <?= setting('payment_online') === '1' ? 'selected' : '' ?>>On</option>
              <option value="0" <?= setting('payment_online') === '0' ? 'selected' : '' ?>>Off</option>
            </select>
          </div>
          <div class="field"><label>Cash</label>
            <select name="settings[payment_cash]">
              <option value="1" <?= setting('payment_cash') === '1' ? 'selected' : '' ?>>On</option>
              <option value="0" <?= setting('payment_cash') === '0' ? 'selected' : '' ?>>Off</option>
            </select>
          </div>
          <div class="field"><label>GCash</label>
            <select name="settings[payment_gcash]">
              <option value="1" <?= setting('payment_gcash') === '1' ? 'selected' : '' ?>>On</option>
              <option value="0" <?= setting('payment_gcash') === '0' ? 'selected' : '' ?>>Off</option>
            </select>
          </div>
          <div class="field"><label>Bank Transfer</label>
            <select name="settings[payment_bank_transfer]">
              <option value="1" <?= setting('payment_bank_transfer') === '1' ? 'selected' : '' ?>>On</option>
              <option value="0" <?= setting('payment_bank_transfer') === '0' ? 'selected' : '' ?>>Off</option>
            </select>
          </div>
          <div class="field"><label>Card Payment</label>
            <select name="settings[payment_card]">
              <option value="0" <?= setting('payment_card') === '0' ? 'selected' : '' ?>>Off</option>
              <option value="1" <?= setting('payment_card') === '1' ? 'selected' : '' ?>>On</option>
            </select>
          </div>
        </div>
      </div>

    </div>
    <div class="card" style="text-align:right">
      <button class="btn btn-primary">Save Changes</button>
    </div>
  </form>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>