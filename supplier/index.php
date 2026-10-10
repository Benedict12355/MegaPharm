<?php
require_once __DIR__ . '/../includes/functions.php';

$sup_page = 'Dashboard';
$sup_active = 'dashboard';
$supUser = $_SESSION['supplier'];

// Supplier record
$supStmt = db()->prepare('SELECT * FROM suppliers WHERE user_id = ?');
$supStmt->execute([$supUser['id']]);
$supInfo = $supStmt->fetch();
$supplierId = (int)($supInfo['id'] ?? 0);

/* ---------- KPIs ---------- */
$totalPO     = (int)db()->query("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id=$supplierId")->fetchColumn();
$pendingPO   = (int)db()->query("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id=$supplierId AND status='pending'")->fetchColumn();
$processing  = (int)db()->query("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id=$supplierId AND status IN ('approved','ordered','partially_received')")->fetchColumn();
$delivered   = (int)db()->query("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id=$supplierId AND status='received'")->fetchColumn();

/* ---------- Recent POs ---------- */
$recent = db()->prepare("SELECT po.*, 
    (SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id=po.id) AS items
    FROM purchase_orders po WHERE supplier_id=?
    ORDER BY po.id DESC LIMIT 7");
$recent->execute([$supplierId]);
$recent = $recent->fetchAll();

/* ---------- Notifications ---------- */
$notifs = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 4');
$notifs->execute([$supUser['id']]);
$notifs = $notifs->fetchAll();

require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">Dashboard</h1>

<div class="stats">
  <div class="stat stat-icon-right">
    <div>
      <div class="stat-label">Total Purchase Orders</div>
      <div class="stat-value"><?= $totalPO ?></div>
      <div class="stat-foot">All purchase orders</div>
    </div>
    <div class="stat-icon-box">📄</div>
  </div>
  <div class="stat stat-icon-right">
    <div>
      <div class="stat-label">Pending Orders</div>
      <div class="stat-value"><?= $pendingPO ?></div>
      <div class="stat-foot">Waiting for confirmation</div>
    </div>
    <div class="stat-icon-box">🕐</div>
  </div>
  <div class="stat stat-icon-right">
    <div>
      <div class="stat-label">Processing Orders</div>
      <div class="stat-value"><?= $processing ?></div>
      <div class="stat-foot">Currently Processing</div>
    </div>
    <div class="stat-icon-box">⚙️</div>
  </div>
  <div class="stat stat-icon-right">
    <div>
      <div class="stat-label">Delivered Orders</div>
      <div class="stat-value"><?= $delivered ?></div>
      <div class="stat-foot">Successfully delivered</div>
    </div>
    <div class="stat-icon-box">✓</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">

  <!-- Recent POs -->
  <div class="card">
    <h3 class="card-title" style="display:flex;justify-content:space-between;align-items:center">
      Recent Purchase Orders
      <a href="<?= base_url('supplier/purchase-orders.php') ?>" style="font-size:12px">View All</a>
    </h3>
    <div class="table-wrap" style="border:0">
      <table class="data">
        <thead>
          <tr><th>PO Number</th><th>Date</th><th>Items</th><th>Amount</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php if (!$recent): ?>
          <tr><td colspan="5" class="empty">No purchase orders assigned to you yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($recent as $p):
          $cls = ['pending'=>'status-pending','approved'=>'status-processing','ordered'=>'status-processing',
                  'partially_received'=>'status-processing','received'=>'status-delivered','cancelled'=>'status-cancelled'][$p['status']] ?? '';
        ?>
          <tr>
            <td><?= e($p['purchase_order_number']) ?></td>
            <td><?= e(date('M d, Y', strtotime($p['order_date']))) ?></td>
            <td><?= (int)$p['items'] ?></td>
            <td><?= peso($p['subtotal']) ?></td>
            <td class="<?= $cls ?>"><?= e(ucfirst(str_replace('_', ' ', $p['status']))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Notifications -->
  <div class="notif-panel">
    <h3 style="margin:0 0 12px;display:flex;justify-content:space-between;align-items:center">
      Recent Notifications
      <a href="<?= base_url('supplier/notifications.php') ?>" style="font-size:12px">View All</a>
    </h3>
    <?php if (!$notifs): ?>
      <p class="text-muted">No notifications.</p>
    <?php endif; ?>
    <?php foreach ($notifs as $n): ?>
      <div class="notif-item">
        <div class="notif-icon">🔔</div>
        <div class="notif-body">
          <p class="notif-title"><?= e($n['title']) ?></p>
          <p class="notif-text"><?= e($n['message']) ?></p>
          <span class="notif-time"><?= e(date('M j, g:i A', strtotime($n['created_at']))) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>