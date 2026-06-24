<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
//require_once __DIR__ . '/../app/Messaging.php';
use DevHire\Auth;
use DevHire\Database;
use DevHire\Messaging;

Auth::requireLogin('user');
$user = Auth::user();
$pdo = Database::getConnection();
$userId = $user['id'];

$hasSender = false;
try { $pdo->query("SELECT sender_id FROM messages LIMIT 1"); $hasSender = true; } catch(\Throwable $e){}

$threadJobId = isset($_GET['job']) ? (int)$_GET['job'] : 0;
$threadCompanyId = isset($_GET['company']) ? (int)$_GET['company'] : 0;

// send reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $threadJobId && $threadCompanyId) {
    $msg = trim($_POST['message'] ?? '');
    if ($msg !== '') {
        Messaging::sendMessage($threadJobId, $userId, $threadCompanyId, $userId, 'user', $msg, false);
    }
    header("Location: messages.php?job=$threadJobId&company=$threadCompanyId");
    exit;
}

// list threads (group by job+company)
$threads = $pdo->prepare("
SELECT m.job_id, m.company_id, j.title, c.company_name, MAX(m.created_at) as last_at, COUNT(m.id) as msg_count
FROM messages m
JOIN jobs j ON j.id = m.job_id
JOIN companies c ON c.id = m.company_id
WHERE m.user_id = ?
GROUP BY m.job_id, m.company_id
ORDER BY last_at DESC
");
$threads->execute([$userId]);
$threads = $threads->fetchAll();

if (!$threadJobId && $threads) {
    $threadJobId = (int)$threads[0]['job_id'];
    $threadCompanyId = (int)$threads[0]['company_id'];
}

// mark as read when opening
if ($threadJobId && $threadCompanyId) {
    Messaging::markThreadReadUser($threadJobId, $userId, $threadCompanyId);
}

// load thread
$messages = [];
$jobInfo = null;
if ($threadJobId) {
    $stmt = $pdo->prepare("SELECT m.* FROM messages m WHERE m.job_id = ? AND m.user_id = ? AND m.company_id = ? ORDER BY m.created_at ASC");
    $stmt->execute([$threadJobId, $userId, $threadCompanyId]);
    $messages = $stmt->fetchAll();
    $j = $pdo->prepare("SELECT j.title, c.company_name FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.id=?");
    $j->execute([$threadJobId]);
    $jobInfo = $j->fetch();
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages - devHire</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.msg-layout{display:grid;grid-template-columns:280px 1fr;gap:18px}
.thread-list{background:#fff;border:1px solid #ddd;border-radius:10px;padding:12px;max-height:620px;overflow:auto}
.thread-item{padding:10px;border-radius:8px;cursor:pointer;margin-bottom:6px;border:1px solid #eee;position:relative}
.thread-item.active{background:#f2efff;border-color:#d2c8f5}
.msg-box{background:#fff;border:1px solid #ddd;border-radius:10px;display:flex;flex-direction:column;height:620px}
.msg-history{flex:1;overflow:auto;padding:16px}
.msg-bubble{max-width:70%;padding:8px 12px;border-radius:10px;margin-bottom:10px;font-size:13px}
.msg-me{background:#221b3a;color:#fff;margin-left:auto}
.msg-them{background:#f0f0f0}
.msg-input{border-top:1px solid #ddd;padding:12px;display:flex;gap:8px}
.msg-input textarea{flex:1;padding:8px;border:1px solid #ccc;border-radius:8px;resize:vertical}
.unread-dot{background:#b3263a;color:#fff;border-radius:99px;padding:1px 6px;font-size:10px;margin-left:4px}
@media(max-width:820px){.msg-layout{grid-template-columns:1fr}}
</style>
</head><body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Messages</h1>
  <p class="welcome-sub">Your conversations with companies</p>
  <div class="msg-layout">
    <div class="thread-list">
      <h4 style="font-size:13px;margin-bottom:8px">Conversations</h4>
      <?php if(!$threads): ?><p style="color:#777;font-size:12px">No messages yet. Apply to a job first.</p><?php endif;?>
      <?php foreach($threads as $t): 
        $active = $t['job_id']==$threadJobId && $t['company_id']==$threadCompanyId;
        $unread = Messaging::threadUnreadCountUser((int)$t['job_id'], $userId, (int)$t['company_id']);
      ?>
        <a href="messages.php?job=<?= $t['job_id'] ?>&company=<?= $t['company_id'] ?>">
          <div class="thread-item <?= $active?'active':'' ?>">
            <strong style="font-size:13px"><?= htmlspecialchars($t['title']) ?><?php if($unread>0): ?><span class="unread-dot"><?= $unread ?></span><?php endif; ?></strong><br>
            <span style="font-size:11px;color:#666"><?= htmlspecialchars($t['company_name']) ?> · <?= $t['msg_count'] ?> msgs</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="msg-box">
      <?php if($jobInfo): ?>
      <div style="padding:12px 16px;border-bottom:1px solid #eee;font-size:13px"><strong><?= htmlspecialchars($jobInfo['title']) ?></strong> · <?= htmlspecialchars($jobInfo['company_name']) ?></div>
      <div class="msg-history" id="msgHistory">
        <?php foreach($messages as $m): 
          $isMe = $hasSender ? ($m['sender_type'] === 'user' && ($m['sender_id'] ?? null) == $userId) : true;
        ?>
          <div class="msg-bubble <?= $isMe ? 'msg-me' : 'msg-them' ?>">
            <?= nl2br(htmlspecialchars($m['message'])) ?><br>
            <small style="opacity:.7;font-size:10px"><?= htmlspecialchars($m['created_at']) ?><?= !empty($m['is_application']) ? ' · Application' : '' ?></small>
          </div>
        <?php endforeach; ?>
      </div>
      <form method="post" class="msg-input">
        <textarea name="message" rows="2" placeholder="Type a message..." required></textarea>
        <button class="btn btn-primary" type="submit">Send</button>
      </form>
      <script>document.getElementById('msgHistory')?.scrollTo(0, 99999);</script>
      <?php else: ?>
      <div style="padding:40px;color:#777;text-align:center">Select a conversation</div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>
</body></html>
