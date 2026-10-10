<?php
require_once __DIR__ . '/../includes/functions.php';

$sup_page = 'Purchase Orders';
$sup_active = 'purchase';
$supUser = $_SESSION['supplier'];

$supStmt = db()->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$supStmt->execute([$supUser['id']]);
$supplierId = (int)$supStmt->fetchColumn();

$status = $_GET['status'] ?? '';
$q      = trim($_GET['q'] ?? '');

$sql = "SELECT po.*, 
    (SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id=po.id) AS items
    FROM purchase_orders po WHERE po.supplier_id = ?";
$params = [$supplierId];

if ($status !== '') { $sql .= " AND po.status = ?"; $params[] = $status; }
if ($q !== '') {
    $sql .= " AND po.purchase_order_number LIKE ?";
    $params[] = "%$q%";
}
$sql .= " ORDER BY po.id DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">Purchase Orders</h1>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Invoice No. / Customer"
             value="<?= e($q) ?>" style="width:100%">
    </div>
    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">Status</option>
      <?php foreach (['pending','approved','ordered','partially_received','received','cancelled'] as $st): ?>
        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>>
          <?= e(ucfirst(str_replace('_', ' ', $st))) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php if ($q || $status): ?>
      <a class="btn" href="<?= base_url('supplier/purchase-orders.php') ?>">Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Purchase No.</th><th>Order Date</th><th>Items</th>
          <th>Amount</th><th>Delivery Date</th><th>Status</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="7" class="empty">No purchase orders assigned.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o):
        $cls = ['pending'=>'status-pending','approved'=>'status-processing','ordered'=>'status-processing',
                'partially_received'=>'status-processing','received'=>'status-delivered','cancelled'=>'status-cancelled'][$o['status']] ?? '';
      ?>
        <tr>
          <td><?= e($o['purchase_order_number']) ?></td>
          <td><?= e(date('M d, Y', strtotime($o['order_date']))) ?></td>
          <td><?= (int)$o['items'] ?></td>
          <td><?= peso($o['subtotal']) ?></td>
          <td><?= $o['expected_date'] ? e(date('M d, Y', strtotime($o['expected_date']))) : '—' ?></td>
          <td class="<?= $cls ?>"><?= e(ucfirst(str_replace('_', ' ', $o['status']))) ?></td>
          <td><a class="btn btn-sm" href="<?= base_url('supplier/po-details.php?id=' . $o['id']) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted" style="padding:12px 16px">Showing 1 to <?= count($orders) ?> of <?= count($orders) ?> orders</p>
</div>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>