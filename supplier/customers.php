<?php
require_once __DIR__ . '/../includes/functions.php';

$sup_page = 'Customers';
$sup_active = 'customers';

$q = trim($_GET['q'] ?? '');

$sql = "SELECT c.*, 
    (SELECT COUNT(*) FROM customer_orders WHERE customer_id=c.id) AS orders
    FROM customers c WHERE 1=1";
$params = [];
if ($q !== '') {
    $sql .= " AND (c.full_name LIKE ? OR c.customer_code LIKE ?)";
    $like = "%$q%"; array_push($params, $like, $like);
}
$sql .= " ORDER BY c.full_name ASC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">Customers</h1>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search customers..."
             value="<?= e($q) ?>" style="width:100%">
    </div>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr>
          <th>Customer ID</th><th>Contact Person</th><th>Contact Number</th>
          <th>Location</th><th>Orders</th><th>Status</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$customers): ?>
        <tr><td colspan="7" class="empty">No customers yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td><?= e($c['customer_code']) ?></td>
          <td><?= e($c['full_name']) ?></td>
          <td><?= e($c['phone'] ?? '09XX-XXX-XXXX') ?></td>
          <td><?= e($c['address'] ? explode(',', $c['address'])[1] ?? 'Calapan City' : 'Calapan City') ?></td>
          <td><?= (int)$c['orders'] ?></td>
          <td class="status-delivered">Active</td>
          <td><button class="btn btn-sm" onclick='viewCustomer(<?= json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>View</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted" style="padding:12px 16px">
    Showing 1 to <?= count($customers) ?> of <?= count($customers) ?> customers
  </p>
</div>

<div class="modal-overlay" id="viewCustomerModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('viewCustomerModal')">✕</button>
    <h3>Customer Details</h3>
    <div id="custBody"></div>
  </div>
</div>

<script>
function viewCustomer(c) {
  document.getElementById('custBody').innerHTML = `
    <p><b>Customer ID:</b> ${c.customer_code}</p>
    <p><b>Name:</b> ${c.full_name}</p>
    <p><b>Email:</b> ${c.email || '—'}</p>
    <p><b>Phone:</b> ${c.phone || '—'}</p>
    <p><b>Address:</b> ${c.address || '—'}</p>
    <p><b>Total Orders:</b> ${c.orders}</p>
  `;
  openModal('viewCustomerModal');
}
</script>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>