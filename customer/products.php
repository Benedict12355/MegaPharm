<?php
require_once __DIR__ . '/../includes/functions.php';

$cust_page   = 'Products';
$cust_active = 'products';

$q    = trim($_GET['q'] ?? '');
$cat  = $_GET['cat'] ?? '';
$age  = $_GET['age'] ?? '';
$type = $_GET['type'] ?? '';

$sql = "SELECT p.*, c.category_name,
        (SELECT COALESCE(SUM(quantity),0) FROM inventory_batches WHERE product_id = p.id) AS stock
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active'";
$params = [];

if ($q !== '') {
    $sql .= " AND (p.product_name LIKE ? OR p.generic_name LIKE ? OR p.brand LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
if ($cat !== '' && ctype_digit((string)$cat)) { $sql .= " AND p.category_id = ?"; $params[] = (int)$cat; }
if ($age !== '') { $sql .= " AND p.age_group = ?"; $params[] = $age; }
if ($type === 'medicines')  { $sql .= " AND (c.category_name NOT IN ('Cosmetics','Milks') OR c.id IS NULL)"; }
if ($type === 'cosmetics')  { $sql .= " AND c.category_name = 'Cosmetics'"; }
if ($type === 'milks')      { $sql .= " AND c.category_name = 'Milks'"; }
$sql .= " ORDER BY p.product_name ASC LIMIT 60";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = db()->query('SELECT id, category_name FROM categories ORDER BY category_name')->fetchAll();

require_once __DIR__ . '/../customer-includes/header.php';
?>
<h1 style="display:none">Products</h1>
<h2 style="font-size:20px;margin:0 0 16px">Products</h2>

<form method="get" class="cust-filters">
  <div class="cust-search">
    <input type="text" name="q" placeholder="Search products..." value="<?= e($q) ?>">
  </div>
  <select name="age" class="cust-select" onchange="this.form.submit()">
    <option value="">All Categories</option>
    <option value="adult"  <?= $age === 'adult'  ? 'selected' : '' ?>>Adults</option>
    <option value="kid"    <?= $age === 'kid'    ? 'selected' : '' ?>>Kids</option>
    <option value="infant" <?= $age === 'infant' ? 'selected' : '' ?>>Infants</option>
  </select>
  <select name="type" class="cust-select" onchange="this.form.submit()">
    <option value="">All Categories</option>
    <option value="medicines" <?= $type === 'medicines' ? 'selected' : '' ?>>Medicines</option>
    <option value="cosmetics" <?= $type === 'cosmetics' ? 'selected' : '' ?>>Cosmetics</option>
    <option value="milks"     <?= $type === 'milks'     ? 'selected' : '' ?>>Milks</option>
  </select>
</form>

<div class="cust-pills">
  <a class="cust-pill <?= !$cat ? 'active' : '' ?>" href="<?= base_url('customer/products.php') ?>">All</a>
  <?php foreach ($categories as $c): ?>
    <a class="cust-pill <?= $cat == $c['id'] ? 'active' : '' ?>"
       href="<?= base_url('customer/products.php?cat=' . $c['id']) ?>"><?= e($c['category_name']) ?></a>
  <?php endforeach; ?>
</div>

<div class="cust-grid">
  <?php if (!$products): ?>
    <p class="text-muted" style="grid-column:1/-1;padding:40px;text-align:center">No products found.</p>
  <?php endif; ?>
  <?php foreach ($products as $p): ?>
    <?php include __DIR__ . '/_product_card.php'; ?>
  <?php endforeach; ?>
</div>

<div class="cust-modal-overlay" id="productModal">
  <div class="cust-modal">
    <button class="cust-modal-close" onclick="closeProductModal()">✕</button>
    <h3>Product Details</h3>
    <div class="cust-modal-grid">
      <div class="cust-modal-img" id="pm-img"><span class="placeholder">💊</span></div>
      <div class="cust-modal-info">
        <p><b>Product Name:</b> <span id="pm-name"></span></p>
        <p><b>Category:</b> <span id="pm-cat"></span></p>
        <p><b>Brand:</b> <span id="pm-brand"></span></p>
        <p><b>Generic Name:</b> <span id="pm-gen"></span></p>
        <p><b>Price:</b> <span id="pm-price"></span></p>
        <p><b>Stock:</b> <span id="pm-stock"></span></p>
        <p style="margin-top:12px"><b>Quantity:</b></p>
        <div class="cust-qty">
          <button type="button" onclick="qtyStep('pm-qty', -1)">−</button>
          <input id="pm-qty" type="number" value="1" min="1">
          <button type="button" onclick="qtyStep('pm-qty', 1)">+</button>
        </div>
        <div style="margin-top:16px;text-align:right">
          <button class="btn-cart" type="button" onclick="submitAddFromModal()">ADD TO CART</button>
        </div>
      </div>
    </div>
  </div>
</div>

<form id="modalAddForm" method="post" action="<?= base_url('customer/cart.php') ?>" style="display:none">
  <input type="hidden" name="action" value="add">
  <input type="hidden" name="product_id" id="maf-pid">
  <input type="hidden" name="qty" id="maf-qty">
</form>

<script>
let currentProduct = null;
function viewProduct(p) {
  currentProduct = p;
  document.getElementById('pm-name').textContent  = p.product_name || '';
  document.getElementById('pm-cat').textContent   = p.category_name || '—';
  document.getElementById('pm-brand').textContent = p.brand || '—';
  document.getElementById('pm-gen').textContent   = p.generic_name || '—';
  document.getElementById('pm-price').textContent = '₱' + parseFloat(p.selling_price).toFixed(2);
  document.getElementById('pm-stock').textContent = parseInt(p.stock) > 0 ? 'Available' : 'Out of Stock';
  document.getElementById('pm-qty').value = 1;

  const imgBox = document.getElementById('pm-img');
  if (p.image_path) imgBox.innerHTML = `<img src="<?= base_url('') ?>${p.image_path}" alt="">`;
  else              imgBox.innerHTML = '<span class="placeholder">💊</span>';

  openProductModal();
}
function submitAddFromModal() {
  if (!currentProduct) return;
  document.getElementById('maf-pid').value = currentProduct.id;
  document.getElementById('maf-qty').value = document.getElementById('pm-qty').value;
  document.getElementById('modalAddForm').submit();
}
</script>

<?php require_once __DIR__ . '/../customer-includes/footer.php'; ?>