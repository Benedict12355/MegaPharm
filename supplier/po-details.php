<?php
require_once __DIR__ . '/../includes/functions.php';

$supUser = $_SESSION['supplier'];

$supStmt = db()->prepare('SELECT id FROM suppliers WHERE user_id = ?');
$supStmt->execute([$supUser['id']]);
$supplierId = (int)$supStmt->fetchColumn();

$poId = (int)($_GET['id'] ?? 0);

$po = db()->prepare('SELECT * FROM purchase_orders WHERE id = ? AND supplier_id = ?');
$po->execute([$poId, $supplierId]);
$po = $po->fetch();
if (!$po) { flash('error', 'Purchase Order not found.'); redirect(base_url('supplier/purchase-orders.php')); }

$lines = db()->prepare('SELECT poi.*, p.product_name
    FROM purchase_order_items poi
    JOIN products p ON p.id = poi.product_id
    WHERE poi.purchase_order_id = ?');
$lines->execute([$poId]);
$lines = $lines->fetchAll();

/* ---------- Confirm / Reject ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'confirm') {
        db()->prepare("UPDATE purchase_orders 
            SET status='approved', confirmed_at=NOW(), confirmed_by=? 
            WHERE id=?")
           ->execute([$supUser['id'], $poId]);
        audit('Confirm', 'Supplier PO', "Confirmed {$po['purchase_order_number']}");
        notify_admins('delivery', 'PO Confirmed', 
            "Supplier confirmed PO {$po['purchase_order_number']}", 'purchase_order', $poId);
        flash('success', 'Purchase Order confirmed.');
        redirect(base_url('supplier/po-details.php?id=' . $poId));

    } elseif ($act === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        db()->prepare("UPDATE purchase_orders 
            SET status='cancelled', rejection_reason=? 
            WHERE id=?")
           ->execute([$reason, $poId]);
        audit('Reject', 'Supplier PO', "Rejected {$po['purchase_order_number']}: $reason");
        notify_admins('delivery', 'PO Rejected',
            "Supplier rejected PO {$po['purchase_order_number']}", 'purchase_order', $poId);
        flash('success', 'Purchase Order rejected.');
        redirect(base_url('supplier/purchase-orders.php'));
    }
}

$sup_page = 'Purchase Order Details';
$sup_active = 'purchase';
require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">PO Details</h1>

<p style="margin:0 0 14px">
  <a href="<?= base_url('supplier/purchase-orders.php') ?>" style="text-decoration:none;color:#2563eb">
    ← Purchase Order Details
  </a>
</p>

<div class="po-details-card">
  <h3>Purchase Order Details</h3>
  <p><b>PO Number:</b> <?= e($po['purchase_order_number']) ?></p>
  <p><b>Order Date:</b> <?= e(date('F j, Y', strtotime($po['order_date']))) ?></p>

  <table class="po-lines">
    <thead>
      <tr><th>Product</th><th>Qty</th><th>Unit Cost</th><th>Subtotal</th></tr>
    </thead>
    <tbody>
    <?php foreach ($lines as $l): ?>
      <tr>
        <td><?= e($l['product_name']) ?></td>
        <td><?= (int)$l['quantity_ordered'] ?></td>
        <td><?= peso($l['unit_cost']) ?></td>
        <td><?= peso($l['quantity_ordered'] * $l['unit_cost']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="po-totals">
    <div>Subtotal: <b><?= peso($po['subtotal']) ?></b></div>
    <div>Delivery: <b>₱0.00</b></div>
    <div class="grand">Total: <b><?= peso($po['subtotal']) ?></b></div>
  </div>

  <?php if ($po['status'] === 'pending'): ?>
    <form method="post" style="display:contents">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <div class="po-actions">
        <button type="submit" name="act" value="confirm" class="btn btn-primary" style="padding:11px 26px">
          Confirm Order
        </button>
        <button type="button" class="btn" style="padding:11px 26px;color:#dc2626;border-color:#dc2626"
                onclick="openModal('rejectModal')">Reject Order</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('rejectModal')">✕</button>
    <h3>Reject Purchase Order</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="reject">
      <div class="field">
        <label>Reason for Rejection</label>
        <textarea name="reason" rows="3" required></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-light" onclick="closeModal('rejectModal')">Cancel</button>
        <button class="btn btn-primary" style="background:#dc2626;border-color:#dc2626">Reject Order</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>