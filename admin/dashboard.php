<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/header.php';
require_login();
$active = 'dashboard';
$page_title = 'Dashboard';

$isStaff = is_staff();

/* ---------- KPIs ---------- */
$totalSales   = (float)db()->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE status='completed'")->fetchColumn();
$pendingOrders = (int)db()->query("SELECT COUNT(*) FROM customer_orders WHERE status IN ('pending','confirmed','preparing','ready')")->fetchColumn();

$lowStock = (int)db()->query("SELECT COUNT(*) FROM (
    SELECT p.id, COALESCE(SUM(b.quantity),0) AS qty, p.reorder_level
    FROM products p LEFT JOIN inventory_batches b ON b.product_id = p.id
    WHERE p.status='active' GROUP BY p.id HAVING qty <= p.reorder_level
) t")->fetchColumn();

$expiring = (int)db()->query("SELECT COUNT(*) FROM inventory_batches b
    JOIN products p ON p.id = b.product_id
    WHERE b.quantity > 0 AND b.expiry_date IS NOT NULL
      AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

/* ---------- Recent orders ---------- */
$recent = db()->query("SELECT o.id, o.order_number, c.full_name, o.total_amount, o.status, o.order_date
    FROM customer_orders o JOIN customers c ON c.id = o.customer_id
    ORDER BY o.id DESC LIMIT 5")->fetchAll();

/* ---------- Low stock rows (top 5) ---------- */
$lowStockRows = db()->query("SELECT p.id, p.product_name, p.reorder_level,
        COALESCE((SELECT SUM(quantity) FROM inventory_batches WHERE product_id = p.id), 0) AS stock
    FROM products p
    WHERE p.status='active'
    HAVING stock <= p.reorder_level
    ORDER BY stock ASC LIMIT 5")->fetchAll();

/* ---------- Expiring rows (top 5) ---------- */
$expiringRows = db()->query("SELECT p.product_name, b.batch_number, b.expiry_date, b.quantity
    FROM inventory_batches b JOIN products p ON p.id = b.product_id
    WHERE b.quantity > 0 AND b.expiry_date IS NOT NULL
      AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY b.expiry_date ASC LIMIT 5")->fetchAll();

/* ---------- Chart (last 8 months) ---------- */
$chartRows = db()->query("SELECT DATE_FORMAT(sale_date,'%b') ym, SUM(total_amount) t
    FROM sales
    WHERE status='completed' AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 8 MONTH)
    GROUP BY YEAR(sale_date), MONTH(sale_date)
    ORDER BY YEAR(sale_date), MONTH(sale_date)")->fetchAll();

$chartLabels = array_column($chartRows, 'ym');
$chartData   = array_map(fn($r) => (float)$r['t'], $chartRows);
?>
<h1 style="display:none">Dashboard</h1>

<div class="welcome-banner">
  <h2>Welcome back, <?= e(ucfirst($u['role'])) ?>!</h2>
  <p>Here's what's happening in your pharmacy today.</p>
</div>

<?php if ($isStaff): ?>
  <!-- ============ STAFF DASHBOARD ============ -->
  <div class="stats">
    <div class="stat">
      <div class="stat-label">Total Sales</div>
      <div class="stat-value"><?= peso($totalSales) ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Pending Orders</div>
      <div class="stat-value"><?= $pendingOrders ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Low Stock</div>
      <div class="stat-value" style="color:var(--red)"><?= $lowStock ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Expiring</div>
      <div class="stat-value" style="color:var(--amber)"><?= $expiring ?></div>
    </div>
  </div>

  <div class="card">
    <h3 class="card-title" style="display:flex;justify-content:space-between;align-items:center">
      Recent Customer Orders
      <a class="btn btn-primary btn-sm" href="<?= base_url('admin/orders.php') ?>">View All</a>
    </h3>
    <div class="table-wrap" style="border:0">
      <table class="data">
        <thead>
          <tr><th>Ord No.</th><th>Customer</th><th>Total</th><th>Date</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php if (!$recent): ?>
          <tr><td colspan="5" class="empty">No orders yet</td></tr>
        <?php endif; ?>
        <?php foreach ($recent as $r):
          $cls = ['pending'=>'badge-pending','completed'=>'badge-success','confirmed'=>'badge-info',
                  'preparing'=>'badge-pending','ready'=>'badge-info'][$r['status']] ?? 'badge-info';
        ?>
          <tr>
            <td><?= e($r['order_number']) ?></td>
            <td><?= e($r['full_name']) ?></td>
            <td><?= peso($r['total_amount']) ?></td>
            <td><?= e(date('Y-m-d', strtotime($r['order_date']))) ?></td>
            <td><span class="badge <?= $cls ?>"><?= e(ucfirst($r['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
    <div class="card">
      <h3 class="card-title" style="display:flex;justify-content:space-between;align-items:center">
        Low Stock Alerts
        <a class="btn btn-primary btn-sm" href="<?= base_url('admin/inventory.php') ?>">View Inventory</a>
      </h3>
      <?php if (!$lowStockRows): ?>
        <p class="text-muted">No low stock items.</p>
      <?php else: ?>
        <table class="data" style="border:0">
          <tbody>
          <?php foreach ($lowStockRows as $row): ?>
            <tr>
              <td><?= e($row['product_name']) ?></td>
              <td>Stock: <b><?= (int)$row['stock'] ?></b></td>
              <td class="text-muted">Reorder: <?= (int)$row['reorder_level'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 class="card-title" style="display:flex;justify-content:space-between;align-items:center">
        Expiry Alerts
        <a class="btn btn-primary btn-sm" href="<?= base_url('admin/expiry-alerts.php') ?>">View Expiry Alerts</a>
      </h3>
      <?php if (!$expiringRows): ?>
        <p class="text-muted">No expiring items.</p>
      <?php else: ?>
        <table class="data" style="border:0">
          <tbody>
          <?php foreach ($expiringRows as $row):
            $daysLeft = (int)((strtotime($row['expiry_date']) - time()) / 86400);
          ?>
            <tr>
              <td><?= e($row['product_name']) ?></td>
              <td>Expires: <b><?= e(date('M d', strtotime($row['expiry_date']))) ?></b></td>
              <td class="<?= $daysLeft <= 7 ? 'status-low' : 'status-pending' ?>">
                <?= $daysLeft < 0 ? abs($daysLeft).'d ago' : $daysLeft.' days left' ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>
  <!-- ============ ADMIN DASHBOARD ============ -->
  <div class="stats">
    <div class="stat"><div class="stat-label">Total Sales</div><div class="stat-value"><?= peso($totalSales) ?></div></div>
    <div class="stat"><div class="stat-label">Pending Orders</div><div class="stat-value"><?= $pendingOrders ?></div></div>
    <div class="stat"><div class="stat-label">Low Stock</div><div class="stat-value" style="color:var(--red)"><?= $lowStock ?></div></div>
    <div class="stat"><div class="stat-label">Expiring</div><div class="stat-value" style="color:var(--amber)"><?= $expiring ?></div></div>
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
      <h3 class="card-title" style="display:flex;justify-content:space-between">
        Recent Customer Orders
        <a href="<?= base_url('admin/orders.php') ?>" style="font-size:12px">View All</a>
      </h3>
      <div class="table-wrap" style="border:0">
        <table class="data">
          <thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
          <?php if (!$recent): ?><tr><td colspan="4" class="empty">No orders yet</td></tr><?php endif; ?>
          <?php foreach ($recent as $r):
            $cls = ['pending'=>'badge-pending','completed'=>'badge-success','confirmed'=>'badge-info',
                    'preparing'=>'badge-pending','ready'=>'badge-info'][$r['status']] ?? 'badge-info';
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
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
  </script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>