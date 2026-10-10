<?php
require_once __DIR__ . '/../includes/functions.php';

$custUser = $_SESSION['customer'] ?? null;
if (!$custUser) { flash('info', 'Please log in.'); redirect(base_url('customer/login.php')); }

$cartItems = [];
$subtotal = 0;
if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    foreach (db()->query("SELECT id, product_name, selling_price FROM products WHERE id IN ($ids)") as $r) {
        $qty = (int)($_SESSION['cart'][$r['id']]['qty'] ?? 1);
        $line = $qty * (float)$r['selling_price'];
        $subtotal += $line;
        $cartItems[] = ['p' => $r, 'qty' => $qty, 'line' => $line];
    }
}
$deliveryFee = $subtotal > 0 ? 50.00 : 0.00;
$total = $subtotal + $deliveryFee;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    if (empty($cartItems)) { flash('error', 'Your cart is empty.'); redirect(base_url('customer/cart.php')); }

    $address = trim(($_POST['house_no'] ?? '') . ' ' . ($_POST['street'] ?? '') . ', '
              . ($_POST['barangay'] ?? '') . ', ' . ($_POST['city'] ?? '') . ', '
              . ($_POST['province'] ?? ''));

    $cust = db()->prepare('SELECT id FROM customers WHERE user_id = ?');
    $cust->execute([$custUser['id']]);
    $custId = (int)$cust->fetchColumn();

    if (!$custId) {
        $code = next_number('customers', 'customer_code', 'CUST-');
        db()->prepare('INSERT INTO customers (user_id, customer_code, full_name, phone, email, address)
            VALUES (?,?,?,?,?,?)')
           ->execute([$custUser['id'], $code, $custUser['full_name'], $_POST['contact'] ?? '', $custUser['email'], $address]);
        $custId = (int)db()->lastInsertId();
    } else {
        db()->prepare('UPDATE customers SET phone=?, address=? WHERE id=?')
           ->execute([$_POST['contact'] ?? '', $address, $custId]);
    }

    $payMethod = $_POST['payment_method'] ?? 'cash_on_delivery';
    $orderNum = next_number('customer_orders', 'order_number', 'ORD-', 4);

    db()->prepare('INSERT INTO customer_orders 
        (order_number, customer_id, processed_by, order_date, status, payment_method, payment_status,
         subtotal, discount_amount, total_amount, delivery_address, customer_notes)
        VALUES (?,?,?,NOW(),?,?,?,?,?,?,?,?)')
       ->execute([
           $orderNum, $custId, null, 'pending', $payMethod, 'unpaid',
           $subtotal, 0, $total, $address, $_POST['notes'] ?? '',
       ]);
    $orderId = (int)db()->lastInsertId();

    foreach ($cartItems as $item) {
        db()->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price, line_total)
            VALUES (?,?,?,?,?)')
           ->execute([$orderId, $item['p']['id'], $item['qty'], $item['p']['selling_price'], $item['line']]);
    }

    notify_admins('customer_order', 'New Customer Order',
        "New order $orderNum has been placed by a customer.", 'order', $orderId);

    unset($_SESSION['cart']);
    flash('success', "Order $orderNum placed successfully!");
    redirect(base_url('customer/orders.php'));
}

$cust_page = 'Checkout';
$cust_active = 'cart';
require_once __DIR__ . '/../customer-includes/header.php';
?>
<h1 style="display:none">Checkout</h1>
<h2 style="font-size:20px;margin:0 0 18px">Checkout</h2>

<form method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <div class="checkout-grid">
    <div>
      <div class="form-card">
        <h3>Delivery Information</h3>
        <div class="form-grid">
          <div class="field"><label>House/Building No.</label><input name="house_no" required></div>
          <div class="field"><label>Street</label><input name="street" required></div>
          <div class="field"><label>Barangay</label><input name="barangay" required></div>
          <div class="field"><label>City/Municipality</label><input name="city" required></div>
          <div class="field full"><label>Province</label><input name="province" required></div>
          <div class="field full"><label>Contact Number</label><input name="contact" required></div>
        </div>
      </div>
      <div class="form-card">
        <h3>Payment Method</h3>
        <label class="pay-method active">
          <input type="radio" name="payment_method" value="cash_on_delivery" checked> Cash on Delivery
        </label>
        <label class="pay-method" style="margin-top:10px">
          <input type="radio" name="payment_method" value="gcash"> GCash
        </label>
      </div>
      <button class="btn btn-primary" style="width:100%;padding:14px;font-size:14px">PLACE ORDER</button>
      <a class="btn" style="width:100%;margin-top:8px;padding:12px;text-align:center;display:block" href="<?= base_url('customer/cart.php') ?>">BACK TO CART</a>
    </div>
    <div class="cust-summary-card">
      <h3>Order Summary</h3>
      <?php foreach ($cartItems as $item): ?>
        <div class="summary-row">
          <span><?= e($item['p']['product_name']) ?> × <?= $item['qty'] ?></span>
          <span><?= peso($item['line']) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="summary-row" style="border-top:1px solid #e5e9f2;margin-top:8px;padding-top:10px">
        <span>Subtotal</span><span><?= peso($subtotal) ?></span>
      </div>
      <div class="summary-row"><span>Delivery Fee</span><span><?= peso($deliveryFee) ?></span></div>
      <div class="summary-row total"><span>TOTAL</span><span><?= peso($total) ?></span></div>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/../customer-includes/footer.php'; ?>