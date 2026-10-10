<?php
// admin/products.php
require_once __DIR__ . '/../includes/functions.php';
require_staff_or_admin();
$active = 'products';
$page_title = 'Products';

/* ---------- IMAGE UPLOAD HELPER ---------- */
function handle_product_image(?string $existingPath = null): ?string {
    if (empty($_FILES['product_image']['name'])) return $existingPath;
    $file = $_FILES['product_image'];
    if ($file['error'] !== UPLOAD_ERR_OK) return $existingPath;

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) return $existingPath;
    if ($file['size'] > 2 * 1024 * 1024) return $existingPath;

    $ext      = $allowed[$mime];
    $filename = 'prd_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dir      = __DIR__ . '/../assets/img/products/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) return $existingPath;

    if ($existingPath) {
        $old = __DIR__ . '/../' . ltrim($existingPath, '/');
        if (is_file($old)) @unlink($old);
    }
    return 'assets/img/products/' . $filename;
}

/* ---------- POST ACTIONS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $code = next_number('products', 'product_code', 'PRD-');
        $imagePath = handle_product_image(null);

        $stmt = db()->prepare('INSERT INTO products 
            (product_code, product_name, generic_name, brand, category_id, age_group,
             selling_price, purchase_price, reorder_level, requires_prescription, status, image_path)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $code,
            trim($_POST['product_name']),
            trim($_POST['generic_name'] ?? ''),
            trim($_POST['brand'] ?? ''),
            (int)$_POST['category_id'] ?: null,
            $_POST['age_group'] ?? 'all_ages',
            (float)$_POST['selling_price'],
            (float)($_POST['purchase_price'] ?? 0),
            (int)$_POST['reorder_level'],
            isset($_POST['requires_prescription']) ? 1 : 0,
            $_POST['status'] ?? 'active',
            $imagePath,
        ]);
        $pid = (int)db()->lastInsertId();

        $qty     = (int)($_POST['quantity'] ?? 0);
        $batchNo = trim($_POST['batch_number'] ?? '') ?: ('B' . strtoupper(bin2hex(random_bytes(3))));
        $expiry  = $_POST['expiry_date'] ?: null;

        if ($qty > 0) {
            db()->prepare('INSERT INTO inventory_batches 
                (product_id, batch_number, expiry_date, quantity, purchase_price, received_date)
                VALUES (?,?,?,?,?,CURDATE())')
               ->execute([$pid, $batchNo, $expiry, $qty, (float)$_POST['purchase_price']]);
            $bid = (int)db()->lastInsertId();
            db()->prepare('INSERT INTO stock_movements 
                (product_id, batch_id, user_id, movement_type, quantity_change, reason)
                VALUES (?,?,?,?,?,?)')
               ->execute([$pid, $bid, $u['id'], 'stock_in', $qty, 'Initial stock']);
        }
        audit('Add', 'Product', "Created {$_POST['product_name']}");
        flash('success', 'Product added successfully.');

    } elseif ($act === 'update') {
        $id = (int)$_POST['id'];
        $old = db()->prepare('SELECT image_path FROM products WHERE id=?');
        $old->execute([$id]);
        $existing = $old->fetchColumn() ?: null;
        $imagePath = handle_product_image($existing);

        db()->prepare('UPDATE products 
            SET product_name=?, generic_name=?, brand=?, category_id=?, age_group=?,
                selling_price=?, purchase_price=?, reorder_level=?, 
                requires_prescription=?, status=?, image_path=?
            WHERE id=?')
           ->execute([
               trim($_POST['product_name']),
               trim($_POST['generic_name'] ?? ''),
               trim($_POST['brand'] ?? ''),
               (int)$_POST['category_id'] ?: null,
               $_POST['age_group'] ?? 'all_ages',
               (float)$_POST['selling_price'],
               (float)($_POST['purchase_price'] ?? 0),
               (int)$_POST['reorder_level'],
               isset($_POST['requires_prescription']) ? 1 : 0,
               $_POST['status'] ?? 'active',
               $imagePath,
               $id,
           ]);
        audit('Update', 'Product', "Updated #$id");
        flash('success', 'Product updated.');

    } elseif ($act === 'delete') {
        if (!can('delete', 'products')) {
            flash('error', 'You do not have permission to delete products.');
            redirect(base_url('admin/products.php'));
        }
        $id = (int)$_POST['id'];
        try {
            db()->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
            audit('Delete', 'Product', "Deleted #$id");
            flash('success', 'Product deleted.');
        } catch (PDOException $e) {
            flash('error', 'Cannot delete: product has related records.');
        }
    }
    redirect(base_url('admin/products.php' . qs()));
}

/* ---------- FILTERS ---------- */
$q      = trim($_GET['q'] ?? '');
$cat    = $_GET['cat'] ?? '';
$type   = $_GET['type'] ?? '';
$age    = $_GET['age'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT p.*, c.category_name,
        (SELECT COALESCE(SUM(quantity),0) FROM inventory_batches WHERE product_id=p.id) stock,
        (SELECT MIN(expiry_date) FROM inventory_batches WHERE product_id=p.id AND quantity>0) expiry
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (p.product_name LIKE ? OR p.generic_name LIKE ? OR p.product_code LIKE ? OR p.brand LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if ($cat !== '' && ctype_digit((string)$cat)) {
    $sql .= " AND p.category_id = ?";
    $params[] = (int)$cat;
}
if ($type === 'medicines') {
    $sql .= " AND (c.category_name NOT IN ('Cosmetics','Milks') OR c.id IS NULL)";
} elseif ($type === 'cosmetics') {
    $sql .= " AND c.category_name = 'Cosmetics'";
} elseif ($type === 'milks') {
    $sql .= " AND c.category_name = 'Milks'";
}
if ($age !== '') {
    $sql .= " AND p.age_group = ?";
    $params[] = $age;
}
if ($status === 'active' || $status === 'inactive') {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY p.product_name ASC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$statTotal     = (int)db()->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$statMedicines = (int)db()->query("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND (c.category_name NOT IN ('Cosmetics','Milks') OR c.id IS NULL)")->fetchColumn();
$statCosmetics = (int)db()->query("SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND c.category_name='Cosmetics'")->fetchColumn();
$statMilks     = (int)db()->query("SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND c.category_name='Milks'")->fetchColumn();

$categories = db()->query('SELECT id, category_name FROM categories ORDER BY category_name')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Products</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Total Products</div><div class="stat-value"><?= $statTotal ?></div></div>
  <div class="stat"><div class="stat-label">Medicines</div><div class="stat-value"><?= $statMedicines ?></div></div>
  <div class="stat"><div class="stat-label">Cosmetics</div><div class="stat-value"><?= $statCosmetics ?></div></div>
  <div class="stat"><div class="stat-label">Milks</div><div class="stat-value"><?= $statMilks ?></div></div>
</div>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search product..." value="<?= e($q) ?>" style="width:100%">
    </div>
    <div class="tabs">
      <a class="tab <?= !$type ? 'active' : '' ?>" href="<?= base_url('admin/products.php' . qs(['type' => ''])) ?>">All products</a>
      <a class="tab <?= $type === 'medicines' ? 'active' : '' ?>" href="<?= base_url('admin/products.php' . qs(['type' => 'medicines'])) ?>">Medicines</a>
      <a class="tab <?= $type === 'cosmetics' ? 'active' : '' ?>" href="<?= base_url('admin/products.php' . qs(['type' => 'cosmetics'])) ?>">Cosmetics</a>
      <a class="tab <?= $type === 'milks' ? 'active' : '' ?>" href="<?= base_url('admin/products.php' . qs(['type' => 'milks'])) ?>">Milks</a>
    </div>
    <button class="btn btn-primary" type="button" onclick="openModal('addProduct')">➕ Add product</button>
  </form>

  <div class="pills">
    <a class="pill <?= $cat === '' ? 'active' : '' ?>" href="<?= base_url('admin/products.php' . qs(['cat' => ''])) ?>">All</a>
    <?php foreach ($categories as $c): ?>
      <a class="pill <?= $cat == $c['id'] ? 'active' : '' ?>" href="<?= base_url('admin/products.php' . qs(['cat' => $c['id']])) ?>">
        <?= e($c['category_name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Name</th><th>Price (₱)</th><th>Stock</th><th>Expiry</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$products): ?>
        <tr><td colspan="5" class="empty">No products match your filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><?= e($p['product_name']) ?></td>
          <td><?= peso($p['selling_price']) ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td><?= $p['expiry'] ? e(date('Y-m-d', strtotime($p['expiry']))) : '—' ?></td>
          <td class="row-actions">
            <?php if (is_admin()): ?>
              <button class="btn-icon" title="Edit" onclick='editProduct(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this product?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn-icon danger" title="Delete">🗑️</button>
              </form>
            <?php else: ?>
              <button class="btn btn-sm">View</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted" style="padding:12px 16px">Showing 1 to <?= count($products) ?> of <?= count($products) ?> product</p>
</div>

<!-- ============ MODAL: ADD PRODUCT ============ -->
<div class="modal-overlay" id="addProduct">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('addProduct')">✕</button>
    <h3>Add New Product</h3>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="create">
      <div class="form-grid">
        <div class="field"><label>Brand Name <span class="req">*</span></label><input name="product_name" required></div>
        <div class="field"><label>Generic Name</label><input name="generic_name"></div>
        <div class="field"><label>Category <span class="req">*</span></label>
          <select name="category_id" required>
            <option value="">Select...</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Reorder Level <span class="req">*</span></label><input name="reorder_level" type="number" value="20" required></div>
        <div class="field"><label>Quantity <span class="req">*</span></label><input name="quantity" type="number" value="0" required></div>
        <div class="field"><label>Supplier</label>
          <select name="supplier_id">
            <option value="">Select...</option>
            <?php foreach (db()->query('SELECT id, supplier_name FROM suppliers WHERE status="active" ORDER BY supplier_name') as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Unit Price (₱)</label><input name="selling_price" type="number" step="0.01" value="0" required></div>
        <div class="field"><label>Expiry Date</label><input name="expiry_date" type="date"></div>
        <div class="field full">
          <label>Product Image</label>
          <input type="file" name="product_image" accept="image/*">
          <small class="text-muted">JPG, PNG, WEBP · Max 2MB</small>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('addProduct')">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ MODAL: EDIT PRODUCT ============ -->
<div class="modal-overlay" id="editProduct">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('editProduct')">✕</button>
    <h3>Edit Product</h3>
    <form method="post" enctype="multipart/form-data">
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
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Reorder Level</label><input name="reorder_level" id="ep-reorder" type="number"></div>
        <div class="field"><label>Purchase Price</label><input name="purchase_price" id="ep-pp" type="number" step="0.01"></div>
        <div class="field"><label>Selling Price</label><input name="selling_price" id="ep-sp" type="number" step="0.01"></div>
        <div class="field"><label>Status</label>
          <select name="status" id="ep-status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="field full">
          <label>Replace Image (optional)</label>
          <input type="file" name="product_image" accept="image/*">
          <div id="ep-image-preview" style="margin-top:8px"></div>
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
function editProduct(p) {
  document.getElementById('ep-id').value      = p.id;
  document.getElementById('ep-name').value    = p.product_name || '';
  document.getElementById('ep-gen').value     = p.generic_name || '';
  document.getElementById('ep-brand').value   = p.brand || '';
  document.getElementById('ep-cat').value     = p.category_id || '';
  document.getElementById('ep-reorder').value = p.reorder_level || 0;
  document.getElementById('ep-pp').value      = p.purchase_price || 0;
  document.getElementById('ep-sp').value      = p.selling_price || 0;
  document.getElementById('ep-status').value  = p.status || 'active';

  const prev = document.getElementById('ep-image-preview');
  if (p.image_path) {
    prev.innerHTML = `<img src="<?= base_url('') ?>${p.image_path}" style="height:80px;border-radius:6px">`;
  } else {
    prev.innerHTML = '<span class="text-muted" style="font-size:12px">No image uploaded</span>';
  }
  openModal('editProduct');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>