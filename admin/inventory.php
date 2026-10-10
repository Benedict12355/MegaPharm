<?php
// admin/inventory.php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'inventory';
$page_title = 'Inventory';

/* ---------- POST ACTIONS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'stock_in') {
        $pid     = (int)$_POST['product_id'];
        $batchNo = trim($_POST['batch_number']);
        $expiry  = $_POST['expiry_date'] ?: null;
        $qty     = (int)$_POST['quantity'];
        $cost    = (float)$_POST['cost_per_unit'];

        $b = db()->prepare('INSERT INTO inventory_batches 
            (product_id, batch_number, expiry_date, quantity, purchase_price, received_date)
            VALUES (?,?,?,?,?,CURDATE())
            ON DUPLICATE KEY UPDATE 
              quantity = quantity + VALUES(quantity),
              expiry_date = VALUES(expiry_date),
              purchase_price = VALUES(purchase_price)');
        $b->execute([$pid, $batchNo, $expiry, $qty, $cost]);

        $bid = (int)db()->query("SELECT id FROM inventory_batches 
            WHERE product_id=$pid AND batch_number=".db()->quote($batchNo))->fetchColumn();

        db()->prepare('INSERT INTO stock_movements 
            (product_id, batch_id, user_id, movement_type, quantity_change, reason)
            VALUES (?,?,?,?,?,?)')
           ->execute([$pid, $bid, $u['id'], 'stock_in', $qty, 'Stock in via inventory']);

        audit('Stock In', 'Inventory', "Added $qty units to product #$pid");
        flash('success', 'Stock added successfully.');

    } elseif ($act === 'adjust') {
        $pid    = (int)$_POST['product_id'];
        $bid    = (int)$_POST['batch_id'];
        $new    = (int)$_POST['new_quantity'];
        $reason = $_POST['reason'] ?? 'adjustment';
        $notes  = $_POST['notes'] ?? '';

        $cur  = (int)db()->query("SELECT quantity FROM inventory_batches WHERE id=$bid")->fetchColumn();
        $diff = $new - $cur;

        if ($diff !== 0) {
            db()->prepare('UPDATE inventory_batches SET quantity=? WHERE id=?')->execute([$new, $bid]);
            db()->prepare('INSERT INTO stock_movements 
                (product_id, batch_id, user_id, movement_type, quantity_change, reason, notes)
                VALUES (?,?,?,?,?,?,?)')
               ->execute([$pid, $bid, $u['id'], 'adjustment', $diff, $reason, $notes]);
            audit('Adjust', 'Inventory', "Adjusted batch #$bid by $diff");
        }
        flash('success', 'Stock adjusted.');

    } elseif ($act === 'add_item') {
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
            'active',
        ]);
        $pid = (int)db()->lastInsertId();

        $qty     = (int)($_POST['quantity'] ?? 0);
        $batchNo = trim($_POST['batch_number'] ?? '') ?: ('B'.strtoupper(bin2hex(random_bytes(3))));
        $expiry  = $_POST['expiry_date'] ?: null;

        if ($qty > 0) {
            db()->prepare('INSERT INTO inventory_batches 
                (product_id, batch_number, expiry_date, quantity, purchase_price, received_date)
                VALUES (?,?,?,?,?,CURDATE())')
               ->execute([$pid, $batchNo, $expiry, $qty, (float)($_POST['purchase_price'] ?? 0)]);
        }
        audit('Add', 'Inventory', "Added new item: {$_POST['product_name']}");
        flash('success', 'Item added to inventory.');
    }
    redirect(base_url('admin/inventory.php' . qs()));
}

/* ---------- FILTERS ---------- */
$q      = trim($_GET['q'] ?? '');
$cat    = $_GET['cat']    ?? '';
$type   = $_GET['type']   ?? '';
$age    = $_GET['age']    ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT p.id, p.product_code, p.product_name, p.generic_name, p.brand,
               p.selling_price, p.purchase_price, p.reorder_level, p.age_group,
               p.requires_prescription, p.status,
               c.category_name,
               (SELECT COALESCE(SUM(quantity),0) FROM inventory_batches WHERE product_id=p.id) AS stock,
               (SELECT MIN(expiry_date) FROM inventory_batches WHERE product_id=p.id AND quantity>0) AS expiry
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
$rows = $stmt->fetchAll();

/* ---------- SUPPORT DATA FOR MODALS ---------- */
$allProducts = db()->query('SELECT id, product_code, product_name FROM products WHERE status="active" ORDER BY product_name')->fetchAll();
$allBatches  = db()->query('SELECT id, product_id, batch_number, quantity FROM inventory_batches ORDER BY id DESC LIMIT 300')->fetchAll();
$categories  = db()->query('SELECT id, category_name FROM categories ORDER BY category_name')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<h1 style="display:none">Inventory</h1>

<!-- 🔽 FILTER BAR -->
<div class="card">
  <form method="get" class="toolbar">
    <button type="button" class="btn btn-primary" onclick="openModal('addItem')">➕</button>

    <div class="search grow">
      <input class="input" name="q" placeholder="Search by name, generic, or SKU..."
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

    <select class="select" name="age" onchange="this.form.submit()">
      <option value="">All Ages</option>
      <option value="adult"    <?= $age === 'adult'    ? 'selected' : '' ?>>Adults</option>
      <option value="kid"      <?= $age === 'kid'      ? 'selected' : '' ?>>Kids</option>
      <option value="infant"   <?= $age === 'infant'   ? 'selected' : '' ?>>Infants</option>
      <option value="all_ages" <?= $age === 'all_ages' ? 'selected' : '' ?>>All Ages</option>
    </select>

    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>

    <?php if ($q || $cat || $type || $age || $status): ?>
      <a class="btn" href="<?= base_url('admin/inventory.php') ?>">Reset</a>
    <?php endif; ?>
  </form>

  <!-- 🔽 CATEGORY PILLS (sortable) -->
  <div class="pills">
    <a class="pill <?= $cat === '' ? 'active' : '' ?>" href="<?= base_url('admin/inventory.php' . qs(['cat' => ''])) ?>">All</a>
    <?php foreach ($categories as $c): ?>
      <a class="pill <?= $cat == $c['id'] ? 'active' : '' ?>"
         href="<?= base_url('admin/inventory.php' . qs(['cat' => $c['id']])) ?>">
        <?= e($c['category_name']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- 🔽 TABLE -->
<div class="card">
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Product</th>
          <th>SKU</th>
          <th>Category</th>
          <th>Age</th>
          <th>Price (₱)</th>
          <th>Stock</th>
          <th>Expiry</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="empty">No inventory records match your filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r):
        $st = stock_status((int)$r['stock'], (int)$r['reorder_level']);
        $statusLabel = $st === 'low' ? 'Low Stock' : ($st === 'out' ? 'Out of Stock' : 'Normal');
        $statusCls   = $st === 'normal' ? 'status-ok' : 'status-low';
      ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= e($r['product_name']) ?></div>
            <?php if ($r['brand']): ?><div class="text-muted" style="font-size:11px"><?= e($r['brand']) ?></div><?php endif; ?>
          </td>
          <td><?= e($r['product_code']) ?></td>
          <td><?= e($r['category_name'] ?? '—') ?></td>
          <td><?= e(ucfirst(str_replace('_',' ', $r['age_group']))) ?></td>
          <td><?= peso($r['selling_price']) ?></td>
          <td><?= (int)$r['stock'] ?> <small class="text-muted">/ <?= (int)$r['reorder_level'] ?></small></td>
          <td><?= $r['expiry'] ? e(date('Y-m-d', strtotime($r['expiry']))) : '—' ?></td>
          <td class="<?= $statusCls ?>"><?= $statusLabel ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="toolbar" style="margin-top:14px">
    <button class="btn" onclick="openModal('stockIn')">Stock In</button>
    <button class="btn btn-primary" onclick="openModal('adjustStock')">Adjust Stock</button>
  </div>
</div>

<!-- ============ MODAL: ADD ITEM ============ -->
<div class="modal-overlay" id="addItem">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('addItem')">✕</button>
    <h3>Add New Item</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="add_item">
      <div class="form-grid">

        <div class="field"><label>Product Name <span class="req">*</span></label>
          <input name="product_name" required></div>

        <div class="field"><label>Generic Name</label>
          <input name="generic_name"></div>

        <div class="field"><label>Brand</label>
          <input name="brand"></div>

        <div class="field"><label>Category <span class="req">*</span></label>
          <select name="category_id" required>
            <option value="">Select...</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- ✨ NEW: Age Group -->
        <div class="field"><label>Age Group <span class="req">*</span></label>
          <select name="age_group" required>
            <option value="all_ages">All Ages</option>
            <option value="adult">Adults</option>
            <option value="kid">Kids</option>
            <option value="infant">Infants</option>
          </select>
        </div>

        <!-- ✨ NEW: Supplier from DB -->
        <div class="field"><label>Supplier</label>
          <select name="supplier_id">
            <option value="">Select supplier...</option>
            <?php foreach (db()->query('SELECT id, supplier_name FROM suppliers WHERE status="active" ORDER BY supplier_name') as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field"><label>Batch No.</label>
          <input name="batch_number" placeholder="Auto if blank"></div>

        <div class="field"><label>Quantity <span class="req">*</span></label>
          <input name="quantity" type="number" value="0" required></div>

        <div class="field"><label>Reorder Level <span class="req">*</span></label>
          <input name="reorder_level" type="number" value="20" required></div>

        <div class="field"><label>Purchase Price (₱)</label>
          <input name="purchase_price" type="number" step="0.01" value="0"></div>

        <div class="field"><label>Selling Price (₱) <span class="req">*</span></label>
          <input name="selling_price" type="number" step="0.01" value="0" required></div>

        <div class="field"><label>Expiry Date</label>
          <input name="expiry_date" type="date"></div>

        <div class="field full">
          <label>
            <input type="checkbox" name="requires_prescription" value="1" style="width:auto"> 
            Requires prescription
          </label>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('addItem')">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ MODAL: STOCK IN ============ -->
<div class="modal-overlay" id="stockIn">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('stockIn')">✕</button>
    <h3>Stock In</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="stock_in">
      <div class="form-grid">

        <div class="field"><label>Product</label>
          <select name="product_id" required>
            <option value="">Select...</option>
            <?php foreach ($allProducts as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['product_name']) ?> (<?= e($p['product_code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field"><label>Cost per Unit (₱)</label>
          <input name="cost_per_unit" type="number" step="0.01" value="0"></div>

        <div class="field"><label>Batch No. <span class="req">*</span></label>
          <input name="batch_number" required></div>

        <div class="field"><label>Expiry Date</label>
          <input name="expiry_date" type="date"></div>

        <div class="field"><label>Quantity <span class="req">*</span></label>
          <input name="quantity" type="number" min="1" required></div>

        <!-- ✨ NEW: Supplier from DB -->
        <div class="field"><label>Supplier</label>
          <select name="supplier_id">
            <option value="">Select supplier...</option>
            <?php foreach (db()->query('SELECT id, supplier_name FROM suppliers WHERE status="active" ORDER BY supplier_name') as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('stockIn')">Cancel</button>
        <button class="btn btn-primary">Add Stock</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ MODAL: ADJUST STOCK ============ -->
<div class="modal-overlay" id="adjustStock">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('adjustStock')">✕</button>
    <h3>Adjust Inventory</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="adjust">
      <div class="form-grid">

        <div class="field"><label>Product</label>
          <select name="product_id" required>
            <option value="">Select...</option>
            <?php foreach ($allProducts as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['product_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field"><label>Batch</label>
          <select name="batch_id" required>
            <option value="">Select batch...</option>
            <?php foreach ($allBatches as $b): ?>
              <option value="<?= $b['id'] ?>">#<?= $b['id'] ?> — <?= e($b['batch_number']) ?> (qty <?= (int)$b['quantity'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field"><label>New Quantity</label>
          <input name="new_quantity" type="number" min="0" required></div>

        <div class="field"><label>Reason</label>
          <select name="reason">
            <option value="damaged">Damaged / Missing / Correction</option>
            <option value="expired">Expired</option>
            <option value="adjustment">Manual Adjustment</option>
          </select>
        </div>

        <div class="field full"><label>Notes</label>
          <textarea name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('adjustStock')">Cancel</button>
        <button class="btn btn-primary">Save Adjustment</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>