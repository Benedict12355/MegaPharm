<?php
// admin/orders.php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'orders';
$page_title = 'Customer Orders';

/* ---------- POST ACTIONS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $id  = (int)$_POST['id'];
    $act = $_POST['act'] ?? '';

    if ($act === 'update_status') {
        $st = $_POST['status'];
        db()->prepare('UPDATE customer_orders SET status=?, processed_by=? WHERE id=?')
           ->execute([$st, $u['id'], $id]);
        audit('Update', 'Orders', "Order #$id => $st");
        flash('success', 'Order status updated.');
    } elseif ($act === 'cancel') {
        db()->prepare("UPDATE customer_orders SET status='cancelled' WHERE id=?")->execute([$id]);
        audit('Cancel', 'Orders', "Order #$id cancelled");
        flash('success', 'Order cancelled.');
    }
    redirect(base_url('admin/orders.php' . qs()));
}

/* ---------- FILTERS ---------- */
$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';

$sql = "SELECT o.*, c.full_name, c.phone
        FROM customer_orders o
        JOIN customers c ON c.id = o.customer_id
        WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (o.order_number LIKE ? OR c.full_name LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like);
}
if ($status !== '') {
    $sql .= " AND o.status = ?";
    $params[] = $status;
}
if ($from !== '') {
    $sql .= " AND DATE(o.order_date) >= ?";
    $params[] = $from;
}
if ($to !== '') {
    $sql .= " AND DATE(o.order_date) <= ?";
    $params[] = $to;
}
$sql .= " ORDER BY o.id DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$stats = [
  'pending'   => (int)db()->query("SELECT COUNT(*) FROM customer_orders WHERE status='pending'")->fetchColumn(),
  'preparing' => (int)db()->query("SELECT COUNT(*) FROM customer_orders WHERE status='preparing'")->fetchColumn(),
  'completed' => (int)db()->query("SELECT COUNT(*) FROM customer_orders WHERE status='completed'")->fetchColumn(),
];

$statusList = ['pending','confirmed','preparing','ready','completed','cancelled','rejected'];

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Customer Orders</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Pending</div><div class="stat-value"><?= $stats['pending'] ?></div></div>
  <div class="stat"><div class="stat-label">Preparing</div><div class="stat-value"><?= $stats['preparing'] ?></div></div>
  <div class="stat"><div class="stat-label">Completed</div><div class="stat-value"><?= $stats['completed'] ?></div></div>
</div>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search Order / Customer..."
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

    <input class="input" type="date" name="from" value="<?= e($from) ?>" onchange="this.form.submit()">
    <input class="input" type="date" name="to"   value="<?= e($to) ?>"   onchange="this.form.submit()">

    <?php if ($q || $status || $from || $to): ?>
      <a class="btn" href="<?= base_url('admin/orders.php') ?>">Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr><th>Ord No.</th><th>Customer</th><th>Total</th><th>Date</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="6" class="empty">No orders match your filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o):
        $cls = [
          'pending'   => 'badge-pending',
          'confirmed' => 'badge-info',
          'preparing' => 'badge-pending',
          'ready'     => 'badge-info',
          'completed' => 'badge-success',
          'cancelled' => 'badge-danger',
          'rejected'  => 'badge-danger',
        ][$o['status']] ?? 'badge-info';
      ?>
        <tr>
          <td><?= e($o['order_number']) ?></td>
          <td><?= e($o['full_name']) ?></td>
          <td><?= peso($o['total_amount']) ?></td>
          <td><?= e(date('Y-m-d', strtotime($o['order_date']))) ?></td>
          <td><span class="badge <?= $cls ?>"><?= e(ucfirst($o['status'])) ?></span></td>
          <td class="row-actions">
            <button class="btn btn-sm" onclick='viewOrder(<?= json_encode($o, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>View</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ MODAL: ORDER DETAILS ============ -->
<div class="modal-overlay" id="viewOrderModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('viewOrderModal')">✕</button>
    <h3>Order Details</h3>
    <div id="orderBody"></div>

    <form method="post" style="margin-top:18px">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="update_status">
      <input type="hidden" name="id" id="ov-id">
      <div class="form-grid">
        <div class="field full">
          <label>Update Status</label>
          <select name="status" id="ov-status" required>
            <?php foreach ($statusList as $st): ?>
              <option value="<?= $st ?>"><?= e(ucfirst($st)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('viewOrderModal')">Close</button>
        <button class="btn btn-primary">Update Status</button>
      </div>
    </form>
  </div>
</div>

<script>
function viewOrder(o) {
  document.getElementById('ov-id').value     = o.id;
  document.getElementById('ov-status').value = o.status;

  document.getElementById('orderBody').innerHTML = `
    <p><b>Order No.:</b> ${o.order_number}</p>
    <p><b>Customer:</b> ${o.full_name}</p>
    <p><b>Contact:</b> ${o.phone || '—'}</p>
    <p><b>Order Date:</b> ${new Date(o.order_date).toLocaleString()}</p>
    <p><b>Status:</b> ${o.status}</p>
    <p><b>Payment:</b> ${o.payment_method || '—'} (${o.payment_status})</p>
    <p><b>Total:</b> ₱${parseFloat(o.total_amount).toFixed(2)}</p>
    <p><b>Delivery Address:</b> ${o.delivery_address || '—'}</p>
    <p><b>Customer Notes:</b> ${o.customer_notes || '—'}</p>
  `;
  openModal('viewOrderModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>