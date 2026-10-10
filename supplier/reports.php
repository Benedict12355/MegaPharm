<?php
require_once __DIR__ . '/../includes/functions.php';

$sup_page = 'Reports';
$sup_active = 'reports';
$supUser = $_SESSION['supplier'];

$supStmt = db()->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$supStmt->execute([$supUser['id']]);
$supplierId = (int)$supStmt->fetchColumn();

$type = $_GET['type'] ?? 'purchase';
$period = $_GET['period'] ?? date('Y-m');

// Purchase orders for this supplier
$stmt = db()->prepare("SELECT po.*,
    (SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id=po.id) AS items
    FROM purchase_orders po WHERE supplier_id=?
    ORDER BY po.id DESC LIMIT 50");
$stmt->execute([$supplierId]);
$orders = $stmt->fetchAll();

// Payments
$stmt = db()->prepare("SELECT po.id AS po_id,
    CONCAT('PAY-', YEAR(po.order_date), '-', LPAD(po.id,3,'0')) AS payment_id,
    po.purchase_order_number,
    CONCAT('INV-', YEAR(po.order_date), '-', LPAD(po.id,3,'0')) AS invoice_no,
    po.order_date AS invoice_date, po.subtotal AS amount,
    CASE
      WHEN po.status='received' THEN 'completed'
      WHEN po.status='cancelled' THEN 'pending'
      WHEN po.status IN ('approved','ordered','partially_received') THEN 'processing'
      ELSE 'pending'
    END AS status
    FROM purchase_orders po WHERE supplier_id=?
    ORDER BY po.id DESC LIMIT 10");
$stmt->execute([$supplierId]);
$payments = $stmt->fetchAll();

$statusCls = [
    'pending' => 'status-pending', 'approved' => 'status-processing', 'ordered' => 'status-processing',
    'partially_received' => 'status-processing', 'received' => 'status-delivered', 'cancelled' => 'status-cancelled',
    'completed' => 'status-paid', 'processing' => 'status-processing',
];

require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">Reports</h1>

<div class="card">
  <form method="get" class="toolbar">
    <label style="font-weight:600">Report Type:</label>
    <select class="select" name="type" onchange="this.form.submit()">
      <option value="purchase" <?= $type === 'purchase' ? 'selected' : '' ?>>Purchase Order</option>
      <option value="payment"  <?= $type === 'payment'  ? 'selected' : '' ?>>Payment</option>
    </select>
    <label style="font-weight:600;margin-left:auto">Period:</label>
    <select class="select" name="period" onchange="this.form.submit()">
      <option value="<?= date('Y-m') ?>"><?= date('F Y') ?></option>
      <option value="<?= date('Y-m', strtotime('-1 month')) ?>"><?= date('F Y', strtotime('-1 month')) ?></option>
      <option value="<?= date('Y-m', strtotime('-2 month')) ?>"><?= date('F Y', strtotime('-2 month')) ?></option>
    </select>
    <button class="btn btn-primary">Generate Report</button>
  </form>
</div>

<?php if ($type === 'purchase'): ?>
  <div class="card">
    <h3 class="card-title">Purchase Order Summary</h3>
    <div class="table-wrap" style="border:0">
      <table class="data">
        <thead>
          <tr>
            <th>Purchase No.</th><th>Order Date</th><th>Items</th>
            <th>Amount</th><th>Delivery Date</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): $cls = $statusCls[$o['status']] ?? ''; ?>
          <tr>
            <td><?= e($o['purchase_order_number']) ?></td>
            <td><?= e(date('M d, Y', strtotime($o['order_date']))) ?></td>
            <td><?= (int)$o['items'] ?></td>
            <td><?= peso($o['subtotal']) ?></td>
            <td><?= $o['expected_date'] ? e(date('M d, Y', strtotime($o['expected_date']))) : '—' ?></td>
            <td class="<?= $cls ?>"><?= e(ucfirst(str_replace('_',' ',$o['status']))) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
          <tr><td colspan="6" class="empty">No orders this period.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <h3 class="card-title">Payment Summary</h3>
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Payment ID</th><th>PO Number</th><th>Invoice Date</th>
          <th>Amount</th><th>Method</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($payments as $p): $cls = $statusCls[$p['status']] ?? ''; ?>
        <tr>
          <td><?= e($p['payment_id']) ?></td>
          <td><?= e($p['purchase_order_number']) ?></td>
          <td><?= e(date('M d, Y', strtotime($p['invoice_date']))) ?></td>
          <td><?= peso($p['amount']) ?></td>
          <td>Bank Transfer</td>
          <td class="<?= $cls ?>"><?= e(ucfirst($p['status'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?>
        <tr><td colspan="6" class="empty">No payments this period.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div style="display:flex;gap:12px;justify-content:flex-end;padding:16px">
    <button class="btn" onclick="window.print()">Print Report</button>
    <button class="btn btn-primary" onclick="alert('Export not yet implemented.')">Export Report</button>
  </div>
</div>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>