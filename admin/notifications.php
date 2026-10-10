<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$active = 'notifications';
$page_title = 'Notifications';

// Mark all as read
db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$u['id']]);

$rows = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50');
$rows->execute([$u['id']]);
$items = $rows->fetchAll();

$today = []; $yesterday = []; $earlier = [];
foreach ($items as $n) {
    $d = date('Y-m-d', strtotime($n['created_at']));
    if ($d === date('Y-m-d')) $today[] = $n;
    elseif ($d === date('Y-m-d', strtotime('-1 day'))) $yesterday[] = $n;
    else $earlier[] = $n;
}
$links = ['low_stock'=>['admin/inventory.php','View Inventory'],'expiry'=>['admin/inventory.php','View Product'],'customer_order'=>['admin/orders.php','View Order'],'delivery'=>['admin/deliveries.php','View Delivery'],'return'=>['admin/returns.php','View Return'],'system'=>['admin/dashboard.php','Open']];

require_once __DIR__ . '/../includes/header.php';
?>
<h1 style="display:none">Notifications</h1>
<div class="card">
  <?php foreach (['TODAY'=>$today,'YESTERDAY'=>$yesterday,'EARLIER'=>$earlier] as $label=>$group): ?>
    <?php if (!$group) continue; ?>
    <h3 class="card-title" style="margin-top:10px"><?= $label ?></h3>
    <?php foreach ($group as $n):
      [$url,$btn] = $links[$n['notification_type']] ?? ['admin/dashboard.php','Open'];
    ?>
      <div class="card" style="display:flex;justify-content:space-between;align-items:center;background:#f8fbff;border-left:4px solid var(--blue)">
        <div>
          <p style="margin:0 0 4px;font-weight:700;text-transform:uppercase;font-size:12px"><?= e($n['title']) ?></p>
          <p style="margin:0;color:var(--muted);font-size:13px"><?= e($n['message']) ?></p>
        </div>
        <a class="btn btn-primary btn-sm" href="<?= base_url($url) ?>"><?= e($btn) ?></a>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <?php if (!$items): ?><div class="empty">No notifications.</div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>