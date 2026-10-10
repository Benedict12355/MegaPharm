<?php
require_once __DIR__ . '/../includes/functions.php';

$cust_page   = 'Home';
$cust_active = 'home';

$featured = db()->query("SELECT p.*, c.category_name,
    (SELECT COALESCE(SUM(quantity),0) FROM inventory_batches WHERE product_id = p.id) AS stock
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'active'
    ORDER BY p.id DESC
    LIMIT 8")->fetchAll();

$cats = db()->query("SELECT * FROM categories ORDER BY category_name LIMIT 3")->fetchAll();

require_once __DIR__ . '/../customer-includes/header.php';
?>
<h1 style="display:none">Home</h1>

<div class="cust-hero">
  <h1>Welcome to MegaPharm</h1>
  <p>Your trusted online pharmacy store. Shop your everyday health and personal care needs easily.</p>
  <a class="btn-shop" href="<?= base_url('customer/products.php') ?>">SHOP NOW</a>
</div>

<h2 class="cust-section-title">Shop by Category</h2>
<div class="cust-cats">
  <?php
  $catIcons = ['Medicines' => '💊', 'Cosmetics' => '🧴', 'Milks' => '🍼'];
  foreach ($cats as $c):
    $icon = $catIcons[$c['category_name']] ?? '📦';
  ?>
    <a class="cust-cat" href="<?= base_url('customer/products.php?cat=' . $c['id']) ?>">
      <span class="cat-icon"><?= $icon ?></span>
      <span class="cat-label"><?= e($c['category_name']) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<h2 class="cust-section-title">Shop by Category</h2>
<div class="cust-grid">
  <?php foreach ($featured as $p): ?>
    <?php include __DIR__ . '/_product_card.php'; ?>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../customer-includes/footer.php'; ?>