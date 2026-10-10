<?php
require_once __DIR__ . '/../includes/functions.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add') {
    $pid = (int)$_POST['product_id'];
    $qty = max(1, (int)$_POST['qty']);
    $_SESSION['cart'][$pid] = ['qty' => ($_SESSION['cart'][$pid]['qty'] ?? 0) + $qty];
    flash('success', 'Added to cart.');
    redirect(base_url('customer/cart.php'));
} elseif ($action === 'update') {
    foreach ($_POST['qty'] ?? [] as $pid => $q) {
        $pid = (int)$pid; $q = (int)$q;
        if ($q <= 0) unset($_SESSION['cart'][$pid]);
        else $_SESSION['cart'][$pid] = ['qty' => $q];
    }
    redirect(base_url('customer/cart.php'));
} elseif ($action === 'remove') {
    unset($_SESSION['cart'][(int)$_GET['pid']]);
    redirect(base_url('customer/cart.php'));
}

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

$cust_page = 'Shopping Cart';
$cust_active = 'cart';
require_once __DIR__ . '/../customer-includes/header.php';
?>
<h1 style="display:none">Shopping Cart</h1>
<h2 style="font-size:20px;margin:0 0 18px">Shopping Cart</h2>

<form method="post" action="<?= base_url('customer/cart.php') ?>">
  <input type="hidden" name="action" value="update">
  <div class="cust-cart-wrap">
    <div class="cust-cart-card">
      <table class="cust-cart-table">
        <thead><tr><th>Product</th><th>Price</th><th>Quantity</th><th>Subtotal</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (!$cartItems): ?>
          <tr><td colspan="5" style="text-align:center;padding:40px" class="text-muted">Your cart is empty.</td></tr>
        <?php endif; ?>
        <?php foreach ($cartItems as $item): $p = $item['p']; ?>
          <tr>
            <td><?= e($p['product_name']) ?></td>
            <td style="color:#2563eb;font-weight:700"><?= peso($p['selling_price']) ?></td>
            <td>
              <div class="cust-qty">
                <button type="button" onclick="qtyStep('qty-<?= $p['id'] ?>', -1)">−</button>
                <input id="qty-<?= $p['id'] ?>" name="qty[<?= $p['id'] ?>]" type="number" min="0" value="<?= $item['qty'] ?>">
                <button type="button" onclick="qtyStep('qty-<?= $p['id'] ?>', 1)">+</button>
              </div>
            </td>
            <td style="font-weight:700"><?= peso($item['line']) ?></td>
            <td class="remove"><a href="<?= base_url('customer/cart.php?action=remove&pid=' . $p['id']) ?>">Remove</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="cust-summary-card">
      <h3>Order Summary</h3>
      <div class="summary-row"><span>Subtotal</span><span><?= peso($subtotal) ?></span></div>
      <div class="summary-row"><span>Delivery Fee</span><span><?= peso($deliveryFee) ?></span></div>
      <div class="summary-row total"><span>TOTAL</span><span><?= peso($total) ?></span></div>
      <div class="summary-actions">
        <a class="btn btn-primary" href="<?= base_url('customer/checkout.php') ?>">PROCEED TO CHECKOUT</a>
        <a class="btn" href="<?= base_url('customer/products.php') ?>">CONTINUE SHOPPING</a>
        <button type="submit" class="btn">Update Cart</button>
      </div>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/../customer-includes/footer.php'; ?>