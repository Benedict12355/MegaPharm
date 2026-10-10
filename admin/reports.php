<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'reports';
$page_title = 'Reports';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

$stmt = db()->prepare("SELECT DATE(sale_date) d, COUNT(*) t, SUM(total_amount) amt
    FROM sales WHERE status='completed' AND DATE(sale_date) BETWEEN ? AND ?
    GROUP BY DATE(sale_date) ORDER BY d DESC");
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll();
$totalSales = array_sum(array_column($rows, 'amt'));
$totalTx = array_sum(array_column($rows, 't'));
$avg = $totalTx ? $totalSales / $totalTx : 0;

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Reports</h1>

<div class="card">
  <div class="toolbar">
    <label>Report Type:</label>
    <select class="select"><option>Sales Report</option><option>Inventory Report</option><option>Expiry Report</option></select>
    <form method="get" style="display:flex;gap:8px;align-items:center;margin-left:auto">
      <input class="input" type="date" name="from" value="<?= e($from) ?>">
      <input class="input" type="date" name="to" value="<?= e($to) ?>">
      <button class="btn btn-primary">Generate Report</button>
    </form>
    <button class="btn btn-primary">Export PDF</button>
    <button class="btn btn-primary">Export Excel</button>
  </div>
</div>

<div class="card">
  <h3 class="card-title">Sales Report</h3>
  <p>Total Sales: <b><?= peso($totalSales) ?></b></p>
  <p>Total Transactions: <b><?= $totalTx ?></b></p>
  <p>Average Sale: <b><?= peso($avg) ?></b></p>
</div>

<div class="card">
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>Date</th><th>Transactions</th><th>Sales Amount</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td><?= e(date('M d, Y', strtotime($r['d']))) ?></td><td><?= (int)$r['t'] ?></td><td><?= peso($r['amt']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="3" class="empty">No data for this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>