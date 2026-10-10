<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');
$active = 'audit';
$page_title = 'Audit Logs';

$rows = db()->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Audit Logs</h1>

<div class="card">
  <div class="toolbar">
    <form class="search"><input class="input" placeholder="Search..."></form>
    <input class="input" type="date"><input class="input" type="date">
    <select class="select"><option>User: All</option></select>
    <select class="select"><option>Role: All</option></select>
    <select class="select"><option>Module: All</option></select>
    <select class="select"><option>Actions: All</option></select>
    <button class="btn btn-primary">Filter</button>
    <button class="btn">Reset</button>
  </div>
</div>

<div class="card">
  <h3 class="card-title">Audit Logs</h3>
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>Date &amp; Time</th><th>Username</th><th>Role</th><th>Action</th><th>Module</th><th>Details</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e(date('M d h:i A', strtotime($r['created_at']))) ?></td>
          <td><?= e($r['username_snapshot']) ?></td>
          <td><?= e(ucfirst($r['role_snapshot'] ?? '')) ?></td>
          <td><?= e($r['action']) ?></td>
          <td><?= e($r['module']) ?></td>
          <td><?= e($r['details']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty">No logs yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>