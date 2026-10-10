<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'deliveries';
$page_title = 'Deliveries';

$rows = db()->query("SELECT d.*, po.purchase_order_number, s.supplier_name
    FROM deliveries d
    JOIN purchase_orders po ON po.id = d.purchase_order_id
    JOIN suppliers s ON s.id = po.supplier_id
    ORDER BY d.id DESC LIMIT 50")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Deliveries</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Total</div><div class="stat-value"><?= count($rows) ?></div></div>
  <div class="stat"><div class="stat-label">Pending</div><div class="stat-value"><?= (int)db()->query("SELECT COUNT(*) FROM deliveries WHERE status='pending'")->fetchColumn() ?></div></div>
  <div class="stat"><div class="stat-label">Delivered</div><div class="stat-value"><?= (int)db()->query("SELECT COUNT(*) FROM deliveries WHERE status='delivered'")->fetchColumn() ?></div></div>
  <div class="stat"><div class="stat-label">Completed</div><div class="stat-value"><?= (int)db()->query("SELECT COUNT(*) FROM deliveries WHERE status='completed'")->fetchColumn() ?></div></div>
</div>

<div class="card">
  <div class="toolbar">
    <form class="search"><input class="input" placeholder="Search..."></form>
    <select class="select"><option>All Status</option></select>
    <select class="select"><option>Supplier</option></select>
  </div>
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>Delivery No.</th><th>PO No.</th><th>Supplier</th><th>Date</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['delivery_number']) ?></td>
          <td><?= e($r['purchase_order_number']) ?></td>
          <td><?= e($r['supplier_name']) ?></td>
          <td><?= e($r['delivery_date']) ?></td>
          <td><span class="badge badge-info"><?= e(ucfirst($r['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="empty">No deliveries yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="toolbar" style="margin-top:14px"><button class="btn">View</button></div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>