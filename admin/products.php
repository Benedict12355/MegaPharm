<?php
// admin/products.php
require_once __DIR__ . '/../includes/functions.php';
require_staff_or_admin();
$active = 'products';
$page_title = 'Products';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $code = next_number('products', 'product_code', 'PRD-');
        $stmt = db()->prepare('INSERT INTO products 
            (product_code, product_name, generic_name, brand, category_id, age_group,
             selling_price, purchase_price, reorder_level, requires_prescription, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)');
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
        db()->prepare('UPDATE products 
            SET product_name=?, generic_name=?, brand=?, category_id=?, age_group=?,
                selling_price=?, purchase_price=?, reorder_level=?, 
                requires_prescription=?, status=?
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
               $id,
           ]);
        audit('Update', 'Product', "Updated #$id");
        flash('success', 'Product updated.');

    } elseif ($act === 'delete') {
        // 🔒 Only admins can delete
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
      <input class="input" name="q" placeholder="Search product..."
             value="<?= e($q) ?>" style="width:100%">
    </div>

    <select class="select" name="cat" onchange="this.form.submit()">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>>
          <?= e($c['category_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select class="select" name="type" onchange="this.form.submit()">
      <option value="">All Types</option>
      <option value="medicines" <?= $type === 'medicines' ? 'selected' : '' ?>>Medicines</option>
      <option value="cosmetics" <?= $type === 'cosmetics' ? 'selected' : '' ?>>Cosmetics</option>
      <option value="milks"     <?= $type === 'milks'     ? 'selected' : '' ?>>Milks</option>
    </select>

    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>

    <?php if ($q || $cat || $type || $age || $status): ?>
      <a class="btn" href="<?= base_url('admin/products.php') ?>">Reset</a>
    <?php endif; ?>

    <button class="btn btn-primary" type="button" onclick="openModal('addProduct')">➕ Add product</button>
  </form>

  <div class="pills">
    <a class="pill <?= $cat === '' ? 'active' : '' ?>"
       href="<?= base_url('admin/products.php' . qs(['cat' => ''])) ?>">All</a>
    <?php foreach ($categories as $c): ?>
      <a class="pill <?= $cat == $c['id'] ? 'active' : '' ?>"
         href="<?= base_url('admin/products.php' . qs(['cat' => $c['id']])) ?>">
        <?= e($c['category_name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Product</th><th>Category</th><th>Age</th><th>Price (₱)</th>
          <th>Stock</th><th>Expiry</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$products): ?>
        <tr><td colspan="8" class="empty">No products match your filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($products as $p):
        $st = stock_status((int)$p['stock'], (int)$p['reorder_level']);
        $statusLabel = $st === 'low' ? 'Low Stock' : ($st === 'out' ? 'Out of Stock' : 'Normal');
        $statusCls   = $st === 'normal' ? 'status-ok' : 'status-low';
      ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= e($p['product_name']) ?></div>
            <?php if ($p['brand']): ?>
              <div class="text-muted" style="font-size:11px"><?= e($p['brand']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= e($p['category_name'] ?? '—') ?></td>
          <td><?= e(ucfirst(str_replace('_',' ', $p['age_group']))) ?></td>
          <td><?= peso($p['selling_price']) ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td><?= $p['expiry'] ? e(date('Y-m-d', strtotime($p['expiry']))) : '—' ?></td>
          <td class="<?= $statusCls ?>"><?= $statusLabel ?></td>
          <td class="row-actions">
            <button class="btn-icon" title="Edit"
                    onclick='editProduct(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
            <?php if (can('delete', 'products')): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this product?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn-icon danger" title="Delete">🗑️</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ MODAL: ADD PRODUCT ============ -->
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
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Age Group <span class="req">*</span></label>
          <select name="age_group" required>
            <option value="all_ages">All Ages</option>
            <option value="adult">Adults</option>
            <option value="kid">Kids</option>
            <option value="infant">Infants</option>
          </select>
        </div>
        <div class="field"><label>Supplier</label>
          <select name="supplier_id">
            <option value="">Select supplier...</option>
            <?php foreach (db()->query('SELECT id, supplier_name FROM suppliers WHERE status="active" ORDER BY supplier_name') as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Batch No.</label><input name="batch_number" placeholder="Auto if blank"></div>
        <div class="field"><label>Quantity <span class="req">*</span></label><input name="quantity" type="number" value="0" required></div>
        <div class="field"><label>Reorder Level <span class="req">*</span></label><input name="reorder_level" type="number" value="20" required></div>
        <div class="field"><label>Purchase Price (₱)</label><input name="purchase_price" type="number" step="0.01" value="0"></div>
        <div class="field"><label>Selling Price (₱) <span class="req">*</span></label><input name="selling_price" type="number" step="0.01" value="0" required></div>
        <div class="field"><label>Expiry Date</label><input name="expiry_date" type="date"></div>
        <div class="field full">
          <label style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="requires_prescription" value="1" style="width:auto"> Requires prescription
          </label>
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
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Age Group</label>
          <select name="age_group" id="ep-age">
            <option value="all_ages">All Ages</option>
            <option value="adult">Adults</option>
            <option value="kid">Kids</option>
            <option value="infant">Infants</option>
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
          <label style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="requires_prescription" id="ep-rx" value="1" style="width:auto"> Requires prescription
          </label>
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
  document.getElementById('ep-id').value       = p.id;
  document.getElementById('ep-name').value     = p.product_name || '';
  document.getElementById('ep-gen').value      = p.generic_name || '';
  document.getElementById('ep-brand').value    = p.brand || '';
  document.getElementById('ep-cat').value      = p.category_id || '';
  document.getElementById('ep-age').value      = p.age_group || 'all_ages';
  document.getElementById('ep-reorder').value  = p.reorder_level || 0;
  document.getElementById('ep-pp').value       = p.purchase_price || 0;
  document.getElementById('ep-sp').value       = p.selling_price || 0;
  document.getElementById('ep-status').value   = p.status || 'active';
  document.getElementById('ep-rx').checked     = p.requires_prescription == 1;
  openModal('editProduct');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>