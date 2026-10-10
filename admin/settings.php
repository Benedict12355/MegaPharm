<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'settings';
$page_title = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    foreach ($_POST['settings'] ?? [] as $k => $v) {
        db()->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)')
           ->execute([$k, $v, $u['id']]);
    }
    audit('Update','Settings','Settings updated');
    flash('success','Settings saved.');
    redirect(base_url('admin/settings.php'));
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Settings</h1>

<form method="post">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
  <div class="card">
    <h3 class="card-title">GENERAL SETTINGS</h3>
    <div class="form-grid">
      <div class="field"><label>Pharmacy Name</label><input name="settings[pharmacy_name]" value="<?= e(setting('pharmacy_name')) ?>"></div>
      <div class="field"><label>Contact Number</label><input name="settings[contact_number]" value="<?= e(setting('contact_number')) ?>"></div>
      <div class="field"><label>Address</label><textarea name="settings[address]" rows="2"><?= e(setting('address')) ?></textarea></div>
      <div class="field"><label>Email Address</label><input name="settings[email]" value="<?= e(setting('email')) ?>"></div>
    </div>
  </div>

  <div class="card">
    <h3 class="card-title">INVENTORY SETTINGS</h3>
    <div class="form-grid">
      <div class="field"><label>Default Reorder Level</label><input name="settings[default_reorder_level]" type="number" value="<?= e(setting('default_reorder_level',20)) ?>"></div>
      <div class="field"><label>Alert Before Expiry (days)</label><input name="settings[expiry_alert_days]" type="number" value="<?= e(setting('expiry_alert_days',30)) ?>"></div>
      <div class="field"><label>Low Stock Alert</label><select name="settings[low_stock_alert]"><option value="1">On</option><option value="0">Off</option></select></div>
      <div class="field"><label>Expiry Alert</label><select name="settings[expiry_alert]"><option value="1">On</option><option value="0">Off</option></select></div>
    </div>
  </div>

  <div class="card">
    <h3 class="card-title">Order Settings</h3>
    <div class="form-grid">
      <div class="field"><label>Require Staff Confirmation</label><select name="settings[require_staff_order_confirmation]"><option value="1">On</option><option value="0">Off</option></select></div>
      <div class="field"><label>Allow Customer Cancellation</label><select name="settings[allow_customer_cancellation]"><option value="1">On</option><option value="0">Off</option></select></div>
      <div class="field"><label>Cancellation Time Limit (minutes)</label><input name="settings[customer_cancellation_limit_minutes]" type="number" value="<?= e(setting('customer_cancellation_limit_minutes',30)) ?>"></div>
    </div>
  </div>

  <div class="card">
    <h3 class="card-title">Payment Settings</h3>
    <div class="form-grid">
      <div class="field"><label>Cash on Delivery</label><select name="settings[payment_cod]"><option value="1">On</option><option value="0">Off</option></select></div>
      <div class="field"><label>Online Payment</label><select name="settings[payment_online]"><option value="1">On</option><option value="0">Off</option></select></div>
      <div class="field"><label>Cash</label><select name="settings[payment_cash]"><option value="1">On</option><option value="0">Off</option></select></div>
      <div class="field"><label>GCash</label><select name="settings[payment_gcash]"><option value="1">On</option><option value="0">Off</option></select></div>
      <div class="field"><label>Bank Transfer</label><select name="settings[payment_bank_transfer]"><option value="1">On</option><option value="0">Off</option></select></div>
      <div class="field"><label>Card Payment</label><select name="settings[payment_card]"><option value="0">Off</option><option value="1">On</option></select></div>
    </div>
  </div>
</div>
<div class="card" style="text-align:right">
  <button class="btn btn-primary">Save Changes</button>
</div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>