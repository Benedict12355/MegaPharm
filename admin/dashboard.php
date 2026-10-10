<?php
require_once __DIR__ . '/../includes/header.php';
$active = 'dashboard';
$page_title = 'Dashboard';

// Stats
$totalProducts = (int)db()->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$lowStockCount = (int)db()->query("SELECT COUNT(*) FROM (
    SELECT p.id, COALESCE(SUM(b.quantity),0) AS qty, p.reorder_level
    FROM products p LEFT JOIN inventory_batches b ON b.product_id = p.id
    WHERE p.status='active' GROUP BY p.id HAVING qty <= p.reorder_level
) t")->fetchColumn();
$pendingOrders = (int)db()->query("SELECT COUNT(*) FROM customer_orders WHERE status='pending'")->fetchColumn();
$totalSales = (float)db()->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE status='completed'")->fetchColumn();

$recent = db()->query("SELECT o.id, o.order_number, c.full_name, o.total_amount, o.status
    FROM customer_orders o JOIN customers c ON c.id=o.customer_id
    ORDER BY o.id DESC LIMIT 5")->fetchAll();

// Sales chart data (last 8 months)
$rows = db()->query("SELECT DATE_FORMAT(sale_date,'%Y-%m') ym, SUM(total_amount) t
    FROM sales WHERE status='completed' AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 8 MONTH)
    GROUP BY ym ORDER BY ym")->fetchAll();
$chartLabels = array_column($rows, 'ym');
$chartData   = array_map(fn($r)=>(float)$r['t'], $rows);
?>
<h1 style="display:none">Dashboard</h1>

<div class="welcome-banner">
  <h2>Welcome back, <?= e($u['full_name']) ?>!</h2>
  <p>Here's what's happening in your pharmacy today.</p>
</div>

<div class="stats">
  <div class="stat"><div class="stat-label">Total Products</div><div class="stat-value"><?= number_format($totalProducts) ?></div><div class="stat-foot">All Categories</div></div>
  <div class="stat"><div class="stat-label">Low Stock</div><div class="stat-value" style="color:var(--red)"><?= $lowStockCount ?></div><div class="stat-foot" style="color:var(--red)">Needs attention</div></div>
  <div class="stat"><div class="stat-label">Pending Orders</div><div class="stat-value"><?= $pendingOrders ?></div><div class="stat-foot">For processing</div></div>
  <div class="stat"><div class="stat-label">Total Sales</div><div class="stat-value"><?= peso($totalSales) ?></div><div class="stat-foot">Current period</div></div>
</div>

<div class="card">
  <h3 class="card-title">Sales Overview</h3>
  <canvas id="salesChart" height="90"></canvas>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
  <div class="card">
    <h3 class="card-title">Quick Actions</h3>
    <div class="quick-actions">
      <a class="quick-action" href="<?= base_url('admin/products.php?new=1') ?>"><span class="qa-icon">➕</span>Add Product</a>
      <a class="quick-action" href="<?= base_url('admin/inventory.php?stockin=1') ?>"><span class="qa-icon">📦</span>Stock In</a>
      <a class="quick-action" href="<?= base_url('admin/purchase-orders.php?new=1') ?>"><span class="qa-icon">📄</span>Purchase Order</a>
      <a class="quick-action" href="<?= base_url('admin/users.php?new=1') ?>"><span class="qa-icon">👤</span>Add User</a>
      <a class="quick-action" href="<?= base_url('admin/reports.php') ?>"><span class="qa-icon">📊</span>View Reports</a>
      <a class="quick-action" href="<?= base_url('admin/deliveries.php') ?>"><span class="qa-icon">🚚</span>Deliveries</a>
    </div>
  </div>
  <div class="card">
    <h3 class="card-title" style="display:flex;justify-content:space-between">Recent Customer Orders <a href="<?= base_url('admin/orders.php') ?>" style="font-size:12px">View All</a></h3>
    <div class="table-wrap" style="border:0">
      <table class="data">
        <thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$recent): ?><tr><td colspan="4" class="empty">No orders yet</td></tr><?php endif; ?>
        <?php foreach ($recent as $r): 
          $cls = ['pending'=>'badge-pending','completed'=>'badge-success','confirmed'=>'badge-info','preparing'=>'badge-pending'][$r['status']] ?? 'badge-info';
        ?>
          <tr>
            <td>#<?= e($r['order_number']) ?></td>
            <td><?= e($r['full_name']) ?></td>
            <td><?= peso($r['total_amount']) ?></td>
            <td><span class="badge <?= $cls ?>"><?= e(ucfirst($r['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
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
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>