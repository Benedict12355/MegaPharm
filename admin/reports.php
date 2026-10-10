<?php
// admin/reports.php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'reports';
$page_title = 'Reports';

$reportType = $_GET['type'] ?? 'sales';
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

/* ---------- SALES REPORT ---------- */
$salesRows = [];
$totalSales = 0; $totalTx = 0; $avg = 0;
if ($reportType === 'sales') {
    $stmt = db()->prepare("SELECT DATE(sale_date) d, COUNT(*) t, SUM(total_amount) amt
        FROM sales WHERE status='completed' AND DATE(sale_date) BETWEEN ? AND ?
        GROUP BY DATE(sale_date) ORDER BY d DESC");
    $stmt->execute([$from, $to]);
    $salesRows = $stmt->fetchAll();
    $totalSales = array_sum(array_column($salesRows, 'amt'));
    $totalTx    = array_sum(array_column($salesRows, 't'));
    $avg        = $totalTx ? $totalSales / $totalTx : 0;
}

/* ---------- INVENTORY REPORT ---------- */
$inventoryRows = [];
if ($reportType === 'inventory') {
    $stmt = db()->prepare("SELECT p.product_code, p.product_name, c.category_name,
            p.reorder_level, p.selling_price,
            (SELECT COALESCE(SUM(quantity),0) FROM inventory_batches WHERE product_id=p.id) AS stock
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status='active'
        ORDER BY p.product_name ASC
        LIMIT 500");
    $stmt->execute();
    $inventoryRows = $stmt->fetchAll();
}

/* ---------- EXPIRY REPORT ---------- */
$expiryRows = [];
if ($reportType === 'expiry') {
    $stmt = db()->prepare("SELECT p.product_code, p.product_name, c.category_name,
            b.batch_number, b.expiry_date, b.quantity
        FROM inventory_batches b
        JOIN products p ON p.id = b.product_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE b.quantity > 0 AND b.expiry_date IS NOT NULL
          AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
        ORDER BY b.expiry_date ASC
        LIMIT 500");
    $stmt->execute();
    $expiryRows = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Reports</h1>

<div class="card">
  <form method="get" class="toolbar">
    <label style="font-weight:600">Report Type:</label>
    <select class="select" name="type" onchange="this.form.submit()">
      <option value="sales"     <?= $reportType === 'sales'     ? 'selected' : '' ?>>Sales Report</option>
      <option value="inventory" <?= $reportType === 'inventory' ? 'selected' : '' ?>>Inventory Report</option>
      <option value="expiry"    <?= $reportType === 'expiry'    ? 'selected' : '' ?>>Expiry Report</option>
    </select>

    <input class="input" type="date" name="from" value="<?= e($from) ?>" onchange="this.form.submit()">
    <input class="input" type="date" name="to"   value="<?= e($to) ?>"   onchange="this.form.submit()">

    <button class="btn btn-primary">Generate Report</button>

    <a class="btn btn-primary" href="<?= base_url('admin/reports.php?' . http_build_query(['type'=>$reportType,'from'=>$from,'to'=>$to,'export'=>'pdf'])) ?>">Export PDF</a>
    <a class="btn btn-primary" href="<?= base_url('admin/reports.php?' . http_build_query(['type'=>$reportType,'from'=>$from,'to'=>$to,'export'=>'excel'])) ?>">Export Excel</a>
  </form>
</div>

<?php if ($reportType === 'sales'): ?>
  <div class="card">
    <h3 class="card-title">Sales Report Summary</h3>
    <p>Total Sales: <b><?= peso($totalSales) ?></b></p>
    <p>Total Transactions: <b><?= $totalTx ?></b></p>
    <p>Average Sale: <b><?= peso($avg) ?></b></p>
  </div>

  <div class="card">
    <div class="table-wrap" style="border:0">
      <table class="data">
        <thead><tr><th>Date</th><th>Transactions</th><th>Sales Amount</th></tr></thead>
        <tbody>
        <?php if (!$salesRows): ?>
          <tr><td colspan="3" class="empty">No sales in this period.</td></tr>
        <?php endif; ?>
        <?php foreach ($salesRows as $r): ?>
          <tr>
            <td><?= e(date('M d, Y', strtotime($r['d']))) ?></td>
            <td><?= (int)$r['t'] ?></td>
            <td><?= peso($r['amt']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($reportType === 'inventory'): ?>
  <div class="card">
    <h3 class="card-title">Inventory Report</h3>
    <div class="table-wrap" style="border:0">
      <table class="data">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Product</th>
            <th>Category</th>
            <th>Stock</th>
            <th>Reorder</th>
            <th>Status</th>
            <th>Price</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($inventoryRows as $r):
          $st = stock_status((int)$r['stock'], (int)$r['reorder_level']);
        ?>
          <tr>
            <td><?= e($r['product_code']) ?></td>
            <td><?= e($r['product_name']) ?></td>
            <td><?= e($r['category_name'] ?? '—') ?></td>
            <td><?= (int)$r['stock'] ?></td>
            <td><?= (int)$r['reorder_level'] ?></td>
            <td class="<?= $st === 'normal' ? 'status-ok' : 'status-low' ?>">
              <?= $st === 'low' ? 'Low' : ($st === 'out' ? 'Out' : 'Normal') ?>
            </td>
            <td><?= peso($r['selling_price']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$inventoryRows): ?>
          <tr><td colspan="7" class="empty">No products.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($reportType === 'expiry'): ?>
  <div class="card">
    <h3 class="card-title">Expiry Report (next 90 days)</h3>
    <div class="table-wrap" style="border:0">
      <table class="data">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Product</th>
            <th>Category</th>
            <th>Batch</th>
            <th>Expiry</th>
            <th>Qty</th>
            <th>Days Left</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($expiryRows as $r):
          $daysLeft = (int)((strtotime($r['expiry_date']) - time()) / 86400);
        ?>
          <tr>
            <td><?= e($r['product_code']) ?></td>
            <td><?= e($r['product_name']) ?></td>
            <td><?= e($r['category_name'] ?? '—') ?></td>
            <td><?= e($r['batch_number']) ?></td>
            <td><?= e($r['expiry_date']) ?></td>
            <td><?= (int)$r['quantity'] ?></td>
            <td class="<?= $daysLeft < 0 ? 'status-low' : ($daysLeft <= 30 ? 'status-pending' : 'status-ok') ?>">
              <?= $daysLeft < 0 ? abs($daysLeft) . 'd ago' : $daysLeft . 'd' ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$expiryRows): ?>
          <tr><td colspan="7" class="empty">No near-expiry batches.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>