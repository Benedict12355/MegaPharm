<?php
require_once __DIR__ . '/../includes/functions.php';

$sup_page = 'Payments';
$sup_active = 'payments';
$supUser = $_SESSION['supplier'];

$supStmt = db()->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$supStmt->execute([$supUser['id']]);
$supplierId = (int)$supStmt->fetchColumn();

/* Payments derived from received POs */
$sql = "SELECT po.id AS po_id,
               CONCAT('PAY-', YEAR(po.order_date), '-', LPAD(po.id, 3, '0')) AS payment_id,
               po.purchase_order_number,
               CONCAT('INV-', YEAR(po.order_date), '-', LPAD(po.id, 3, '0')) AS invoice_no,
               po.order_date AS invoice_date,
               po.subtotal AS amount,
               CASE
                 WHEN po.status = 'received' THEN 'completed'
                 WHEN po.status = 'cancelled' THEN 'pending'
                 WHEN po.status IN ('approved','ordered','partially_received') THEN 'processing'
                 ELSE 'pending'
               END AS status
        FROM purchase_orders po
        WHERE po.supplier_id = ?
        ORDER BY po.id DESC LIMIT 200";
$stmt = db()->prepare($sql);
$stmt->execute([$supplierId]);
$rows = $stmt->fetchAll();

$totalPayments   = count($rows);
$pendingPayments = count(array_filter($rows, fn($r) => $r['status'] === 'pending'));
$completedPay    = count(array_filter($rows, fn($r) => $r['status'] === 'completed'));

$statusCls = [
    'pending'    => 'status-pending',
    'processing' => 'status-processing',
    'completed'  => 'status-paid',
];

require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">Payments</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Total Payments</div><div class="stat-value"><?= $totalPayments ?></div></div>
  <div class="stat"><div class="stat-label">Pending Payments</div><div class="stat-value"><?= $pendingPayments ?></div></div>
  <div class="stat"><div class="stat-label">Completed Payments</div><div class="stat-value"><?= $completedPay ?></div></div>
</div>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search payment..." style="width:100%">
    </div>
    <select class="select">
      <option>Status</option>
    </select>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Payment ID</th><th>PO Number</th><th>Invoice Date</th>
          <th>Amount</th><th>Method</th><th>Status</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="empty">No payments yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): $cls = $statusCls[$r['status']] ?? ''; ?>
        <tr>
          <td><?= e($r['payment_id']) ?></td>
          <td><?= e($r['purchase_order_number']) ?></td>
          <td><?= e(date('M d, Y', strtotime($r['invoice_date']))) ?></td>
          <td><?= peso($r['amount']) ?></td>
          <td>Bank Transfer</td>
          <td class="<?= $cls ?>"><?= e(ucfirst($r['status'])) ?></td>
          <td><a class="btn btn-sm" href="<?= base_url('supplier/po-details.php?id=' . $r['po_id']) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted" style="padding:12px 16px">
    Showing 1 to <?= count($rows) ?> of <?= count($rows) ?> payments
  </p>
</div>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>