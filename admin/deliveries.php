<?php
// admin/deliveries.php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'deliveries';
$page_title = 'Deliveries';

/* ---------- FILTERS ---------- */
$q        = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$supplier = $_GET['supplier'] ?? '';
$from     = $_GET['from'] ?? '';
$to       = $_GET['to'] ?? '';

$sql = "SELECT d.*, po.purchase_order_number, s.supplier_name, s.id AS supplier_id
        FROM deliveries d
        JOIN purchase_orders po ON po.id = d.purchase_order_id
        JOIN suppliers s ON s.id = po.supplier_id
        WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (d.delivery_number LIKE ? OR po.purchase_order_number LIKE ? OR s.supplier_name LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
if ($status !== '') {
    $sql .= " AND d.status = ?";
    $params[] = $status;
}
if ($supplier !== '' && ctype_digit((string)$supplier)) {
    $sql .= " AND s.id = ?";
    $params[] = (int)$supplier;
}
if ($from !== '') { $sql .= " AND d.delivery_date >= ?"; $params[] = $from; }
if ($to   !== '') { $sql .= " AND d.delivery_date <= ?"; $params[] = $to; }

$sql .= " ORDER BY d.id DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$statTotal     = (int)db()->query("SELECT COUNT(*) FROM deliveries")->fetchColumn();
$statPending   = (int)db()->query("SELECT COUNT(*) FROM deliveries WHERE status='pending'")->fetchColumn();
$statDelivered = (int)db()->query("SELECT COUNT(*) FROM deliveries WHERE status='delivered'")->fetchColumn();
$statCompleted = (int)db()->query("SELECT COUNT(*) FROM deliveries WHERE status='completed'")->fetchColumn();

$suppliers = db()->query('SELECT id, supplier_name FROM suppliers WHERE status="active" ORDER BY supplier_name')->fetchAll();
$statusList = ['pending','partial','delivered','completed','cancelled'];

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Deliveries</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Total</div><div class="stat-value"><?= $statTotal ?></div></div>
  <div class="stat"><div class="stat-label">Pending</div><div class="stat-value"><?= $statPending ?></div></div>
  <div class="stat"><div class="stat-label">Delivered</div><div class="stat-value"><?= $statDelivered ?></div></div>
  <div class="stat"><div class="stat-label">Completed</div><div class="stat-value"><?= $statCompleted ?></div></div>
</div>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search Delivery / PO / Supplier..."
             value="<?= e($q) ?>" style="width:100%">
    </div>

    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">All Status</option>
      <?php foreach ($statusList as $st): ?>
        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>>
          <?= e(ucfirst($st)) ?>
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

    <input class="input" type="date" name="from" value="<?= e($from) ?>" onchange="this.form.submit()">
    <input class="input" type="date" name="to"   value="<?= e($to) ?>"   onchange="this.form.submit()">

    <?php if ($q || $status || $supplier || $from || $to): ?>
      <a class="btn" href="<?= base_url('admin/deliveries.php') ?>">Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Delivery No.</th>
          <th>PO No.</th>
          <th>Supplier</th>
          <th>Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="empty">No deliveries match your filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r):
        $cls = [
          'pending'   => 'badge-pending',
          'partial'   => 'badge-pending',
          'delivered' => 'badge-info',
          'completed' => 'badge-success',
          'cancelled' => 'badge-danger',
        ][$r['status']] ?? 'badge-info';
      ?>
        <tr>
          <td><?= e($r['delivery_number']) ?></td>
          <td><?= e($r['purchase_order_number']) ?></td>
          <td><?= e($r['supplier_name']) ?></td>
          <td><?= e($r['delivery_date']) ?></td>
          <td><span class="badge <?= $cls ?>"><?= e(ucfirst($r['status'])) ?></span></td>
          <td class="row-actions">
            <button class="btn btn-sm" onclick='viewDelivery(<?= json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>View</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ MODAL: DELIVERY DETAILS ============ -->
<div class="modal-overlay" id="viewDeliveryModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('viewDeliveryModal')">✕</button>
    <h3>Delivery Details</h3>
    <div id="deliveryBody"></div>
    <div class="modal-actions">
      <button class="btn btn-light" onclick="window.print()">Print Delivery</button>
      <button class="btn btn-primary" onclick="closeModal('viewDeliveryModal')">Close</button>
    </div>
  </div>
</div>

<script>
function viewDelivery(d) {
  document.getElementById('deliveryBody').innerHTML = `
    <p><b>Delivery No.:</b> ${d.delivery_number}</p>
    <p><b>Purchase Order:</b> ${d.purchase_order_number}</p>
    <p><b>Supplier:</b> ${d.supplier_name}</p>
    <p><b>Delivery Date:</b> ${d.delivery_date}</p>
    <p><b>Status:</b> ${d.status}</p>
    <p><b>Notes:</b> ${d.notes || '—'}</p>
  `;
  openModal('viewDeliveryModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>