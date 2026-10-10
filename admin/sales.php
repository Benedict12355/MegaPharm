<?php
// admin/sales.php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'sales';
$page_title = 'Sales';

/* ---------- FILTERS ---------- */
$q       = trim($_GET['q'] ?? '');
$payment = $_GET['payment'] ?? '';
$status  = $_GET['status'] ?? '';
$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';

$sql = "SELECT s.*, c.full_name, u.full_name AS cashier_name
        FROM sales s
        LEFT JOIN customers c ON c.id = s.customer_id
        LEFT JOIN users u     ON u.id = s.cashier_id
        WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (s.sale_number LIKE ? OR c.full_name LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like);
}
if ($payment !== '') {
    $sql .= " AND s.payment_method = ?";
    $params[] = $payment;
}
if ($status !== '') {
    $sql .= " AND s.status = ?";
    $params[] = $status;
}
if ($from !== '') { $sql .= " AND DATE(s.sale_date) >= ?"; $params[] = $from; }
if ($to   !== '') { $sql .= " AND DATE(s.sale_date) <= ?"; $params[] = $to; }

$sql .= " ORDER BY s.id DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$totalCount = (int)db()->query("SELECT COUNT(*) FROM sales")->fetchColumn();

/* ---------- CHART DATA (last 8 months) ---------- */
$chartRows = db()->query("SELECT DATE_FORMAT(sale_date,'%b') ym, SUM(total_amount) t
    FROM sales
    WHERE status='completed' AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 8 MONTH)
    GROUP BY YEAR(sale_date), MONTH(sale_date)
    ORDER BY YEAR(sale_date), MONTH(sale_date)")->fetchAll();

$chartLabels = array_column($chartRows, 'ym');
$chartData   = array_map(fn($r) => (float)$r['t'], $chartRows);

$paymentMethods = ['cash','gcash','bank_transfer','card','cash_on_delivery'];
$statusList     = ['completed','voided','refunded','partially_refunded'];

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Sales</h1>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Invoice No. / Customer"
             value="<?= e($q) ?>" style="width:100%">
    </div>

    <select class="select" name="payment" onchange="this.form.submit()">
      <option value="">All Payments</option>
      <?php foreach ($paymentMethods as $pm): ?>
        <option value="<?= $pm ?>" <?= $payment === $pm ? 'selected' : '' ?>>
          <?= e(ucfirst(str_replace('_', ' ', $pm))) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">All Status</option>
      <?php foreach ($statusList as $st): ?>
        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>>
          <?= e(ucfirst(str_replace('_', ' ', $st))) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input class="input" type="date" name="from" value="<?= e($from) ?>" onchange="this.form.submit()">
    <input class="input" type="date" name="to"   value="<?= e($to) ?>"   onchange="this.form.submit()">

    <?php if ($q || $payment || $status || $from || $to): ?>
      <a class="btn" href="<?= base_url('admin/sales.php') ?>">Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Invoice No.</th>
          <th>Customer</th>
          <th>Date</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$sales): ?>
        <tr><td colspan="7" class="empty">No sales match your filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($sales as $s):
        $cls = [
          'completed'          => 'badge-success',
          'voided'             => 'badge-danger',
          'refunded'           => 'badge-danger',
          'partially_refunded' => 'badge-pending',
        ][$s['status']] ?? 'badge-info';
      ?>
        <tr>
          <td><?= e($s['sale_number']) ?></td>
          <td><?= e($s['full_name'] ?? 'Walk-in') ?></td>
          <td><?= e(date('M j, Y', strtotime($s['sale_date']))) ?></td>
          <td><?= peso($s['total_amount']) ?></td>
          <td><?= e(ucfirst(str_replace('_', ' ', $s['payment_method']))) ?></td>
          <td><span class="badge <?= $cls ?>"><?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?></span></td>
          <td><button class="btn btn-sm" onclick='viewSale(<?= json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>View</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted" style="padding:12px 16px">
    Showing <?= count($sales) ?> of <?= $totalCount ?> transactions
  </p>
</div>

<div class="card">
  <h3 class="card-title">Sales Trend</h3>
  <canvas id="salesChart" height="90"></canvas>
</div>

<!-- ============ MODAL: SALE DETAILS ============ -->
<div class="modal-overlay" id="viewSaleModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('viewSaleModal')">✕</button>
    <h3>Sale Details</h3>
    <div id="saleBody"></div>
    <div class="modal-actions">
      <button class="btn btn-light" onclick="window.print()">Print Receipt</button>
      <button class="btn btn-primary" onclick="closeModal('viewSaleModal')">Close</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('salesChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Sales',
      data: <?= json_encode($chartData) ?>,
      borderColor: '#16a34a',
      backgroundColor: 'rgba(22,163,74,.1)',
      tension: 0.3,
      fill: true,
      pointRadius: 3
    }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

function viewSale(s) {
  document.getElementById('saleBody').innerHTML = `
    <p><b>Invoice No.:</b> ${s.sale_number}</p>
    <p><b>Customer:</b> ${s.full_name || 'Walk-in'}</p>
    <p><b>Date:</b> ${new Date(s.sale_date).toLocaleString()}</p>
    <p><b>Processed By:</b> ${s.cashier_name || 'Staff'}</p>
    <p><b>Payment:</b> ${s.payment_method}</p>
    <hr>
    <p><b>Subtotal:</b> ₱${parseFloat(s.subtotal).toFixed(2)}</p>
    <p><b>Discount:</b> ₱${parseFloat(s.discount_amount).toFixed(2)}</p>
    <p><b>TOTAL:</b> <b>₱${parseFloat(s.total_amount).toFixed(2)}</b></p>
    <p><b>Amount Paid:</b> ₱${parseFloat(s.amount_paid).toFixed(2)}</p>
    <p><b>Change:</b> ₱${parseFloat(s.change_amount).toFixed(2)}</p>
  `;
  openModal('viewSaleModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>