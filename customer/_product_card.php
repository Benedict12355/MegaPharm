<?php
$imgSrc = !empty($p['image_path']) ? base_url($p['image_path']) : null;
$isOOS = ((int)$p['stock']) <= 0;
?>
<div class="cust-card">
  <div class="cust-card-img">
    <?php if ($imgSrc): ?>
      <img src="<?= e($imgSrc) ?>" alt="<?= e($p['product_name']) ?>">
    <?php else: ?>
      <span class="placeholder">💊</span>
    <?php endif; ?>
  </div>
  <p class="cust-card-name"><?= e($p['product_name']) ?></p>
  <p class="cust-card-cat"><?= e($p['category_name'] ?? '—') ?></p>
  <p class="cust-card-price"><?= peso($p['selling_price']) ?></p>
  <div class="cust-card-actions">
    <button class="btn-view" type="button"
      onclick='viewProduct(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>VIEW</button>
    <form method="post" action="<?= base_url('customer/cart.php') ?>" style="margin:0">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      <input type="hidden" name="qty" value="1">
      <button class="btn-cart" <?= $isOOS ? 'disabled' : '' ?>>
        <?= $isOOS ? 'OUT' : 'ADD TO CART' ?>
      </button>
    </form>
  </div>
</div>