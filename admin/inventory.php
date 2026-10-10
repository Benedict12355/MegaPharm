<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'inventory';
$page_title = 'Inventory';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'stock_in') {
        $pid = (int)$_POST['product_id'];
        $batchNo = trim($_POST['batch_number']);
        $expiry = $_POST['expiry_date'] ?: null;
        $qty = (int)$_POST['quantity'];
        $cost = (float)$_POST['cost_per_unit'];
        // upsert batch
        $b = db()->prepare('INSERT INTO inventory_batches (product_id, batch_number, expiry_date, quantity, purchase_price, received_date) VALUES (?,?,?,?,?,CURDATE())
                            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), expiry_date = VALUES(expiry_date), purchase_price = VALUES(purchase_price)');
        $b->execute([$pid, $batchNo, $expiry, $qty, $cost]);
        $bid = (int)db()->query("SELECT id FROM inventory_batches WHERE product_id=$pid AND batch_number=".db()->quote($batchNo))->fetchColumn();
        db()->prepare('INSERT INTO stock_movements (product_id, batch_id, user_id, movement_type, quantity_change, reason) VALUES (?,?,?,?,?,?)')
           ->execute([$pid, $bid, $u['id'], 'stock_in', $qty, 'Stock in via inventory']);
        audit('Stock In', 'Inventory', "Added $qty units");
        flash('success', 'Stock added.');
    } elseif ($act === 'adjust') {
        $pid = (int)$_POST['product_id'];
        $bid = (int)$_POST['batch_id'];
        $new = (int)$_POST['new_quantity'];
        $reason = $_POST['reason'] ?? 'adjustment';
        $notes = $_POST['notes'] ?? '';
        $cur = (int)db()->query("SELECT quantity FROM inventory_batches WHERE id=$bid")->fetchColumn();
        $diff = $new - $cur;
        if ($diff !== 0) {
            db()->prepare('UPDATE inventory_batches SET quantity=? WHERE id=?')->execute([$new, $bid]);
            db()->prepare('INSERT INTO stock_movements (product_id, batch_id, user_id, movement_type, quantity_change, reason, notes) VALUES (?,?,?,?,?,?,?)')
               ->execute([$pid, $bid, $u['id'], 'adjustment', $diff, $reason, $notes]);
            audit('Adjust', 'Inventory', "Adjusted batch #$bid by $diff");
        }
        flash('success', 'Stock adjusted.');
    } elseif ($act === 'add_item') {
        // same as products create
        $data = [
            'product_name' => trim($_POST['product_name']),
            'generic_name' => trim($_POST['generic_name'] ?? ''),
            'category_id' => (int)$_POST['category_id'] ?: null,
            'selling_price' => (float)$_POST['selling_price'],
            'purchase_price' => (float)($_POST['purchase_price'] ?? 0),
            'reorder_level' => (int)$_POST['reorder_level'],
        ];
        $code = next_number('products', 'product_code', 'PRD-');
        $stmt = db()->prepare('INSERT INTO products (product_code, product_name, generic_name, category_id, selling_price, purchase_price, reorder_level) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$code, $data['product_name'], $data['generic_name'], $data['category_id'], $data['selling_price'], $data['purchase_price'], $data['reorder_level']]);
        $pid = (int)db()->lastInsertId();
        $qty = (int)($_POST['quantity'] ?? 0);
        $batchNo = trim($_POST['batch_number'] ?? '') ?: ('B'.strtoupper(bin2hex(random_bytes(3))));
        $expiry = $_POST['expiry_date'] ?: null;
        if ($qty > 0) {
            db()->prepare('INSERT INTO inventory_batches (product_id, batch_number, expiry_date, quantity, purchase_price, received_date) VALUES (?,?,?,?,?,CURDATE())')
               ->execute([$pid, $batchNo, $expiry, $qty, $data['purchase_price']]);
        }
        audit('Add', 'Inventory', "Added new item: {$data['product_name']}");
        flash('success', 'Item added to inventory.');
    }
    redirect(base_url('admin/inventory.php'));
}

$search = trim($_GET['q'] ?? '');
$sql = "SELECT p.id, p.product_code, p.product_name, p.selling_price, p.reorder_level,
        (SELECT COALESCE(SUM(quantity),0) FROM inventory_batches WHERE product_id=p.id) stock,
        (SELECT MIN(expiry_date) FROM inventory_batches WHERE product_id=p.id AND quantity>0) expiry
        FROM products p WHERE p.status='active'";
$params = [];
if ($search !== '') { $sql .= " AND (p.product_name LIKE ? OR p.generic_name LIKE ? OR p.product_code LIKE ?)"; $s="%$search%"; array_push($params,$s,$s,$s); }
$sql .= " ORDER BY p.id DESC LIMIT 100";
$stmt = db()->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

$allProducts = db()->query('SELECT id, product_name FROM products WHERE status="active" ORDER BY product_name')->fetchAll();
$categories = db()->query('SELECT * FROM categories ORDER BY category_name')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Inventory</h1>

<div class="card">
  <div class="toolbar">
    <button class="btn btn-primary" onclick="openModal('addItem')">➕</button>
    <form class="search" method="get"><input class="input" name="q" placeholder="Search by name, generic, or SKU..." value="<?= e($search) ?>"></form>
    <select class="select"><option>All Categories</option></select>
    <select class="select"><option>Medicines</option></select>
    <select class="select"><option>All Status</option></select>
  </div>
  <div class="pills">
    <?php foreach ($categories as $c): ?><span class="pill"><?= e($c['category_name']) ?></span><?php endforeach; ?>
  </div>
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>Product</th><th>SKU</th><th>Price (₱)</th><th>Stock</th><th>Expiry</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty">No inventory records.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): $low = $r['stock'] <= $r['reorder_level']; ?>
        <tr onclick='openDetails(<?= json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' style="cursor:pointer">
          <td><?= e($r['product_name']) ?></td>
          <td><?= e($r['product_code']) ?></td>
          <td><?= peso($r['selling_price']) ?></td>
          <td><?= (int)$r['stock'] ?></td>
          <td><?= $r['expiry'] ? e(date('Y-m-d', strtotime($r['expiry']))) : '—' ?></td>
          <td class="<?= $low ? 'status-low' : 'status-ok' ?>"><?= $low ? 'Low Stock' : 'Normal' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="toolbar" style="margin-top:14px">
    <button class="btn" onclick="openDetailsCurrent()">View</button>
    <button class="btn" onclick="openModal('stockIn')">Stock In</button>
    <button class="btn btn-primary" onclick="openModal('adjustStock')">Adjust Stock</button>
  </div>
</div>

<!-- Add Item Modal -->
<div class="modal-overlay" id="addItem">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('addItem')">✕</button>
    <h3>Add New Item</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="add_item">
      <div class="form-grid">
        <div class="field"><label>Product Name <span class="req">*</span></label><input name="product_name" required></div>
        <div class="field"><label>Batch</label><input name="batch_number"></div>
        <div class="field"><label>Category <span class="req">*</span></label>
          <select name="category_id" required>
            <option value="">Select...</option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Quantity <span class="req">*</span></label><input name="quantity" type="number" value="0" required></div>
        <div class="field"><label>Reorder Level <span class="req">*</span></label><input name="reorder_level" type="number" value="20" required></div>
        <div class="field"><label>Purchase Price</label><input name="purchase_price" type="number" step="0.01" value="0"></div>
        <div class="field"><label>Selling Price <span class="req">*</span></label><input name="selling_price" type="number" step="0.01" value="0" required></div>
        <div class="field"><label>Expiry Date</label><input name="expiry_date" type="date"></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('addItem')">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Stock In Modal -->
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
            <?php foreach ($allProducts as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['product_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Cost per Unit</label><input name="cost_per_unit" type="number" step="0.01" value="0"></div>
        <div class="field"><label>Batch</label><input name="batch_number" placeholder="Batch No." required></div>
        <div class="field"><label>Expiry Date</label><input name="expiry_date" type="date"></div>
        <div class="field"><label>Quantity <span class="req">*</span></label><input name="quantity" type="number" min="1" required></div>
        <div class="field"><label>Supplier</label><input name="supplier" placeholder="Supplier name"></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('stockIn')">Cancel</button>
        <button class="btn btn-primary">Add Stock</button>
      </div>
    </form>
  </div>
</div>

<!-- Adjust Stock Modal -->
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
            <?php foreach ($allProducts as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['product_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>New Quantity</label><input name="new_quantity" type="number" min="0" required></div>
        <div class="field"><label>Batch</label>
          <select name="batch_id" required>
            <option value="">Select batch...</option>
            <?php foreach (db()->query('SELECT id, product_id, batch_number FROM inventory_batches ORDER BY id DESC LIMIT 100') as $b): ?>
              <option value="<?= $b['id'] ?>">#<?= $b['id'] ?> — <?= e($b['batch_number']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Reason</label>
          <select name="reason">
            <option value="damaged">Damaged / Missing / Correction</option>
            <option value="expired">Expired</option>
            <option value="adjustment">Manual Adjustment</option>
          </select>
        </div>
        <div class="field full"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('adjustStock')">Cancel</button>
        <button class="btn btn-primary">Save Adjustment</button>
      </div>
    </form>
  </div>
</div>

<!-- Details Modal -->
<div class="modal-overlay" id="detailsModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('detailsModal')">✕</button>
    <h3>Details</h3>
    <div id="detailsBody"></div>
  </div>
</div>

<script>
function openDetails(p){
  const html = `
    <p><b>Product:</b> ${p.product_name}</p>
    <p><b>SKU:</b> ${p.product_code}</p>
    <p><b>Current Stock:</b> ${p.stock} &nbsp; <b>Reorder Level:</b> ${p.reorder_level}</p>
    <p><b>Status:</b> ${p.stock <= p.reorder_level ? '<span class="status-low">LOW STOCK</span>' : '<span class="status-ok">IN STOCK</span>'}</p>
  `;
  document.getElementById('detailsBody').innerHTML = html;
  openModal('detailsModal');
}
function openDetailsCurrent(){ /* placeholder */ openModal('detailsModal'); document.getElementById('detailsBody').innerHTML='<p class="text-muted">Select a product row.</p>'; }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>