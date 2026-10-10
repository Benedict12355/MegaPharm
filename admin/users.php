<?php
// admin/users.php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');   // ← Staff blocked here
$active = 'users';
$page_title = 'Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        try {
            db()->prepare('INSERT INTO users 
                (full_name, username, email, password_hash, role, status)
                VALUES (?,?,?,?,?,?)')
               ->execute([
                   trim($_POST['full_name']),
                   trim($_POST['username']),
                   trim($_POST['email']),
                   $hash,
                   $_POST['role'],
                   $_POST['status'],
               ]);
            audit('Add', 'Users', "Created {$_POST['username']}");
            flash('success', 'User created.');
        } catch (PDOException $e) {
            flash('error', 'Username or email already exists.');
        }

    } elseif ($act === 'update') {
        $id = (int)$_POST['id'];
        $sql = 'UPDATE users SET full_name=?, email=?, role=?, status=?';
        $params = [
            trim($_POST['full_name']),
            trim($_POST['email']),
            $_POST['role'],
            $_POST['status'],
        ];

        if (!empty($_POST['password'])) {
            $sql .= ', password_hash=?';
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id=?';
        $params[] = $id;

        db()->prepare($sql)->execute($params);
        audit('Update', 'Users', "Updated #$id");
        flash('success', 'User updated.');

    } elseif ($act === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === (int)$u['id']) {
            flash('error', 'You cannot delete your own account.');
        } else {
            db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            audit('Delete', 'Users', "Deleted #$id");
            flash('success', 'User deleted.');
        }
    }
    redirect(base_url('admin/users.php' . qs()));
}

/* ---------- FILTERS ---------- */
$q      = trim($_GET['q'] ?? '');
$role   = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

$sql = 'SELECT * FROM users WHERE 1=1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (full_name LIKE ? OR username LIKE ? OR email LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
if ($role !== '') {
    $sql .= ' AND role = ?';
    $params[] = $role;
}
if ($status !== '') {
    $sql .= ' AND status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY id DESC LIMIT 200';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$counts = [
  'total'  => (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
  'admin'  => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn(),
  'staff'  => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn(),
  'active' => (int)db()->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
];

$roles = ['admin','staff','customer','supplier'];

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
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search user..."
             value="<?= e($q) ?>" style="width:100%">
    </div>

    <select class="select" name="role" onchange="this.form.submit()">
      <option value="">All Roles</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>>
          <?= e(ucfirst($r)) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      <option value="locked"   <?= $status === 'locked'   ? 'selected' : '' ?>>Locked</option>
    </select>

    <?php if ($q || $role || $status): ?>
      <a class="btn" href="<?= base_url('admin/users.php') ?>">Reset</a>
    <?php endif; ?>

    <button class="btn btn-primary" type="button" onclick="openModal('addUser')">➕ Add User</button>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>ID</th><th>Name</th><th>Email</th><th>Role</th>
          <th>Status</th><th>Date Created</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$users): ?>
        <tr><td colspan="7" class="empty">No users match your filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($users as $usr):
        $cls = [
          'active'   => 'badge-success',
          'inactive' => 'badge-danger',
          'locked'   => 'badge-danger',
        ][$usr['status']] ?? 'badge-info';
      ?>
        <tr>
          <td>U<?= str_pad((string)$usr['id'], 3, '0', STR_PAD_LEFT) ?></td>
          <td><?= e($usr['full_name']) ?></td>
          <td><?= e($usr['email']) ?></td>
          <td><?= e(ucfirst($usr['role'])) ?></td>
          <td><span class="badge <?= $cls ?>"><?= e(ucfirst($usr['status'])) ?></span></td>
          <td><?= e(date('M j, Y', strtotime($usr['created_at']))) ?></td>
          <td class="row-actions">
            <button class="btn-icon" title="Edit"
                    onclick='editUser(<?= json_encode($usr, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete user?')">
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

<!-- ============ MODAL: ADD USER ============ -->
<div class="modal-overlay" id="addUser">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('addUser')">✕</button>
    <h3>Add New User</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="create">
      <div class="form-grid">
        <div class="field"><label>Full Name <span class="req">*</span></label>
          <input name="full_name" required></div>
        <div class="field"><label>Email <span class="req">*</span></label>
          <input type="email" name="email" required></div>
        <div class="field"><label>Username <span class="req">*</span></label>
          <input name="username" required></div>
        <div class="field"><label>Password <span class="req">*</span></label>
          <input type="password" name="password" minlength="6" required></div>
        <div class="field"><label>Role</label>
          <select name="role">
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
            <option value="customer">Customer</option>
            <option value="supplier">Supplier</option>
          </select>
        </div>
        <div class="field"><label>Status</label>
          <select name="status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('addUser')">Cancel</button>
        <button class="btn btn-primary">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ MODAL: EDIT USER ============ -->
<div class="modal-overlay" id="editUser">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('editUser')">✕</button>
    <h3>Edit User</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="update">
      <input type="hidden" name="id" id="eu-id">
      <div class="form-grid">
        <div class="field"><label>Full Name</label>
          <input name="full_name" id="eu-name" required></div>
        <div class="field"><label>Email</label>
          <input type="email" name="email" id="eu-email" required></div>
        <div class="field"><label>New Password (leave blank to keep)</label>
          <input type="password" name="password" minlength="6"></div>
        <div class="field"><label>Role</label>
          <select name="role" id="eu-role">
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
            <option value="customer">Customer</option>
            <option value="supplier">Supplier</option>
          </select>
        </div>
        <div class="field"><label>Status</label>
          <select name="status" id="eu-status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="locked">Locked</option>
          </select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('editUser')">Cancel</button>
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function editUser(u) {
  document.getElementById('eu-id').value     = u.id;
  document.getElementById('eu-name').value   = u.full_name;
  document.getElementById('eu-email').value  = u.email;
  document.getElementById('eu-role').value   = u.role;
  document.getElementById('eu-status').value = u.status;
  openModal('editUser');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>