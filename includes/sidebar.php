<?php
$u        = current_user();
$isAdmin  = ($u['role'] ?? '') === 'admin';

$nav = [
  ['key'=>'dashboard',  'label'=>'Dashboard',       'icon'=>'🏠', 'url'=>'admin/dashboard.php'],
  ['key'=>'products',   'label'=>'Products',        'icon'=>'📦', 'url'=>'admin/products.php'],
  ['key'=>'inventory',  'label'=>'Inventory',       'icon'=>'📋', 'url'=>'admin/inventory.php'],
  ['key'=>'expiry',     'label'=>'Expiry Alerts',   'icon'=>'⚠️', 'url'=>'admin/expiry-alerts.php'],
  ['key'=>'orders',     'label'=>'Customer Orders', 'icon'=>'🧾', 'url'=>'admin/orders.php'],
  ['key'=>'sales',      'label'=>'Sales',           'icon'=>'💡', 'url'=>'admin/sales.php'],
  ['key'=>'customers',  'label'=>'Customers',       'icon'=>'👥', 'url'=>'admin/customers.php'],
  ['key'=>'suppliers',  'label'=>'Suppliers',       'icon'=>'🏭', 'url'=>'admin/suppliers.php'],
  ['key'=>'purchase',   'label'=>'Purchase Orders', 'icon'=>'📄', 'url'=>'admin/purchase-orders.php'],
  ['key'=>'deliveries', 'label'=>'Deliveries',      'icon'=>'🚚', 'url'=>'admin/deliveries.php'],
  ['key'=>'returns',    'label'=>'Returns',         'icon'=>'↩️', 'url'=>'admin/returns.php'],
  ['key'=>'reports',    'label'=>'Reports',         'icon'=>'📊', 'url'=>'admin/reports.php'],
];

// Admin-only items
if ($isAdmin) {
    $nav[] = ['key'=>'users',  'label'=>'Users',      'icon'=>'👤', 'url'=>'admin/users.php'];
    $nav[] = ['key'=>'audit',  'label'=>'Audit Logs', 'icon'=>'📜', 'url'=>'admin/audit-logs.php'];
}
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">MEGA<br><small>We care</small></div>
    <div><div class="brand-name">MegaPharm</div><div class="brand-sub">Management Portal</div></div>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($nav as $item): ?>
      <a class="nav-item <?= $active === $item['key'] ? 'active' : '' ?>"
         href="<?= base_url($item['url']) ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <span><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
    <a class="nav-item nav-logout" href="#" onclick="confirmLogout(event)">
      <span class="nav-icon">🚪</span><span>Logout</span>
    </a>
  </nav>
</aside>

<!-- Logout Modal -->
<div class="modal-overlay" id="logoutModal">
  <div class="modal modal-sm">
    <div class="modal-icon-lg">🚪</div>
    <h3 class="text-center">Confirm Logout</h3>
    <p class="text-center text-muted">Are you sure you want to logout from the system?</p>
    <div class="modal-actions center">
      <button class="btn btn-light" onclick="closeModal('logoutModal')">Cancel</button>
      <a class="btn btn-primary" href="<?= base_url('logout.php') ?>">Logout</a>
    </div>
  </div>
</div>