<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'returns';
$page_title = 'Returns';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    if (($_POST['act'] ?? '') === 'create') {
        $num = next_number('returns','return_number','RET-');
        db()->prepare('INSERT INTO returns (return_number, order_id, customer_id, processed_by, reason, status, refund_amount, notes) VALUES (?,?,?,?,?,?,?,?)')
           ->execute([$num, (int)$_POST['order_id'] ?: null, null, $u['id'], $_POST['reason'], 'pending', 0, $_POST['notes'] ?? '']);
        audit('Create','Returns',"Created $num");
        flash('success','Return submitted.');
        redirect(base_url('admin/returns.php'));
    }
}

$returns = db()->query("SELECT r.*, c.full_name FROM returns r LEFT JOIN customers c ON c.id=r.customer_id ORDER BY r.id DESC LIMIT 50")->fetchAll();
$orders = db()->query('SELECT id, order_number FROM customer_orders ORDER BY id DESC LIMIT 100')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Returns</h1>

<div class="card">
  <div class="toolbar">
    <form class="search"><input class="input" placeholder="Search..."></form>
    <select class="select"><option>All Status</option></select>
    <input class="input" type="date">
    <button class="btn btn-primary" onclick="openModal('createReturn')">➕ Create Return</button>
  </div>
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>Return No.</th><th>Order No.</th><th>Customer</th><th>Reason</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($returns as $r): ?>
        <tr>
          <td><?= e($r['return_number']) ?></td>
          <td>#<?= e($r['order_id']) ?></td>
          <td><?= e($r['full_name'] ?? '—') ?></td>
          <td><?= e(ucfirst(str_replace('_',' ',$r['reason']))) ?></td>
          <td><span class="badge badge-info"><?= e(ucfirst($r['status'])) ?></span></td>
          <td class="row-actions"><button class="btn btn-sm">View</button><button class="btn btn-sm">Process</button></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$returns): ?><tr><td colspan="6" class="empty">No returns yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="createReturn">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('createReturn')">✕</button>
    <h3>Create Return</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="create">
      <div class="form-grid">
        <div class="field"><label>Order No.</label>
          <select name="order_id" required>
            <option value="">Select...</option>
            <?php foreach ($orders as $o): ?><option value="<?= $o['id'] ?>"><?= e($o['order_number']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Return Reason</label>
          <select name="reason">
            <option value="damaged_product">Damaged Product</option>
            <option value="wrong_item">Wrong Item</option>
            <option value="expired_product">Expired Product</option>
            <option value="customer_request">Customer Request</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="field full"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('createReturn')">Cancel</button>
        <button class="btn btn-primary">Submit Return</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>