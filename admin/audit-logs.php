<?php
// admin/audit-logs.php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');   // ← Staff blocked here
$active = 'audit';
$page_title = 'Audit Logs';

$q      = trim($_GET['q'] ?? '');
$user   = $_GET['user'] ?? '';
$role   = $_GET['role'] ?? '';
$module = $_GET['module'] ?? '';
$action = $_GET['action'] ?? '';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';

$sql = 'SELECT * FROM audit_logs WHERE 1=1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (details LIKE ? OR username_snapshot LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like);
}
if ($user !== '')   { $sql .= ' AND username_snapshot = ?'; $params[] = $user; }
if ($role !== '')   { $sql .= ' AND role_snapshot = ?';     $params[] = $role; }
if ($module !== '') { $sql .= ' AND module = ?';            $params[] = $module; }
if ($action !== '') { $sql .= ' AND action = ?';            $params[] = $action; }
if ($from !== '')   { $sql .= ' AND DATE(created_at) >= ?'; $params[] = $from; }
if ($to   !== '')   { $sql .= ' AND DATE(created_at) <= ?'; $params[] = $to; }

$sql .= ' ORDER BY id DESC LIMIT 300';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$users   = db()->query('SELECT DISTINCT username_snapshot FROM audit_logs WHERE username_snapshot IS NOT NULL ORDER BY username_snapshot')->fetchAll(PDO::FETCH_COLUMN);
$roles   = db()->query('SELECT DISTINCT role_snapshot FROM audit_logs WHERE role_snapshot IS NOT NULL ORDER BY role_snapshot')->fetchAll(PDO::FETCH_COLUMN);
$modules = db()->query('SELECT DISTINCT module FROM audit_logs ORDER BY module')->fetchAll(PDO::FETCH_COLUMN);
$actions = db()->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Audit Logs</h1>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search details or username..."
             value="<?= e($q) ?>" style="width:100%">
    </div>

    <select class="select" name="user" onchange="this.form.submit()">
      <option value="">User: All</option>
      <?php foreach ($users as $usr): ?>
        <option value="<?= e($usr) ?>" <?= $user === $usr ? 'selected' : '' ?>><?= e($usr) ?></option>
      <?php endforeach; ?>
    </select>

    <select class="select" name="role" onchange="this.form.submit()">
      <option value="">Role: All</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
      <?php endforeach; ?>
    </select>

    <select class="select" name="module" onchange="this.form.submit()">
      <option value="">Module: All</option>
      <?php foreach ($modules as $m): ?>
        <option value="<?= e($m) ?>" <?= $module === $m ? 'selected' : '' ?>><?= e($m) ?></option>
      <?php endforeach; ?>
    </select>

    <select class="select" name="action" onchange="this.form.submit()">
      <option value="">Action: All</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option>
      <?php endforeach; ?>
    </select>

    <input class="input" type="date" name="from" value="<?= e($from) ?>" onchange="this.form.submit()">
    <input class="input" type="date" name="to"   value="<?= e($to) ?>"   onchange="this.form.submit()">

    <?php if ($q || $user || $role || $module || $action || $from || $to): ?>
      <a class="btn" href="<?= base_url('admin/audit-logs.php') ?>">Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Date &amp; Time</th><th>Username</th><th>Role</th>
          <th>Action</th><th>Module</th><th>Details</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="empty">No logs match your filters.</td></tr>
      <?php endif; ?>
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
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>