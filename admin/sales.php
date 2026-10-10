<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'sales';
$page_title = 'Sales';

$sales = db()->query("SELECT s.*, c.full_name FROM sales s LEFT JOIN customers c ON c.id=s.customer_id ORDER BY s.id DESC LIMIT 50")->fetchAll();
$total = (int)db()->query('SELECT COUNT(*) FROM sales')->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Sales</h1>

<div class="card">
  <div class="toolbar">
    <form class="search"><input class="input" placeholder="Invoice No. / Customer"></form>
    <select class="select"><option>Payment</option></select>
    <input class="input" type="date">
  </div>
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>Invoice No.</th><th>Customer</th><th>Date</th><th>Total</th><th>Payment</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$sales): ?><tr><td colspan="6" class="empty">No sales recorded.</td></tr><?php endif; ?>
      <?php foreach ($sales as $s): ?>
        <tr>
          <td><?= e($s['sale_number']) ?></td>
          <td><?= e($s['full_name'] ?? 'Walk-in') ?></td>
          <td><?= e(date('F j, Y', strtotime($s['sale_date']))) ?></td>
          <td><?= peso($s['total_amount']) ?></td>
          <td><?= e(ucfirst(str_replace('_',' ', $s['payment_method']))) ?></td>
          <td><button class="btn btn-sm">View</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted" style="padding:12px 16px">Showing 1 to <?= count($sales) ?> of <?= $total ?> transactions</p>
</div>

<div class="card">
  <h3 class="card-title">Sales</h3>
  <canvas id="salesChart" height="80"></canvas>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('salesChart'), {
  type:'line',
  data:{labels:['January','February','March','April','May','June','July','August'],
        datasets:[{data:[5,12,28,30,32,28,20,45],borderColor:'#16a34a',backgroundColor:'rgba(22,163,74,.1)',tension:0.3,fill:true,pointRadius:3}]},
  options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>