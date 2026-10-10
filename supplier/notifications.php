<?php
require_once __DIR__ . '/../includes/functions.php';

$sup_page = 'Notifications';
$sup_active = 'notifications';
$supUser = $_SESSION['supplier'];

$stmt = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50');
$stmt->execute([$supUser['id']]);
$items = $stmt->fetchAll();

// Mark all read
db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$supUser['id']]);

$today = []; $yesterday = []; $earlier = [];
foreach ($items as $n) {
    $d = date('Y-m-d', strtotime($n['created_at']));
    if ($d === date('Y-m-d')) $today[] = $n;
    elseif ($d === date('Y-m-d', strtotime('-1 day'))) $yesterday[] = $n;
    else $earlier[] = $n;
}

require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">Notifications</h1>

<?php foreach (['TODAY'=>$today, 'YESTERDAY'=>$yesterday, 'EARLIER'=>$earlier] as $label=>$group): ?>
  <?php if (!$group) continue; ?>
  <h3 style="margin:22px 0 12px;font-size:14px;text-transform:uppercase;letter-spacing:.5px"><?= $label ?></h3>
  <?php foreach ($group as $n): ?>
    <div class="card" style="margin-bottom:12px;padding:18px 22px;display:flex;justify-content:space-between;align-items:flex-start">
      <div style="flex:1">
        <p style="margin:0 0 6px;font-weight:700;font-size:13px">• <?= e($n['title']) ?></p>
        <p style="margin:0;color:#64748b;font-size:13px;line-height:1.5"><?= e($n['message']) ?></p>
      </div>
      <div class="notif-dot"></div>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>

<?php if (!$items): ?>
  <div class="card" style="text-align:center;padding:40px;color:#64748b">No notifications.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>