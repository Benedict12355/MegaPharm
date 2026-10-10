<?php
// admin/customers.php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'customers';
$page_title = 'Customers';

$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

$sql = "SELECT c.*,
        (SELECT COUNT(*) FROM customer_orders WHERE customer_id=c.id) AS total_orders,
        (SELECT COUNT(*) FROM customer_orders WHERE customer_id=c.id AND status='completed') AS completed,
        (SELECT COUNT(*) FROM customer_orders WHERE customer_id=c.id AND status='pending')   AS pending,
        (SELECT COUNT(*) FROM customer_orders WHERE customer_id=c.id AND status='cancelled') AS cancelled
        FROM customers c
        WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if ($status === 'active') {
    $sql .= " AND c.user_id IS NOT NULL AND EXISTS (SELECT 1 FROM users u WHERE u.id = c.user_id AND u.status='active')";
} elseif ($status === 'inactive') {
    $sql .= " AND (c.user_id IS NULL OR EXISTS (SELECT 1 FROM users u WHERE u.id = c.user_id AND u.status<>'active'))";
}
$sql .= " ORDER BY c.id DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$totalCount = (int)db()->query("SELECT COUNT(*) FROM customers")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Customers</h1>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search by name, email, or phone..."
             value="<?= e($q) ?>" style="width:100%">
    </div>

    <select class="select" name="status" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>

    <?php if ($q || $status): ?>
      <a class="btn" href="<?= base_url('admin/customers.php') ?>">Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Customer</th>
          <th>Email</th>
          <th>Phone No.</th>
          <th>Total Orders</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$customers): ?>
        <tr><td colspan="6" class="empty">No customers match your filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= e($c['full_name']) ?></div>
            <div class="text-muted" style="font-size:11px"><?= e($c['customer_code']) ?></div>
          </td>
          <td><?= e($c['email'] ?? '—') ?></td>
          <td><?= e($c['phone'] ?? '—') ?></td>
          <td><?= (int)$c['total_orders'] ?></td>
          <td><span class="badge badge-success">Active</span></td>
          <td><button class="btn btn-sm" onclick='viewCustomer(<?= json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>View</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted" style="padding:12px 16px">
    Showing <?= count($customers) ?> of <?= $totalCount ?> customers
  </p>
</div>

<!-- ============ MODAL: CUSTOMER DETAILS ============ -->
<div class="modal-overlay" id="viewCustomerModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('viewCustomerModal')">✕</button>
    <h3>Customer Details</h3>
    <div id="customerBody"></div>
    <div class="modal-actions">
      <button class="btn btn-light" onclick="closeModal('viewCustomerModal')">Close</button>
    </div>
  </div>
</div>

<script>
function viewCustomer(c) {
  document.getElementById('customerBody').innerHTML = `
    <p><b>Customer ID:</b> ${c.customer_code}</p>
    <p><b>Full Name:</b> ${c.full_name}</p>
    <p><b>Email:</b> ${c.email || '—'}</p>
    <p><b>Phone:</b> ${c.phone || '—'}</p>
    <p><b>Delivery Address:</b> ${c.address || '—'}</p>
    <p><b>Account Status:</b> Active</p>
    <p><b>Date Registered:</b> ${new Date(c.created_at).toLocaleDateString()}</p>
    <hr>
    <h4>Order Summary</h4>
    <p>Total Orders: <b>${c.total_orders}</b></p>
    <p>Completed: <b>${c.completed}</b></p>
    <p>Pending: <b>${c.pending}</b></p>
    <p>Cancelled: <b>${c.cancelled}</b></p>
  `;
  openModal('viewCustomerModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>