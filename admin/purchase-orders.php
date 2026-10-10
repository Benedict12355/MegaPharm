<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'purchase';
$page_title = 'Purchase Orders';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    if (($_POST['act'] ?? '') === 'create') {
        $num = next_number('purchase_orders','purchase_order_number','PO-');
        $sub = (float)($_POST['subtotal'] ?? 0);
        db()->prepare('INSERT INTO purchase_orders (purchase_order_number, supplier_id, created_by, order_date, expected_date, status, subtotal, notes) VALUES (?,?,?,CURDATE(),?,?,?,?)')
           ->execute([$num, (int)$_POST['supplier_id'], $u['id'], $_POST['expected_date'] ?: null, 'pending', $sub, $_POST['notes'] ?? '']);
        $po = (int)db()->lastInsertId();
        audit('Create', 'Purchase Orders', "Created $num");
        flash('success','Purchase Order created.');
        redirect(base_url('admin/purchase-orders.php'));
    }
}

$orders = db()->query("SELECT po.*, s.supplier_name FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id ORDER BY po.id DESC LIMIT 50")->fetchAll();
$suppliers = db()->query('SELECT id, supplier_name FROM suppliers WHERE status="active" ORDER BY supplier_name')->fetchAll();
$products = db()->query('SELECT id, product_name, selling_price FROM products WHERE status="active" ORDER BY product_name LIMIT 100')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Purchase Orders</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Pending</div><div class="stat-value"><?= (int)db()->query("SELECT COUNT(*) FROM purchase_orders WHERE status='pending'")->fetchColumn() ?></div></div>
  <div class="stat"><div class="stat-label">Approved</div><div class="stat-value"><?= (int)db()->query("SELECT COUNT(*) FROM purchase_orders WHERE status='approved'")->fetchColumn() ?></div></div>
  <div class="stat"><div class="stat-label">Received</div><div class="stat-value"><?= (int)db()->query("SELECT COUNT(*) FROM purchase_orders WHERE status='received'")->fetchColumn() ?></div></div>
</div>

<div class="card">
  <div class="toolbar">
    <form class="search"><input class="input" placeholder="Search..."></form>
    <select class="select"><option>All Status</option></select>
    <select class="select"><option>Supplier</option></select>
    <button class="btn btn-primary" onclick="openModal('createPO')">➕ Create PO</button>
  </div>
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>PO No.</th><th>Supplier</th><th>Total</th><th>Date</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= e($o['purchase_order_number']) ?></td>
          <td><?= e($o['supplier_name']) ?></td>
          <td><?= peso($o['subtotal']) ?></td>
          <td><?= e($o['order_date']) ?></td>
          <td><span class="badge badge-info"><?= e(ucfirst(str_replace('_',' ',$o['status']))) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?><tr><td colspan="5" class="empty">No purchase orders yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="toolbar" style="margin-top:14px">
    <button class="btn">View</button><button class="btn">Edit</button><button class="btn">Cancel</button>
  </div>
</div>

<div class="modal-overlay" id="createPO">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('createPO')">✕</button>
    <h3>Create Purchase Order</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="create">
      <div class="form-grid">
        <div class="field full"><label>Supplier</label>
          <select name="supplier_id" required>
            <option value="">Select...</option>
            <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['supplier_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field full"><label>Expected Delivery Date</label><input type="date" name="expected_date"></div>
        <div class="field full"><label>Subtotal (₱)</label><input type="number" step="0.01" name="subtotal" value="0"></div>
        <div class="field full"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('createPO')">Cancel</button>
        <button class="btn btn-primary">Create Purchase Order</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>