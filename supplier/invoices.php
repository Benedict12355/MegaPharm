<?php
require_once __DIR__ . '/../includes/functions.php';

$sup_page = 'Invoices';
$sup_active = 'invoices';
$supUser = $_SESSION['supplier'];

$supStmt = db()->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$supStmt->execute([$supUser['id']]);
$supplierId = (int)$supStmt->fetchColumn();

$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

/*
 * Invoices are synthetic — derived from purchase_orders for this supplier.
 * One PO => one invoice.
 */
$sql = "SELECT po.id AS po_id,
               CONCAT('INV-', YEAR(po.order_date), '-', LPAD(po.id, 3, '0')) AS invoice_no,
               po.purchase_order_number,
               po.order_date AS invoice_date,
               DATE_ADD(po.order_date, INTERVAL 15 DAY) AS due_date,
               po.subtotal AS amount,
               CASE
                 WHEN po.status = 'received' THEN 'paid'
                 WHEN po.status = 'cancelled' THEN 'overdue'
                 WHEN po.status IN ('approved','ordered','partially_received') THEN 'processing'
                 ELSE 'pending'
               END AS status
        FROM purchase_orders po
        WHERE po.supplier_id = ?";
$params = [$supplierId];

if ($q !== '') {
    $sql .= " AND po.purchase_order_number LIKE ?";
    $params[] = "%$q%";
}
$sql .= " ORDER BY po.id DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

if ($status !== '') {
    $invoices = array_values(array_filter($invoices, fn($i) => $i['status'] === $status));
}

$statusCls = [
    'pending'    => 'status-pending',
    'processing' => 'status-processing',
    'paid'       => 'status-paid',
    'overdue'    => 'status-overdue',
];

require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">Invoices</h1>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search invoice..."
             value="<?= e($q) ?>" style="width:100%">
    </div>
    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">Status</option>
      <?php foreach (['pending','processing','paid','overdue'] as $st): ?>
        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($q || $status): ?>
      <a class="btn" href="<?= base_url('supplier/invoices.php') ?>">Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Invoice No.</th><th>PO Number</th><th>Invoice Date</th>
          <th>Due Date</th><th>Amount</th><th>Status</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$invoices): ?>
        <tr><td colspan="7" class="empty">No invoices yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($invoices as $inv):
        $cls = $statusCls[$inv['status']] ?? '';
      ?>
        <tr>
          <td><?= e($inv['invoice_no']) ?></td>
          <td><?= e($inv['purchase_order_number']) ?></td>
          <td><?= e(date('M d, Y', strtotime($inv['invoice_date']))) ?></td>
          <td><?= e(date('M d, Y', strtotime($inv['due_date']))) ?></td>
          <td><?= peso($inv['amount']) ?></td>
          <td class="<?= $cls ?>"><?= e(ucfirst($inv['status'])) ?></td>
          <td><a class="btn btn-sm" href="<?= base_url('supplier/po-details.php?id=' . $inv['po_id']) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted" style="padding:12px 16px">
    Showing 1 to <?= count($invoices) ?> of <?= count($invoices) ?> invoice
  </p>
</div>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>