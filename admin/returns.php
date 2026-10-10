<?php
// admin/returns.php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'returns';
$page_title = 'Returns';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $num = next_number('returns', 'return_number', 'RET-');
        db()->prepare('INSERT INTO returns 
            (return_number, order_id, customer_id, processed_by, reason, status, refund_amount, notes)
            VALUES (?,?,?,?,?,?,?,?)')
           ->execute([
               $num,
               (int)$_POST['order_id'] ?: null,
               (int)($_POST['customer_id'] ?? 0) ?: null,
               $u['id'],
               $_POST['reason'],
               'pending',
               0,
               $_POST['notes'] ?? '',
           ]);
        audit('Create', 'Returns', "Created $num");
        flash('success', 'Return submitted.');

    } elseif ($act === 'approve') {
        $id = (int)$_POST['id'];
        db()->prepare("UPDATE returns SET status='approved' WHERE id=?")->execute([$id]);
        audit('Approve', 'Returns', "Approved #$id");
        flash('success', 'Return approved.');

    } elseif ($act === 'reject') {
        $id = (int)$_POST['id'];
        db()->prepare("UPDATE returns SET status='rejected' WHERE id=?")->execute([$id]);
        audit('Reject', 'Returns', "Rejected #$id");
        flash('success', 'Return rejected.');

    } elseif ($act === 'complete') {
        $id = (int)$_POST['id'];
        db()->prepare("UPDATE returns SET status='completed' WHERE id=?")->execute([$id]);
        audit('Complete', 'Returns', "Completed #$id");
        flash('success', 'Return completed.');
    }
    redirect(base_url('admin/returns.php' . qs()));
}

/* ---------- FILTERS ---------- */
$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';

$sql = "SELECT r.*, c.full_name AS customer_name, o.order_number
        FROM returns r
        LEFT JOIN customers c ON c.id = r.customer_id
        LEFT JOIN customer_orders o ON o.id = r.order_id
        WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (r.return_number LIKE ? OR c.full_name LIKE ? OR o.order_number LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
if ($status !== '') {
    $sql .= " AND r.status = ?";
    $params[] = $status;
}
if ($from !== '') { $sql .= " AND DATE(r.return_date) >= ?"; $params[] = $from; }
if ($to   !== '') { $sql .= " AND DATE(r.return_date) <= ?"; $params[] = $to; }

$sql .= " ORDER BY r.id DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$returns = $stmt->fetchAll();

$orders = db()->query('SELECT id, order_number FROM customer_orders ORDER BY id DESC LIMIT 200')->fetchAll();
$statusList = ['pending','approved','rejected','completed'];

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Returns</h1>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search Return / Customer / Order..."
             value="<?= e($q) ?>" style="width:100%">
    </div>

    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">All Status</option>
      <?php foreach ($statusList as $st): ?>
        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>>
          <?= e(ucfirst($st)) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input class="input" type="date" name="from" value="<?= e($from) ?>" onchange="this.form.submit()">
    <input class="input" type="date" name="to"   value="<?= e($to) ?>"   onchange="this.form.submit()">

    <?php if ($q || $status || $from || $to): ?>
      <a class="btn" href="<?= base_url('admin/returns.php') ?>">Reset</a>
    <?php endif; ?>

    <button class="btn btn-primary" type="button" onclick="openModal('createReturn')">➕ Create Return</button>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Return No.</th>
          <th>Order No.</th>
          <th>Customer</th>
          <th>Reason</th>
          <th>Refund</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$returns): ?>
        <tr><td colspan="7" class="empty">No returns match your filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($returns as $r):
        $cls = [
          'pending'   => 'badge-pending',
          'approved'  => 'badge-info',
          'rejected'  => 'badge-danger',
          'completed' => 'badge-success',
        ][$r['status']] ?? 'badge-info';
      ?>
        <tr>
          <td><?= e($r['return_number']) ?></td>
          <td><?= e($r['order_number'] ?? '—') ?></td>
          <td><?= e($r['customer_name'] ?? '—') ?></td>
          <td><?= e(ucfirst(str_replace('_', ' ', $r['reason']))) ?></td>
          <td><?= peso($r['refund_amount']) ?></td>
          <td><span class="badge <?= $cls ?>"><?= e(ucfirst($r['status'])) ?></span></td>
          <td class="row-actions">
            <?php if ($r['status'] === 'pending'): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="act" value="approve">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn btn-sm" title="Approve">✓ Approve</button>
              </form>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="act" value="reject">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn btn-sm" title="Reject">✕ Reject</button>
              </form>
            <?php elseif ($r['status'] === 'approved'): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="act" value="complete">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn btn-sm btn-primary">Complete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ MODAL: CREATE RETURN ============ -->
<div class="modal-overlay" id="createReturn">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('createReturn')">✕</button>
    <h3>Create Return</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="create">
      <div class="form-grid">
        <div class="field full"><label>Order No. <span class="req">*</span></label>
          <select name="order_id" required>
            <option value="">Select...</option>
            <?php foreach ($orders as $o): ?>
              <option value="<?= $o['id'] ?>"><?= e($o['order_number']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field full"><label>Reason <span class="req">*</span></label>
          <select name="reason" required>
            <option value="damaged_product">Damaged Product</option>
            <option value="wrong_item">Wrong Item</option>
            <option value="expired_product">Expired Product</option>
            <option value="customer_request">Customer Request</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="field full"><label>Notes</label>
          <textarea name="notes" rows="3"></textarea></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('createReturn')">Cancel</button>
        <button class="btn btn-primary">Submit Return</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>