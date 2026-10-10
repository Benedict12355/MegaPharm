<?php
require_once __DIR__ . '/../includes/functions.php';

$sup_page = 'Deliveries';
$sup_active = 'deliveries';
$supUser = $_SESSION['supplier'];

$supStmt = db()->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$supStmt->execute([$supUser['id']]);
$supplierId = (int)$supStmt->fetchColumn();

$q = trim($_GET['q'] ?? '');
$sql = "SELECT d.*, po.purchase_order_number,
    (SELECT COALESCE(SUM(quantity_received),0) FROM delivery_items WHERE delivery_id=d.id) AS items
    FROM deliveries d
    JOIN purchase_orders po ON po.id = d.purchase_order_id
    WHERE po.supplier_id = ?";
$params = [$supplierId];
if ($q !== '') {
    $sql .= " AND (d.delivery_number LIKE ? OR po.purchase_order_number LIKE ?)";
    $like = "%$q%"; array_push($params, $like, $like);
}
$sql .= " ORDER BY d.id DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$deliveries = $stmt->fetchAll();

$statusCls = [
    'pending'   => 'status-scheduled',
    'partial'   => 'status-in-transit',
    'delivered' => 'status-delivered',
    'completed' => 'status-delivered',
    'cancelled' => 'status-cancelled',
];

require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">Deliveries</h1>

<div class="card">
  <form method="get" class="toolbar">
    <div class="search grow">
      <input class="input" name="q" placeholder="Search delivery..."
             value="<?= e($q) ?>" style="width:100%">
    </div>
  </form>

  <div class="table-wrap" style="border:0">
    <table class="data">
      <thead>
        <tr><th>Delivery ID</th><th>PO Number</th><th>Delivery Date</th><th>Items</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php if (!$deliveries): ?>
        <tr><td colspan="6" class="empty">No deliveries yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($deliveries as $d):
        $cls = $statusCls[$d['status']] ?? '';
      ?>
        <tr>
          <td><?= e($d['delivery_number']) ?></td>
          <td><?= e($d['purchase_order_number']) ?></td>
          <td><?= e(date('M d, Y', strtotime($d['delivery_date']))) ?></td>
          <td><?= (int)$d['items'] ?></td>
          <td class="<?= $cls ?>"><?= e(ucfirst($d['status'])) ?></td>
          <td><button class="btn btn-sm" onclick='viewDelivery(<?= json_encode($d, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>View</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted" style="padding:12px 16px">
    Showing 1 to <?= count($deliveries) ?> of <?= count($deliveries) ?> product
  </p>
</div>

<!-- Delivery Details Modal -->
<div class="modal-overlay" id="viewDeliveryModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('viewDeliveryModal')">✕</button>
    <h3>Delivery Details</h3>
    <div id="delBody"></div>
  </div>
</div>

<script>
function viewDelivery(d) {
  document.getElementById('delBody').innerHTML = `
    <p><b>Delivery No.:</b> ${d.delivery_number}</p>
    <p><b>Purchase Order:</b> ${d.purchase_order_number}</p>
    <p><b>Delivery Date:</b> ${d.delivery_date}</p>
    <p><b>Status:</b> ${d.status}</p>
    <p><b>Items:</b> ${d.items}</p>
    <p><b>Notes:</b> ${d.notes || '—'}</p>
  `;
  openModal('viewDeliveryModal');
}
</script>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>