<?php
require_once __DIR__ . '/../includes/functions.php';

$sup_page = 'Messages';
$sup_active = 'messages';
$supUser = $_SESSION['supplier'];

// Static conversations (no table needed)
$conversations = [
    ['id' => 1, 'name' => 'MegaPharm Purchasing', 'dept' => 'Purchasing Department',
     'preview' => 'Please confirm PO-2026-001.', 'time' => '2:20 PM',
     'messages' => [
        ['from' => 'them', 'text' => 'Good afternoon. Please confirm if PO-2026-001 can be processed.', 'time' => '2:15 PM'],
        ['from' => 'me',   'text' => 'Good afternoon. Yes, we can process PO-2026-001.', 'time' => '3:15 PM'],
        ['from' => 'them', 'text' => 'Thank you. Please proceed with the order and follow the scheduled delivery date.', 'time' => '2:15 PM'],
     ]],
    ['id' => 2, 'name' => 'MegaPharm Logistics', 'dept' => 'Logistics',
     'preview' => 'Delivery DEL-002 is scheduled.', 'time' => '11:45 AM',
     'messages' => [
        ['from' => 'them', 'text' => 'Delivery DEL-002 is scheduled for tomorrow.', 'time' => '11:45 AM'],
     ]],
    ['id' => 3, 'name' => 'MegaPharm Accounting', 'dept' => 'Accounting',
     'preview' => 'Payment PAY-2026-002 is processing.', 'time' => 'Oct 08',
     'messages' => [
        ['from' => 'them', 'text' => 'Payment PAY-2026-002 is processing.', 'time' => 'Oct 08'],
     ]],
    ['id' => 4, 'name' => 'MegaPharm Support', 'dept' => 'Support',
     'preview' => 'Your supplier account has been updated.', 'time' => 'Oct 07',
     'messages' => [
        ['from' => 'them', 'text' => 'Your supplier account has been updated.', 'time' => 'Oct 07'],
     ]],
];

$activeId = (int)($_GET['conv'] ?? 1);
$active = null;
foreach ($conversations as $c) if ($c['id'] === $activeId) $active = $c;
if (!$active) $active = $conversations[0];

require_once __DIR__ . '/../supplier-includes/header.php';
?>
<h1 style="display:none">Messages</h1>

<div class="chat-wrap">
  <aside class="chat-side">
    <h3>Conversations</h3>
    <?php foreach ($conversations as $c): ?>
      <a class="chat-conv <?= $c['id'] === $active['id'] ? 'active' : '' ?>"
         href="?conv=<?= $c['id'] ?>" style="display:block;text-decoration:none;color:inherit">
        <p class="conv-name"><?= e($c['name']) ?></p>
        <p class="conv-msg"><?= e($c['preview']) ?></p>
        <p class="conv-time"><?= e($c['time']) ?></p>
      </a>
    <?php endforeach; ?>
  </aside>

  <section class="chat-main">
    <div class="chat-header">
      <h3><?= e($active['name']) ?></h3>
      <p><?= e($active['dept']) ?></p>
    </div>

    <div class="chat-body">
      <?php foreach ($active['messages'] as $m): ?>
        <div class="chat-msg <?= $m['from'] === 'me' ? 'out' : 'in' ?>">
          <?= e($m['text']) ?>
          <div class="chat-time"><?= e($m['time']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <form class="chat-input" method="post" onsubmit="event.preventDefault(); alert('Message sending is not implemented in this demo.');">
      <input type="text" placeholder="Type your message...">
      <button type="submit">➤</button>
    </form>
  </section>
</div>

<?php require_once __DIR__ . '/../supplier-includes/footer.php'; ?>