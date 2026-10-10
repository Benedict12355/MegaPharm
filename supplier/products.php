<?php
require_once __DIR__ . '/../includes/functions.php';

$sup_page = 'Products';
$sup_active = 'products';
$supUser = $_SESSION['supplier'];

$supStmt = db()->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$supStmt->execute([$supUser['id']]);
$supplierId = (int)$supStmt->fetchColumn();

$q = trim($_GET['q'] ?? '');

// Products supplied by this supplier (via product IDs found in this supplier's POs)
$sql = "SELECT DISTINCT p.id, p.product_code, p.product_name, p.selling_price, p.status
        FROM products p
        JOIN purchase_order_items poi ON poi.product_id = p.id
        JOIN purchase_orders po ON po.id = poi.purchase_order_id
        WHERE po.supplier_id = ?";
$params = [$supplierId];
if ($q !== '') {
    $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ?)";
    $like = "%$q%"; array_push($params, $like, $like);
}
$sql .= " ORDER BY p.product_name ASC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">Products</h1>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search product..."
             value="<?= e($q) ?>" style="width:100%">
    </div>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr><th>Product</th><th>SKU</th><th>Price</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php if (!$products): ?>
        <tr><td colspan="5" class="empty">No products assigned to you yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><?= e($p['product_name']) ?></td>
          <td><?= e($p['product_code']) ?></td>
          <td><?= peso($p['selling_price']) ?></td>
          <td class="<?= $p['status'] === 'active' ? 'status-delivered' : 'status-cancelled' ?>">
            <?= e(ucfirst($p['status'])) ?>
          </td>
          <td><a class="btn btn-sm" href="<?= base_url('admin/products.php?q=' . urlencode($p['product_code'])) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted" style="padding:12px 16px">
    Showing 1 to <?= count($products) ?> of <?= count($products) ?> product
  </p>
</div>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>