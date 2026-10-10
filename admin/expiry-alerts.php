<?php
// admin/expiry-alerts.php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'expiry';
$page_title = 'Expiry Alerts';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    if (($_POST['act'] ?? '') === 'mark_disposal') {
        $bid = (int)$_POST['batch_id'];
        $batch = db()->query("SELECT * FROM inventory_batches WHERE id=$bid")->fetch();
        if ($batch) {
            db()->prepare('UPDATE inventory_batches SET quantity=0 WHERE id=?')->execute([$bid]);
            db()->prepare('INSERT INTO stock_movements 
                (product_id, batch_id, user_id, movement_type, quantity_change, reason)
                VALUES (?,?,?,?,?,?)')
               ->execute([$batch['product_id'], $bid, $u['id'], 'expired', -$batch['quantity'], 'Marked for disposal']);
            audit('Mark Disposal', 'Inventory', "Batch #$bid disposed");
            flash('success', 'Batch marked for disposal.');
        }
    }
    redirect(base_url('admin/expiry-alerts.php'));
}

$days = (int)setting('expiry_alert_days', 30);
$q      = trim($_GET['q'] ?? '');
$cat    = $_GET['cat'] ?? '';
$type   = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT p.id, p.product_name, p.product_code, p.selling_price,
               p.reorder_level, c.category_name,
               b.id AS batch_id, b.batch_number, b.expiry_date, b.quantity
        FROM inventory_batches b
        JOIN products p ON p.id = b.product_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE b.quantity > 0 AND b.expiry_date IS NOT NULL
          AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
$params = [$days];

if ($q !== '') {
    $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ?)";
    $like = "%$q%"; array_push($params, $like, $like);
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
if ($status === 'expired') {
    $sql .= " AND b.expiry_date < CURDATE()";
} elseif ($status === 'soon') {
    $sql .= " AND b.expiry_date >= CURDATE()";
}
$sql .= " ORDER BY b.expiry_date ASC LIMIT 200";

$stmt = db()->prepare($sql); $stmt->execute($params);
$alerts = $stmt->fetchAll();
$categories = db()->query('SELECT id, category_name FROM categories ORDER BY category_name')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Expiry Alerts</h1>

<div class="card">
  <form method="get" class="toolbar">
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

    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Already Expired</option>
      <option value="soon"    <?= $status === 'soon'    ? 'selected' : '' ?>>Expiring Soon</option>
    </select>

    <?php if ($q || $cat || $type || $status): ?>
      <a class="btn" href="<?= base_url('admin/expiry-alerts.php') ?>">Reset</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr><th>Product</th><th>SKU</th><th>Category</th><th>Price (₱)</th><th>Stock</th><th>Expiry</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$alerts): ?>
        <tr><td colspan="7" class="empty">No expiry alerts within <?= $days ?> days.</td></tr>
      <?php endif; ?>
      <?php foreach ($alerts as $a):
        $daysLeft = (int)((strtotime($a['expiry_date']) - time()) / 86400);
        $cls = $daysLeft < 0 ? 'status-low' : ($daysLeft <= 7 ? 'status-low' : 'status-pending');
      ?>
        <tr>
          <td><?= e($a['product_name']) ?></td>
          <td><?= e($a['product_code']) ?></td>
          <td><?= e($a['category_name'] ?? '—') ?></td>
          <td><?= peso($a['selling_price']) ?></td>
          <td><?= (int)$a['quantity'] ?></td>
          <td class="<?= $cls ?>">
            <?= e($a['expiry_date']) ?>
            <small>(<?= $daysLeft < 0 ? abs($daysLeft).'d ago' : $daysLeft.'d left' ?>)</small>
          </td>
          <td class="row-actions">
            <form method="post" onsubmit="return confirm('Mark this batch for disposal?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="act" value="mark_disposal">
              <input type="hidden" name="batch_id" value="<?= $a['batch_id'] ?>">
              <button class="btn btn-danger btn-sm">Mark Disposal</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>