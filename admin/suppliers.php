<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'suppliers';
$page_title = 'Suppliers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    if (($_POST['act'] ?? '') === 'create') {
        $code = next_number('suppliers','supplier_code','SUP-');
        db()->prepare('INSERT INTO suppliers (supplier_code, supplier_name, contact_person, phone, email, address, status) VALUES (?,?,?,?,?,?,?)')
           ->execute([$code, $_POST['supplier_name'], $_POST['contact_person'], $_POST['phone'], $_POST['email'], $_POST['address'], $_POST['status']]);
        audit('Add', 'Suppliers', 'Created '.$_POST['supplier_name']);
        flash('success','Supplier created.');
        redirect(base_url('admin/suppliers.php'));
    }
}

$suppliers = db()->query('SELECT * FROM suppliers ORDER BY id DESC')->fetchAll();
$activeC = (int)db()->query("SELECT COUNT(*) FROM suppliers WHERE status='active'")->fetchColumn();
$inactiveC = (int)db()->query("SELECT COUNT(*) FROM suppliers WHERE status='inactive'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Suppliers</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Total Suppliers</div><div class="stat-value"><?= count($suppliers) ?></div></div>
  <div class="stat"><div class="stat-label">Active</div><div class="stat-value" style="color:var(--green)"><?= $activeC ?></div></div>
  <div class="stat"><div class="stat-label">Inactive</div><div class="stat-value" style="color:var(--red)"><?= $inactiveC ?></div></div>
</div>

<div class="card">
  <div class="toolbar">
    <form class="search"><input class="input" placeholder="Search Supplier..."></form>
    <select class="select"><option>Status</option></select>
    <button class="btn btn-primary" onclick="openModal('addSupplier')">➕ Add Supplier</button>
  </div>

  <div class="supplier-grid">
    <?php foreach ($suppliers as $s): ?>
      <div class="supplier-card">
        <div class="supplier-head">
          <div class="supplier-avatar">🏭</div>
          <div>
            <p class="supplier-name"><?= e($s['supplier_name']) ?></p>
            <p class="supplier-meta"><?= e($s['contact_person'] ?? '—') ?></p>
            <p class="supplier-meta"><?= e($s['phone'] ?? '') ?></p>
          </div>
        </div>
        <div class="supplier-foot">Supplier Code: <?= e($s['supplier_code']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal-overlay" id="addSupplier">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('addSupplier')">✕</button>
    <h3>Add New Supplier</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="create">
      <div class="form-grid">
        <div class="field"><label>Supplier Name</label><input name="supplier_name" required></div>
        <div class="field"><label>Email Address</label><input name="email" type="email"></div>
        <div class="field"><label>Contact Person</label><input name="contact_person"></div>
        <div class="field"><label>Address</label><input name="address"></div>
        <div class="field"><label>Contact No.</label><input name="phone"></div>
        <div class="field"><label>Status</label>
          <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('addSupplier')">Cancel</button>
        <button class="btn btn-primary">Save Supplier</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>