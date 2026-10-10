<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'customers';
$page_title = 'Customers';

$customers = db()->query("SELECT c.*, 
    (SELECT COUNT(*) FROM customer_orders WHERE customer_id=c.id) total_orders
    FROM customers c ORDER BY c.id DESC LIMIT 100")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Customers</h1>

<div class="card">
  <div class="toolbar">
    <form class="search"><input class="input" placeholder="Search..."></form>
    <select class="select"><option>All Status</option></select>
  </div>
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>Customer</th><th>Email</th><th>Phone No.</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td><?= e($c['full_name']) ?></td>
          <td><?= e($c['email'] ?? '—') ?></td>
          <td><?= e($c['phone'] ?? '—') ?></td>
          <td><?= (int)$c['total_orders'] ?></td>
          <td><span class="badge badge-success">Active</span></td>
          <td><button class="btn btn-sm">View</button></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?><tr><td colspan="6" class="empty">No customers yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>