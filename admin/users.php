<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');
$active = 'users';
$page_title = 'Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';
    if ($act === 'create') {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        try {
            db()->prepare('INSERT INTO users (full_name, username, email, password_hash, role, status) VALUES (?,?,?,?,?,?)')
               ->execute([$_POST['full_name'], $_POST['username'], $_POST['email'], $hash, $_POST['role'], $_POST['status']]);
            audit('Add','Users',"Created {$_POST['username']}");
            flash('success','User created.');
        } catch (PDOException $e) {
            flash('error','Username or email already exists.');
        }
    } elseif ($act === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === $u['id']) { flash('error','You cannot delete your own account.'); }
        else {
            db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            audit('Delete','Users',"Deleted #$id");
            flash('success','User deleted.');
        }
    }
    redirect(base_url('admin/users.php'));
}

$users = db()->query('SELECT * FROM users ORDER BY id DESC')->fetchAll();
$counts = [
  'total'  => count($users),
  'admin'  => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn(),
  'staff'  => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn(),
  'active' => (int)db()->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
];

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Users</h1>

<div class="stats">
  <div class="stat"><div class="stat-label">Total Users</div><div class="stat-value"><?= $counts['total'] ?></div></div>
  <div class="stat"><div class="stat-label">Admin</div><div class="stat-value"><?= $counts['admin'] ?></div></div>
  <div class="stat"><div class="stat-label">Staff</div><div class="stat-value"><?= $counts['staff'] ?></div></div>
  <div class="stat"><div class="stat-label">Active Users</div><div class="stat-value"><?= $counts['active'] ?></div></div>
</div>

<div class="card">
  <div class="toolbar">
    <h3 class="card-title" style="flex:1;margin:0">Users List</h3>
    <button class="btn btn-primary" onclick="openModal('addUser')">➕ Add User</button>
  </div>
  <div class="toolbar">
    <form class="search"><input class="input" placeholder="Search user..."></form>
    <select class="select"><option>All Roles</option></select>
  </div>
  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Date Created</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $usr): ?>
        <tr>
          <td>U<?= str_pad((string)$usr['id'], 3, '0', STR_PAD_LEFT) ?></td>
          <td><?= e($usr['full_name']) ?></td>
          <td><?= e($usr['email']) ?></td>
          <td><?= e(ucfirst($usr['role'])) ?></td>
          <td><span class="badge <?= $usr['status']==='active' ? 'badge-success' : 'badge-danger' ?>"><?= e(ucfirst($usr['status'])) ?></span></td>
          <td><?= e(date('M j, Y', strtotime($usr['created_at']))) ?></td>
          <td class="row-actions">
            <form method="post" onsubmit="return confirm('Delete user?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="act" value="delete">
              <input type="hidden" name="id" value="<?= $usr['id'] ?>">
              <button class="btn-icon danger">🗑️</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="addUser">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('addUser')">✕</button>
    <h3>Add New User</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="create">
      <div class="form-grid">
        <div class="field"><label>Full Name</label><input name="full_name" required></div>
        <div class="field"><label>Email</label><input type="email" name="email" required></div>
        <div class="field"><label>Username</label><input name="username" required></div>
        <div class="field"><label>Password</label><input type="password" name="password" required minlength="6"></div>
        <div class="field"><label>Role</label>
          <select name="role">
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
            <option value="customer">Customer</option>
            <option value="supplier">Supplier</option>
          </select>
        </div>
        <div class="field"><label>Status</label>
          <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('addUser')">Cancel</button>
        <button class="btn btn-primary">Create User</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>