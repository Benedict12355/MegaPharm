<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'orders';
$page_title = 'Customer Orders';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $id = (int)$_POST['id'];
    $act = $_POST['act'] ?? '';
    if ($act === 'update_status') {
        $st = $_POST['status'];
        db()->prepare('UPDATE customer_orders SET status=?, processed_by=? WHERE id=?')->execute([$st, $u['id'], $id]);
        audit('Update', 'Orders', "Order #$id => $st");
        flash('success', 'Order status updated.');
    } elseif ($act === 'cancel') {
        db()->prepare("UPDATE customer_orders SET status='cancelled' WHERE id=?")->execute([$id]);
        audit('Cancel', 'Orders', "Order #$id cancelled");
        flash('success', 'Order cancelled.');
    }
    redirect(base_url('admin/orders.php'));
}

$stats = [
  'pending'  => (int)db()->query("SELECT COUNT(*) FROM customer_orders WHERE status='pending'")->fetchColumn(),
  'preparing'=> (int)db()->query("SELECT COUNT(*) FROM customer_orders WHERE status='preparing'")->fetchColumn(),
  'completed'=> (int)db()->query("SELECT COUNT(*) FROM customer_orders WHERE status='completed'")->fetchColumn(),
];
$orders = db()->query("SELECT o.*, c.full_name FROM customer_orders o JOIN customers c ON c.id=o.customer_id ORDER BY o.id DESC LIMIT 50")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Customer Orders</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Pending</div><div class="stat-value"><?= $stats['pending'] ?></div></div>
  <div class="stat"><div class="stat-label">Preparing</div><div class="stat-value"><?= $stats['preparing'] ?></div></div>
  <div class="stat"><div class="stat-label">Completed</div><div class="stat-value"><?= $stats['completed'] ?></div></div>
</div>

<div class="card">
  <div class="toolbar">
    <form class="search"><input class="input" placeholder="Search Order / Customer..."></form>
    <select class="select"><option>All Status</option></select>
    <input class="input" type="date">
  </div>
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>Ord No.</th><th>Customer</th><th>Total</th><th>Date</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$orders): ?><tr><td colspan="5" class="empty">No orders yet.</td></tr><?php endif; ?>
      <?php foreach ($orders as $o):
        $cls = ['pending'=>'badge-pending','completed'=>'badge-success','confirmed'=>'badge-info','preparing'=>'badge-pending','ready'=>'badge-info'][$o['status']] ?? 'badge-info';
      ?>
        <tr>
          <td><?= e($o['order_number']) ?></td>
          <td><?= e($o['full_name']) ?></td>
          <td><?= peso($o['total_amount']) ?></td>
          <td><?= e(date('Y-m-d', strtotime($o['order_date']))) ?></td>
          <td><span class="badge <?= $cls ?>"><?= e(ucfirst($o['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="toolbar" style="margin-top:14px">
    <button class="btn">View</button>
    <button class="btn">Update Status</button>
    <button class="btn">Cancel</button>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>