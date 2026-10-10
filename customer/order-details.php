<?php
require_once __DIR__ . '/../includes/functions.php';

$custUser = $_SESSION['customer'] ?? null;
if (!$custUser) redirect(base_url('customer/login.php'));

$cust = db()->prepare('SELECT id FROM customers WHERE user_id = ?');
$cust->execute([$custUser['id']]);
$custId = (int)$cust->fetchColumn();

$orderId = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM customer_orders WHERE id = ? AND customer_id = ?');
$stmt->execute([$orderId, $custId]);
$order = $stmt->fetch();
if (!$order) { flash('error', 'Order not found.'); redirect(base_url('customer/orders.php')); }

$items = db()->prepare('SELECT oi.*, p.product_name FROM order_items oi
    JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$items->execute([$orderId]);
$items = $items->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_check($_POST['csrf'] ?? '');
    if (in_array($order['status'], ['pending', 'confirmed'])) {
        db()->prepare("UPDATE customer_orders SET status='cancelled' WHERE id=?")->execute([$orderId]);
        flash('success', 'Order cancelled.');
    } else { flash('error', 'Order cannot be cancelled at this stage.'); }
    redirect(base_url('customer/order-details.php?id=' . $orderId));
}

$cust_page = 'Order Details';
$cust_active = 'orders';
require_once __DIR__ . '/../customer-includes/header.php';
?>
<h1 style="display:none">Order Details</h1>
<h2 style="font-size:20px;margin:0 0 18px">Order Details</h2>

<div class="form-card">
  <h3 style="color:#1e40af;margin:0 0 4px"><?= e($order['order_number']) ?></h3>
  <p class="text-muted" style="margin:0;font-size:13px">Order Date: <?= e(date('F j, Y', strtotime($order['order_date']))) ?></p>
</div>

<div class="checkout-grid">
  <div>
    <div class="form-card">
      <h3>Delivery Information</h3>
      <p style="margin:4px 0"><b style="display:inline-block;min-width:150px">Name:</b> <?= e($custUser['full_name']) ?></p>
      <p style="margin:4px 0"><b style="display:inline-block;min-width:150px">Delivery Address:</b> <?= e($order['delivery_address'] ?? '—') ?></p>
    </div>
    <div class="form-card">
      <h3>Order Items</h3>
      <table class="cust-cart-table">
        <thead><tr><th>Products</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['product_name']) ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td><?= peso($it['unit_price']) ?></td>
            <td style="font-weight:700;color:#2563eb"><?= peso($it['line_total']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="form-card">
      <h3>Payment Information</h3>
      <p style="margin:4px 0"><b style="display:inline-block;min-width:150px">Payment Method:</b> <?= e(ucfirst(str_replace('_', ' ', $order['payment_method']))) ?></p>
      <p style="margin:4px 0"><b style="display:inline-block;min-width:150px">Payment Status:</b> <?= e(ucfirst($order['payment_status'])) ?></p>
    </div>
  </div>
  <div class="cust-summary-card">
    <h3>Order Summary</h3>
    <div class="summary-row"><span>Subtotal</span><span><?= peso($order['subtotal']) ?></span></div>
    <div class="summary-row"><span>Delivery Fee</span><span><?= peso($order['total_amount'] - $order['subtotal']) ?></span></div>
    <div class="summary-row total"><span>TOTAL</span><span><?= peso($order['total_amount']) ?></span></div>
    <div class="summary-actions">
      <a class="btn" href="<?= base_url('customer/orders.php') ?>">BACK TO MY ORDERS</a>
      <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
        <form method="post" onsubmit="return confirm('Cancel this order?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="cancel">
          <button class="btn" style="background:#dc2626;color:#fff;border-color:#dc2626;width:100%">CANCEL ORDER</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../customer-includes/footer.php'; ?>