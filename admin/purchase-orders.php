<?php
// admin/purchase-orders.php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'purchase';
$page_title = 'Purchase Orders';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $num = next_number('purchase_orders', 'purchase_order_number', 'PO-');
        $sub = (float)($_POST['subtotal'] ?? 0);
        db()->prepare('INSERT INTO purchase_orders 
            (purchase_order_number, supplier_id, created_by, order_date, expected_date, status, subtotal, notes)
            VALUES (?,?,?,CURDATE(),?,?,?,?)')
           ->execute([
               $num,
               (int)$_POST['supplier_id'],
               $u['id'],
               $_POST['expected_date'] ?: null,
               'pending',
               $sub,
               $_POST['notes'] ?? '',
           ]);
        audit('Create', 'Purchase Orders', "Created $num");
        flash('success', 'Purchase Order created.');

    } elseif ($act === 'cancel') {
        $id = (int)$_POST['id'];
        db()->prepare("UPDATE purchase_orders SET status='cancelled' WHERE id=?")->execute([$id]);
        audit('Cancel', 'Purchase Orders', "Cancelled #$id");
        flash('success', 'Purchase Order cancelled.');
    }
    redirect(base_url('admin/purchase-orders.php' . qs()));
}

/* ---------- FILTERS ---------- */
$q        = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$supplier = $_GET['supplier'] ?? '';

$sql = "SELECT po.*, s.supplier_name
        FROM purchase_orders po
        JOIN suppliers s ON s.id = po.supplier_id
        WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (po.purchase_order_number LIKE ? OR s.supplier_name LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like);
}
if ($status !== '') {
    $sql .= " AND po.status = ?";
    $params[] = $status;
}
if ($supplier !== '' && ctype_digit((string)$supplier)) {
    $sql .= " AND po.supplier_id = ?";
    $params[] = (int)$supplier;
}
$sql .= " ORDER BY po.id DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statPending  = (int)db()->query("SELECT COUNT(*) FROM purchase_orders WHERE status='pending'")->fetchColumn();
$statApproved = (int)db()->query("SELECT COUNT(*) FROM purchase_orders WHERE status='approved'")->fetchColumn();
$statReceived = (int)db()->query("SELECT COUNT(*) FROM purchase_orders WHERE status='received'")->fetchColumn();

$suppliers = db()->query('SELECT id, supplier_name FROM suppliers WHERE status="active" ORDER BY supplier_name')->fetchAll();

$statusList = ['draft','pending','approved','ordered','partially_received','received','cancelled'];

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Purchase Orders</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Pending</div><div class="stat-value"><?= $statPending ?></div></div>
  <div class="stat"><div class="stat-label">Approved</div><div class="stat-value"><?= $statApproved ?></div></div>
  <div class="stat"><div class="stat-label">Received</div><div class="stat-value"><?= $statReceived ?></div></div>
</div>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search PO / Supplier..."
             value="<?= e($q) ?>" style="width:100%">
    </div>

    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">All Status</option>
      <?php foreach ($statusList as $st): ?>
        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>>
          <?= e(ucfirst(str_replace('_', ' ', $st))) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select class="select" name="supplier" onchange="this.form.submit()">
      <option value="">All Suppliers</option>
      <?php foreach ($suppliers as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $supplier == $s['id'] ? 'selected' : '' ?>>
          <?= e($s['supplier_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <?php if ($q || $status || $supplier): ?>
      <a class="btn" href="<?= base_url('admin/purchase-orders.php') ?>">Reset</a>
    <?php endif; ?>

    <button class="btn btn-primary" type="button" onclick="openModal('createPO')">➕ Create PO</button>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>PO No.</th>
          <th>Supplier</th>
          <th>Total</th>
          <th>Date</th>
          <th>Expected</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="7" class="empty">No purchase orders match your filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o):
        $cls = [
          'draft'              => 'badge-info',
          'pending'            => 'badge-pending',
          'approved'           => 'badge-info',
          'ordered'            => 'badge-info',
          'partially_received' => 'badge-pending',
          'received'           => 'badge-success',
          'cancelled'          => 'badge-danger',
        ][$o['status']] ?? 'badge-info';
      ?>
        <tr>
          <td><?= e($o['purchase_order_number']) ?></td>
          <td><?= e($o['supplier_name']) ?></td>
          <td><?= peso($o['subtotal']) ?></td>
          <td><?= e($o['order_date']) ?></td>
          <td><?= $o['expected_date'] ? e($o['expected_date']) : '—' ?></td>
          <td><span class="badge <?= $cls ?>"><?= e(ucfirst(str_replace('_', ' ', $o['status']))) ?></span></td>
          <td class="row-actions">
            <?php if ($o['status'] !== 'cancelled' && $o['status'] !== 'received'): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Cancel this PO?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="act" value="cancel">
                <input type="hidden" name="id" value="<?= $o['id'] ?>">
                <button class="btn-icon danger" title="Cancel">✕</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ MODAL: CREATE PO ============ -->
<div class="modal-overlay" id="createPO">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('createPO')">✕</button>
    <h3>Create Purchase Order</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="create">
      <div class="form-grid">
        <div class="field full"><label>Supplier <span class="req">*</span></label>
          <select name="supplier_id" required>
            <option value="">Select...</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field full"><label>Expected Delivery Date</label>
          <input type="date" name="expected_date"></div>
        <div class="field full"><label>Subtotal (₱)</label>
          <input type="number" step="0.01" name="subtotal" value="0"></div>
        <div class="field full"><label>Notes</label>
          <textarea name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('createPO')">Cancel</button>
        <button class="btn btn-primary">Create Purchase Order</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>