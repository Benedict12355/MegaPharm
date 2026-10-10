<?php
require_once __DIR__ . '/../includes/functions.php';

$custUser = $_SESSION['customer'] ?? null;
if (!$custUser) redirect(base_url('customer/login.php'));

$stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$stmt->execute([$custUser['id']]);
$items = $stmt->fetchAll();

$today = []; $yesterday = []; $earlier = [];
foreach ($items as $n) {
    $d = date('Y-m-d', strtotime($n['created_at']));
    if ($d === date('Y-m-d')) $today[] = $n;
    elseif ($d === date('Y-m-d', strtotime('-1 day'))) $yesterday[] = $n;
    else $earlier[] = $n;
}

$cust_page = 'Notifications';
$cust_active = '';
require_once __DIR__ . '/../customer-includes/header.php';
?>
<h1 style="display:none">Notifications</h1>
<h2 style="font-size:20px;margin:0 0 18px">Notifications</h2>

<?php foreach (['TODAY'=>$today, 'YESTERDAY'=>$yesterday, 'EARLIER'=>$earlier] as $label=>$group): ?>
  <?php if (!$group) continue; ?>
  <h3 style="margin:22px 0 12px;font-size:14px;text-transform:uppercase;letter-spacing:.5px"><?= $label ?></h3>
  <?php foreach ($group as $n): ?>
    <div class="form-card" style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:center">
      <div>
        <p style="margin:0 0 4px;font-weight:700"><?= e($n['title']) ?></p>
        <p style="margin:0;color:#64748b;font-size:13px"><?= e($n['message']) ?></p>
      </div>
      <small class="text-muted"><?= e(date('M j, g:i A', strtotime($n['created_at']))) ?></small>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>

<?php if (!$items): ?>
  <div class="form-card" style="text-align:center;padding:40px;color:#64748b">No notifications yet.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../customer-includes/footer.php'; ?>