<?php
// admin/suppliers.php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'suppliers';
$page_title = 'Suppliers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $code = next_number('suppliers', 'supplier_code', 'SUP-');
        db()->prepare('INSERT INTO suppliers 
            (supplier_code, supplier_name, contact_person, phone, email, address, status)
            VALUES (?,?,?,?,?,?,?)')
           ->execute([
               $code,
               trim($_POST['supplier_name']),
               trim($_POST['contact_person'] ?? ''),
               trim($_POST['phone'] ?? ''),
               trim($_POST['email'] ?? ''),
               trim($_POST['address'] ?? ''),
               $_POST['status'] ?? 'active',
           ]);
        audit('Add', 'Suppliers', 'Created ' . $_POST['supplier_name']);
        flash('success', 'Supplier created.');

    } elseif ($act === 'update') {
        $id = (int)$_POST['id'];
        db()->prepare('UPDATE suppliers 
            SET supplier_name=?, contact_person=?, phone=?, email=?, address=?, status=?
            WHERE id=?')
           ->execute([
               trim($_POST['supplier_name']),
               trim($_POST['contact_person'] ?? ''),
               trim($_POST['phone'] ?? ''),
               trim($_POST['email'] ?? ''),
               trim($_POST['address'] ?? ''),
               $_POST['status'] ?? 'active',
               $id,
           ]);
        audit('Update', 'Suppliers', "Updated #$id");
        flash('success', 'Supplier updated.');

    } elseif ($act === 'delete') {
        $id = (int)$_POST['id'];
        try {
            db()->prepare('DELETE FROM suppliers WHERE id=?')->execute([$id]);
            audit('Delete', 'Suppliers', "Deleted #$id");
            flash('success', 'Supplier deleted.');
        } catch (PDOException $e) {
            flash('error', 'Cannot delete: supplier has related records.');
        }
    }
    redirect(base_url('admin/suppliers.php' . qs()));
}

/* ---------- FILTERS ---------- */
$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

$sql = "SELECT * FROM suppliers WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (supplier_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR supplier_code LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if ($status === 'active' || $status === 'inactive') {
    $sql .= " AND status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY supplier_name ASC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

$statTotal    = (int)db()->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$statActive   = (int)db()->query("SELECT COUNT(*) FROM suppliers WHERE status='active'")->fetchColumn();
$statInactive = (int)db()->query("SELECT COUNT(*) FROM suppliers WHERE status='inactive'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Suppliers</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Total Suppliers</div><div class="stat-value"><?= $statTotal ?></div></div>
  <div class="stat"><div class="stat-label">Active</div><div class="stat-value" style="color:var(--green)"><?= $statActive ?></div></div>
  <div class="stat"><div class="stat-label">Inactive</div><div class="stat-value" style="color:var(--red)"><?= $statInactive ?></div></div>
</div>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search Supplier..."
             value="<?= e($q) ?>" style="width:100%">
    </div>

    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>

    <?php if ($q || $status): ?>
      <a class="btn" href="<?= base_url('admin/suppliers.php') ?>">Reset</a>
    <?php endif; ?>

    <button class="btn btn-primary" type="button" onclick="openModal('addSupplier')">➕ Add Supplier</button>
  </form>

  <div class="supplier-grid">
    <?php if (!$suppliers): ?>
      <div class="empty">No suppliers match your filters.</div>
    <?php endif; ?>
    <?php foreach ($suppliers as $s): ?>
      <div class="supplier-card">
        <div class="supplier-head">
          <div class="supplier-avatar">🏭</div>
          <div style="flex:1">
            <p class="supplier-name"><?= e($s['supplier_name']) ?></p>
            <p class="supplier-meta"><?= e($s['contact_person'] ?? '—') ?></p>
            <p class="supplier-meta"><?= e($s['phone'] ?? '') ?></p>
          </div>
          <span class="badge <?= $s['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
            <?= e(ucfirst($s['status'])) ?>
          </span>
        </div>
        <div class="supplier-foot" style="display:flex;justify-content:space-between;align-items:center">
          <span><?= e($s['supplier_code']) ?></span>
          <span class="row-actions">
            <button class="btn-icon" title="Edit"
                    onclick='editSupplier(<?= json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this supplier?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="act" value="delete">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button class="btn-icon danger" title="Delete">🗑️</button>
            </form>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ============ MODAL: ADD SUPPLIER ============ -->
<div class="modal-overlay" id="addSupplier">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('addSupplier')">✕</button>
    <h3>Add New Supplier</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="create">
      <div class="form-grid">
        <div class="field"><label>Supplier Name <span class="req">*</span></label>
          <input name="supplier_name" required></div>
        <div class="field"><label>Email Address</label>
          <input name="email" type="email"></div>
        <div class="field"><label>Contact Person</label>
          <input name="contact_person"></div>
        <div class="field"><label>Address</label>
          <input name="address"></div>
        <div class="field"><label>Contact No.</label>
          <input name="phone"></div>
        <div class="field"><label>Status</label>
          <select name="status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('addSupplier')">Cancel</button>
        <button class="btn btn-primary">Save Supplier</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ MODAL: EDIT SUPPLIER ============ -->
<div class="modal-overlay" id="editSupplier">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('editSupplier')">✕</button>
    <h3>Edit Supplier</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="update">
      <input type="hidden" name="id" id="es-id">
      <div class="form-grid">
        <div class="field"><label>Supplier Name</label>
          <input name="supplier_name" id="es-name" required></div>
        <div class="field"><label>Email Address</label>
          <input name="email" id="es-email" type="email"></div>
        <div class="field"><label>Contact Person</label>
          <input name="contact_person" id="es-contact"></div>
        <div class="field"><label>Address</label>
          <input name="address" id="es-address"></div>
        <div class="field"><label>Contact No.</label>
          <input name="phone" id="es-phone"></div>
        <div class="field"><label>Status</label>
          <select name="status" id="es-status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('editSupplier')">Cancel</button>
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function editSupplier(s) {
  document.getElementById('es-id').value       = s.id;
  document.getElementById('es-name').value     = s.supplier_name;
  document.getElementById('es-email').value    = s.email || '';
  document.getElementById('es-contact').value  = s.contact_person || '';
  document.getElementById('es-address').value  = s.address || '';
  document.getElementById('es-phone').value    = s.phone || '';
  document.getElementById('es-status').value   = s.status || 'active';
  openModal('editSupplier');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>