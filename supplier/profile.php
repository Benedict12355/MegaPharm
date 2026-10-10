<?php
require_once __DIR__ . '/../includes/functions.php';

$sup_page = 'My Profile';
$sup_active = 'profile';
$supUser = $_SESSION['supplier'];

$sup = db()->prepare('SELECT * FROM suppliers WHERE user_id = ?');
$sup->execute([$supUser['id']]);
$sup = $sup->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    db()->prepare('UPDATE suppliers SET supplier_name=?, contact_person=?, position=?, phone=?, business_email=?, business_address=? WHERE user_id=?')
       ->execute([
           trim($_POST['supplier_name']),
           trim($_POST['contact_person']),
           trim($_POST['position']),
           trim($_POST['phone']),
           trim($_POST['business_email']),
           trim($_POST['business_address']),
           $supUser['id'],
       ]);
    audit('Update', 'Supplier Profile', 'Profile updated');
    flash('success', 'Profile updated.');
    redirect(base_url('supplier/profile.php'));
}

require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">My Profile</h1>

<form method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

  <div class="card">
    <h3 class="card-title">Personal Information</h3>
    <div class="form-grid">
      <div class="field"><label>Full Name</label>
        <input name="contact_person" value="<?= e($sup['contact_person'] ?? $supUser['full_name']) ?>"></div>
      <div class="field"><label>Email Address</label>
        <input value="<?= e($supUser['email']) ?>" disabled style="background:#f1f5f9"></div>
      <div class="field"><label>Contact Number</label>
        <input name="phone" value="<?= e($sup['phone'] ?? '') ?>"></div>
      <div class="field"><label>Position</label>
        <input name="position" value="<?= e($sup['position'] ?? '') ?>" placeholder="Position"></div>
    </div>
  </div>

  <div class="card">
    <h3 class="card-title">Supplier Information</h3>
    <div class="form-grid">
      <div class="field"><label>Company Name</label>
        <input name="supplier_name" value="<?= e($sup['supplier_name'] ?? '') ?>" placeholder="Company Name"></div>
      <div class="field"><label>Supplier ID</label>
        <input value="<?= e($sup['supplier_code'] ?? 'SUP-001') ?>" disabled style="background:#f1f5f9"></div>
      <div class="field"><label>Business Email</label>
        <input name="business_email" type="email" value="<?= e($sup['business_email'] ?? '') ?>" placeholder="business@gmail.com"></div>
      <div class="field"><label>Business Address</label>
        <input name="business_address" value="<?= e($sup['business_address'] ?? $sup['address'] ?? '') ?>" placeholder="Business Address"></div>
    </div>
  </div>

  <div class="card">
    <h3 class="card-title">Account Information</h3>
    <div class="form-grid">
      <div class="field"><label>Username</label>
        <input value="<?= e($supUser['email']) ?>" disabled style="background:#f1f5f9"></div>
      <div class="field"><label>Account Role</label>
        <input value="Supplier" disabled style="background:#f1f5f9"></div>
    </div>
  </div>

  <div class="card" style="text-align:right">
    <button class="btn btn-primary">Save Changes</button>
  </div>
</form>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>