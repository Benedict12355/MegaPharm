<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'products';
$page_title = 'Products';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'create' || $act === 'update') {
        $data = [
          'product_name' => trim($_POST['product_name']),
          'generic_name' => trim($_POST['generic_name'] ?? ''),
          'brand' => trim($_POST['brand'] ?? ''),
          'category_id' => (int)($_POST['category_id'] ?? 0) ?: null,
          'selling_price' => (float)($_POST['selling_price'] ?? 0),
          'purchase_price' => (float)($_POST['purchase_price'] ?? 0),
          'reorder_level' => (int)($_POST['reorder_level'] ?? 0),
          'status' => $_POST['status'] ?? 'active',
        ];
        if ($act === 'create') {
            $code = next_number('products', 'product_code', 'PRD-');
            $stmt = db()->prepare('INSERT INTO products (product_code, product_name, generic_name, brand, category_id, selling_price, purchase_price, reorder_level, status) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$code, $data['product_name'], $data['generic_name'], $data['brand'], $data['category_id'], $data['selling_price'], $data['purchase_price'], $data['reorder_level'], $data['status']]);
            $pid = (int)db()->lastInsertId();
            audit('Add', 'Product', "Created {$data['product_name']}");
            // initial stock
            $qty = (int)($_POST['quantity'] ?? 0);
            $expiry = $_POST['expiry_date'] ?? null;
            $supplier = trim($_POST['supplier_text'] ?? '');
            if ($qty > 0) {
                $batchNo = 'B' . strtoupper(bin2hex(random_bytes(3)));
                $b = db()->prepare('INSERT INTO inventory_batches (product_id, batch_number, expiry_date, quantity, purchase_price, received_date) VALUES (?,?,?,?,?,CURDATE())');
                $b->execute([$pid, $batchNo, $expiry ?: null, $qty, $data['purchase_price']]);
                $bid = (int)db()->lastInsertId();
                db()->prepare('INSERT INTO stock_movements (product_id, batch_id, user_id, movement_type, quantity_change, reason) VALUES (?,?,?,?,?,?)')
                   ->execute([$pid, $bid, $u['id'], 'stock_in', $qty, 'Initial stock']);
            }
            flash('success', 'Product added successfully.');
        } else {
            $id = (int)$_POST['id'];
            db()->prepare('UPDATE products SET product_name=?, generic_name=?, brand=?, category_id=?, selling_price=?, purchase_price=?, reorder_level=?, status=? WHERE id=?')
               ->execute([$data['product_name'], $data['generic_name'], $data['brand'], $data['category_id'], $data['selling_price'], $data['purchase_price'], $data['reorder_level'], $data['status'], $id]);
            audit('Update', 'Product', "Updated {$data['product_name']}");
            flash('success', 'Product updated.');
        }
        redirect(base_url('admin/products.php'));
    }

    if ($act === 'delete') {
        $id = (int)$_POST['id'];
        try {
            db()->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
            audit('Delete', 'Product', "Deleted product #$id");
            flash('success', 'Product deleted.');
        } catch (PDOException $e) {
            flash('error', 'Cannot delete: product has related records.');
        }
        redirect(base_url('admin/products.php'));
    }
}

$categories = db()->query('SELECT * FROM categories ORDER BY category_name')->fetchAll();
$catCounts = ['total'=>0,'medicines'=>0,'cosmetics'=>0,'milks'=>0];
$catCounts['total'] = (int)db()->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$catCounts['medicines'] = (int)db()->query("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND (c.category_name NOT IN ('Cosmetics','Milks') OR c.id IS NULL)")->fetchColumn();
$catCounts['cosmetics'] = (int)db()->query("SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND c.category_name='Cosmetics'")->fetchColumn();
$catCounts['milks'] = (int)db()->query("SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND c.category_name='Milks'")->fetchColumn();

$search = trim($_GET['q'] ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);
$sql = "SELECT p.*, c.category_name,
        (SELECT COALESCE(SUM(quantity),0) FROM inventory_batches WHERE product_id=p.id) stock,
        (SELECT MIN(expiry_date) FROM inventory_batches WHERE product_id=p.id AND quantity>0) expiry
        FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE 1=1";
$params = [];
if ($search !== '') { $sql .= " AND (p.product_name LIKE ? OR p.generic_name LIKE ? OR p.product_code LIKE ?)"; $s = "%$search%"; array_push($params,$s,$s,$s); }
if ($catFilter) { $sql .= " AND p.category_id=?"; $params[] = $catFilter; }
$sql .= " ORDER BY p.id DESC LIMIT 50";
$stmt = db()->prepare($sql); $stmt->execute($params); $products = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Products</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Total Products</div><div class="stat-value"><?= $catCounts['total'] ?></div></div>
  <div class="stat"><div class="stat-label">Medicines</div><div class="stat-value"><?= $catCounts['medicines'] ?></div></div>
  <div class="stat"><div class="stat-label">Cosmetics</div><div class="stat-value"><?= $catCounts['cosmetics'] ?></div></div>
  <div class="stat"><div class="stat-label">Milks</div><div class="stat-value"><?= $catCounts['milks'] ?></div></div>
</div>

<div class="card">
  <div class="toolbar">
    <form class="search" method="get">
      <input class="input" type="text" name="q" placeholder="Search product..." value="<?= e($search) ?>">
      <?php if ($catFilter): ?><input type="hidden" name="cat" value="<?= $catFilter ?>"><?php endif; ?>
    </form>
    <div class="tabs">
      <a class="tab <?= !$catFilter ? 'active':'' ?>" href="?">All products</a>
      <a class="tab" href="?cat=<?= (int)(db()->query("SELECT id FROM categories WHERE category_name LIKE 'Antibiotics%' LIMIT 1")->fetchColumn() ?: 0) ?>">Medicines</a>
      <a class="tab" href="?cat=<?= (int)(db()->query("SELECT id FROM categories WHERE category_name='Cosmetics' LIMIT 1")->fetchColumn() ?: 0) ?>">Cosmetics</a>
      <a class="tab" href="?cat=<?= (int)(db()->query("SELECT id FROM categories WHERE category_name='Milks' LIMIT 1")->fetchColumn() ?: 0) ?>">Milks</a>
    </div>
    <button class="btn btn-primary" onclick="openModal('addProduct')">➕ Add product</button>
  </div>

  <div class="pills">
    <?php foreach ($categories as $c): ?>
      <a class="pill <?= $catFilter===$c['id']?'active':'' ?>" href="?cat=<?= $c['id'] ?>"><?= e($c['category_name']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>Product</th><th>Price (₱)</th><th>Stock</th><th>Expiry</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (!$products): ?><tr><td colspan="5" class="empty">No products found.</td></tr><?php endif; ?>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><?= e($p['product_name']) ?></td>
          <td><?= peso($p['selling_price']) ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td><?= $p['expiry'] ? e(date('Y-m-d', strtotime($p['expiry']))) : '—' ?></td>
          <td class="row-actions">
            <button class="btn-icon" title="Edit" onclick='editProduct(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this product?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="act" value="delete">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button class="btn-icon danger" title="Delete">🗑️</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Product Modal -->
<div class="modal-overlay" id="addProduct">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('addProduct')">✕</button>
    <h3>Add New Product</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="create">
      <div class="form-grid">
        <div class="field"><label>Product Name <span class="req">*</span></label><input name="product_name" required></div>
        <div class="field"><label>Generic Name</label><input name="generic_name"></div>
        <div class="field"><label>Brand</label><input name="brand"></div>
        <div class="field"><label>Category <span class="req">*</span></label>
          <select name="category_id" required>
            <option value="">Select...</option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Reorder Level <span class="req">*</span></label><input name="reorder_level" type="number" value="20" required></div>
        <div class="field"><label>Quantity <span class="req">*</span></label><input name="quantity" type="number" min="0" value="0" required></div>
        <div class="field"><label>Supplier</label><input name="supplier_text" placeholder="Supplier name"></div>
        <div class="field"><label>Purchase Price (₱)</label><input name="purchase_price" type="number" step="0.01" value="0"></div>
        <div class="field"><label>Selling Price (₱) <span class="req">*</span></label><input name="selling_price" type="number" step="0.01" value="0" required></div>
        <div class="field"><label>Expiry Date</label><input name="expiry_date" type="date"></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('addProduct')">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Product Modal -->
<div class="modal-overlay" id="editProduct">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('editProduct')">✕</button>
    <h3>Edit Product</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="update">
      <input type="hidden" name="id" id="ep-id">
      <div class="form-grid">
        <div class="field"><label>Product Name</label><input name="product_name" id="ep-name" required></div>
        <div class="field"><label>Generic Name</label><input name="generic_name" id="ep-gen"></div>
        <div class="field"><label>Brand</label><input name="brand" id="ep-brand"></div>
        <div class="field"><label>Category</label>
          <select name="category_id" id="ep-cat">
            <option value="">Select...</option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Reorder Level</label><input name="reorder_level" id="ep-reorder" type="number"></div>
        <div class="field"><label>Purchase Price</label><input name="purchase_price" id="ep-pp" type="number" step="0.01"></div>
        <div class="field"><label>Selling Price</label><input name="selling_price" id="ep-sp" type="number" step="0.01"></div>
        <div class="field"><label>Status</label>
          <select name="status" id="ep-status">
            <option value="active">Active</option><option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('editProduct')">Cancel</button>
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function editProduct(p){
  document.getElementById('ep-id').value = p.id;
  document.getElementById('ep-name').value = p.product_name || '';
  document.getElementById('ep-gen').value = p.generic_name || '';
  document.getElementById('ep-brand').value = p.brand || '';
  document.getElementById('ep-cat').value = p.category_id || '';
  document.getElementById('ep-reorder').value = p.reorder_level || 0;
  document.getElementById('ep-pp').value = p.purchase_price || 0;
  document.getElementById('ep-sp').value = p.selling_price || 0;
  document.getElementById('ep-status').value = p.status || 'active';
  openModal('editProduct');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>