<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'expiry';
$page_title = 'Expiry Alerts';

$days = (int)setting('expiry_alert_days', 30);
$rows = db()->prepare("SELECT p.id, p.product_name, p.product_code, p.selling_price,
        b.id AS batch_id, b.batch_number, b.expiry_date, b.quantity
    FROM inventory_batches b JOIN products p ON p.id = b.product_id
    WHERE b.quantity > 0 AND b.expiry_date IS NOT NULL
      AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ORDER BY b.expiry_date ASC LIMIT 100");
$rows->execute([$days]);
$alerts = $rows->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    if (($_POST['act'] ?? '') === 'mark_disposal') {
        $bid = (int)$_POST['batch_id'];
        $batch = db()->query("SELECT * FROM inventory_batches WHERE id=$bid")->fetch();
        if ($batch) {
            db()->prepare('UPDATE inventory_batches SET quantity=0 WHERE id=?')->execute([$bid]);
            db()->prepare('INSERT INTO stock_movements (product_id, batch_id, user_id, movement_type, quantity_change, reason) VALUES (?,?,?,?,?,?)')
               ->execute([$batch['product_id'], $bid, $u['id'], 'expired', -$batch['quantity'], 'Marked for disposal']);
            audit('Mark Disposal', 'Inventory', "Batch #$bid");
            flash('success', 'Batch marked for disposal.');
        }
        redirect(base_url('admin/expiry-alerts.php'));
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Expiry Alerts</h1>

<div class="card">
  <div class="toolbar">
    <form class="search"><input class="input" placeholder="Search by name, generic, or SKU..."></form>
    <select class="select"><option>All Categories</option></select>
    <select class="select"><option>All Status</option></select>
  </div>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>Product</th><th>SKU</th><th>Price (₱)</th><th>Stock</th><th>Expiry</th><th></th></tr></thead>
      <tbody>
      <?php if (!$alerts): ?><tr><td colspan="6" class="empty">No expiry alerts within <?= $days ?> days.</td></tr><?php endif; ?>
      <?php foreach ($alerts as $a):
        $daysLeft = (int)((strtotime($a['expiry_date']) - time()) / 86400);
      ?>
        <tr>
          <td><?= e($a['product_name']) ?></td>
          <td><?= e($a['product_code']) ?></td>
          <td><?= peso($a['selling_price']) ?></td>
          <td><?= (int)$a['quantity'] ?></td>
          <td><?= e($a['expiry_date']) ?> <small class="text-muted">(<?= $daysLeft ?>d)</small></td>
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