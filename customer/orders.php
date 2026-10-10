<?php
require_once __DIR__ . '/../includes/functions.php';

$custUser = $_SESSION['customer'] ?? null;
if (!$custUser) redirect(base_url('customer/login.php'));

$cust = db()->prepare('SELECT id FROM customers WHERE user_id = ?');
$cust->execute([$custUser['id']]);
$custId = (int)$cust->fetchColumn();

$orders = [];
if ($custId) {
    $stmt = db()->prepare("SELECT * FROM customer_orders WHERE customer_id = ? ORDER BY id DESC LIMIT 50");
    $stmt->execute([$custId]);
    $orders = $stmt->fetchAll();
}

$cust_page = 'My Orders';
$cust_active = 'orders';
require_once __DIR__ . '/../customer-includes/header.php';
?>
<h1 style="display:none">My Orders</h1>
<h2 style="font-size:20px;margin:0 0 18px">My Orders</h2>

<div class="cust-orders-card">
  <table>
    <thead><tr><th>Ord No.</th><th>Date</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php if (!$orders): ?>
      <tr><td colspan="5" style="text-align:center;padding:40px" class="text-muted">No orders yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td><?= e($o['order_number']) ?></td>
        <td><?= e(date('F j, Y', strtotime($o['order_date']))) ?></td>
        <td style="font-weight:700"><?= peso($o['total_amount']) ?></td>
        <td><span class="cust-status status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
        <td><a class="btn-sm-blue" href="<?= base_url('customer/order-details.php?id=' . $o['id']) ?>">View Details</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../customer-includes/footer.php'; ?>