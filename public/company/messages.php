<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Messaging.php';
use DevHire\Auth;
use DevHire\Database;
use DevHire\Messaging;

Auth::requireLogin('company');
$user = Auth::user();
$pdo = Database::getConnection();
$companyId = Auth::getCompanyId($user['id']);
if (!$companyId) die('No company profile');

$hasSender = false;
try { $pdo->query("SELECT sender_id FROM messages LIMIT 1"); $hasSender = true; } catch(\Throwable $e){}

// check hires table exists
$hasHires = false;
try { $pdo->query("SELECT 1 FROM hires LIMIT 1"); $hasHires = true; } catch(\Throwable $e){}

// check rejected column
$hasRejected = false;
try { $pdo->query("SELECT is_rejected FROM messages LIMIT 1"); $hasRejected = true; } catch(\Throwable $e){}

$threadJobId = isset($_GET['job']) ? (int)$_GET['job'] : 0;
$threadUserId = isset($_GET['user']) ? (int)$_GET['user'] : 0;

$errorMsg = '';

// hire / reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hire_user'])) {
    $hj = (int)$_POST['hire_job'];
    $hu = (int)$_POST['hire_user'];
    if ($hasHires && $hj && $hu) {
        try {
            $pdo->prepare('INSERT INTO hires (job_id, user_id, company_id) VALUES (?,?,?)')->execute([$hj, $hu, $companyId]);
            header("Location: messages.php?job=$hj&user=$hu&hired=1");
            exit;
        } catch (\Throwable $e) {
            $errorMsg = 'Hire failed: ' . $e->getMessage();
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_user'])) {
    $hj = (int)$_POST['reject_job'];
    $hu = (int)$_POST['reject_user'];
    $reason = trim($_POST['reject_reason'] ?? 'Thank you for your application. Unfortunately we will not proceed.');
    try {
        $msgId = Messaging::sendMessage($hj, $hu, $companyId, $user['id'], 'company', $reason, false);
        if ($hasRejected) {
            $pdo->prepare('UPDATE messages SET is_rejected = 1 WHERE id = ?')->execute([$msgId]);
        }
        header("Location: messages.php?job=$hj&user=$hu&rejected=1");
        exit;
    } catch (\Throwable $e) {
        $errorMsg = 'Reject failed: ' . htmlspecialchars($e->getMessage());
        $threadJobId = $hj;
        $threadUserId = $hu;
    }
}

// send reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $threadJobId && $threadUserId) {
    $msg = trim($_POST['message'] ?? '');
    if ($msg !== '') {
        try {
            Messaging::sendMessage($threadJobId, $threadUserId, $companyId, $user['id'], 'company', $msg, false);
            header("Location: messages.php?job=$threadJobId&user=$threadUserId");
            exit;
        } catch (\Throwable $e) {
            $errorMsg = 'Send failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// threads list
$threads = $pdo->prepare("
SELECT m.job_id, m.user_id, j.title, u.first_name, u.last_name, MAX(m.created_at) as last_at, COUNT(m.id) as msg_count
FROM messages m
JOIN jobs j ON j.id=m.job_id
JOIN users u ON u.id = m.user_id
WHERE m.company_id = ?
GROUP BY m.job_id, m.user_id
ORDER BY last_at DESC
");
$threads->execute([$companyId]);
$threads = $threads->fetchAll();

if (!$threadJobId && $threads) {
    $threadJobId = (int)$threads[0]['job_id'];
    $threadUserId = (int)$threads[0]['user_id'];
}

if ($threadJobId && $threadUserId) {
    Messaging::markThreadReadCompany($threadJobId, $threadUserId, $companyId);
}

$messages = [];
$threadInfo = null;
$isHired = false;
$isRejected = false;
if ($threadJobId && $threadUserId) {
    $stmt = $pdo->prepare("SELECT m.* FROM messages m WHERE m.job_id = ? AND m.user_id = ? AND m.company_id = ? ORDER BY m.created_at ASC");
    $stmt->execute([$threadJobId, $threadUserId, $companyId]);
    $messages = $stmt->fetchAll();
    $q = $pdo->prepare("SELECT j.title, u.first_name, u.last_name, u.email FROM jobs j, users u WHERE j.id=? AND u.id=?");
    $q->execute([$threadJobId, $threadUserId]);
    $threadInfo = $q->fetch();
    if ($hasHires) {
        $h = $pdo->prepare('SELECT 1 FROM hires WHERE job_id=? AND user_id=?');
        $h->execute([$threadJobId, $threadUserId]);
        $isHired = (bool)$h->fetchColumn();
    }
    if ($hasRejected) {
        $r = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE job_id=? AND user_id=? AND company_id=? AND sender_type="company" AND is_rejected=1');
        $r->execute([$threadJobId, $threadUserId, $companyId]);
        $isRejected = ((int)$r->fetchColumn()) > 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages - devHire Company</title>
<link rel="stylesheet" href="/devhire/assets/css/style.css">
<style>
.msg-layout{display:grid;grid-template-columns:280px 1fr;gap:18px}
.thread-list{background:#fff;border:1px solid #ddd;border-radius:10px;padding:12px;max-height:620px;overflow:auto}
.thread-item{padding:10px;border-radius:8px;cursor:pointer;margin-bottom:6px;border:1px solid #eee}
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
  <p class="welcome-sub">Reply to applicants</p>
  <?php if($errorMsg): ?><div class="error-list" style="margin-bottom:12px"><?= $errorMsg ?></div><?php endif; ?>
  <?php if(!$hasSender): ?>
    <div class="error-list" style="margin-bottom:12px">Run <code>database_upgrade_full.sql</code> for full messaging features.</div>
  <?php endif; ?>
  <div class="msg-layout">
    <div class="thread-list">
      <h4 style="font-size:13px;margin-bottom:8px">Conversations</h4>
      <?php foreach($threads as $t): 
        $active = $t['job_id']==$threadJobId && $t['user_id']==$threadUserId;
        $unread = Messaging::threadUnreadCountCompany((int)$t['job_id'], (int)$t['user_id'], $companyId);
      ?>
        <a href="messages.php?job=<?= $t['job_id'] ?>&user=<?= $t['user_id'] ?>">
          <div class="thread-item <?= $active?'active':'' ?>">
            <strong style="font-size:13px"><?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?><?php if($unread>0): ?><span class="unread-dot"><?= $unread ?></span><?php endif; ?></strong><br>
            <span style="font-size:11px;color:#666"><?= htmlspecialchars($t['title']) ?> · <?= $t['msg_count'] ?> msgs</span>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if(empty($threads)): ?><p style="color:#777;font-size:12px">No messages yet.</p><?php endif; ?>
    </div>
    <div class="msg-box">
      <?php if($threadInfo): ?>
      <div style="padding:12px 16px;border-bottom:1px solid #eee;font-size:13px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div><strong><?= htmlspecialchars($threadInfo['first_name'].' '.$threadInfo['last_name']) ?></strong> · <?= htmlspecialchars($threadInfo['title']) ?><br><small style="color:#666"><?= htmlspecialchars($threadInfo['email']) ?></small></div>
        <div style="display:flex;gap:6px">
          <?php if($isHired): ?>
            <span class="status-pill status-active">Hired ✓</span>
          <?php elseif($isRejected): ?>
            <span class="status-pill status-rejected">Rejected</span>
          <?php else: ?>
            <?php if($hasHires): ?>
            <form method="post" onsubmit="return confirm('Hire <?= htmlspecialchars($threadInfo['first_name'], ENT_QUOTES) ?> for this position?')">
              <input type="hidden" name="hire_job" value="<?= $threadJobId ?>">
              <input type="hidden" name="hire_user" value="<?= $threadUserId ?>">
              <button class="btn btn-primary btn-sm" name="hire_user" value="1" type="submit">Hire</button>
            </form>
            <?php endif; ?>
            <?php if($hasRejected): ?>
            <button class="btn btn-sm btn-outline" style="color:#b3263a;border-color:#b3263a" onclick="document.getElementById('rejectBox').style.display = document.getElementById('rejectBox').style.display==='none' ? 'block' : 'none';return false">Reject</button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <div id="rejectBox" style="display:none;padding:10px 16px;border-bottom:1px solid #eee;background:#fff5f5">
        <form method="post">
          <input type="hidden" name="reject_job" value="<?= $threadJobId ?>">
          <input type="hidden" name="reject_user" value="<?= $threadUserId ?>">
          <label style="font-size:12px">Rejection message to applicant:</label>
          <textarea name="reject_reason" rows="2" style="width:100%;padding:6px;border:1px solid #ccc;border-radius:6px;margin:4px 0">Thank you for your application for <?= htmlspecialchars($threadInfo['title']) ?>. Unfortunately we will not be moving forward at this time.</textarea>
          <button class="btn btn-sm" style="background:#b3263a;color:#fff;border:none;border-radius:6px;padding:6px 12px" name="reject_user" value="1">Send Rejection</button>
          <button type="button" onclick="document.getElementById('rejectBox').style.display='none'" class="btn btn-sm btn-outline">Cancel</button>
        </form>
      </div>
      <?php if(isset($_GET['hired'])): ?><div class="success-box" style="margin:10px 16px">User hired successfully!</div><?php endif; ?>
      <?php if(isset($_GET['rejected'])): ?><div class="error-list" style="margin:10px 16px">Application rejected. The user will see "Rejected" status.</div><?php endif; ?>
      <div class="msg-history" id="msgHistory">
        <?php foreach($messages as $m): 
          $isMe = isset($m['sender_type']) ? $m['sender_type'] === 'company' : false;
        ?>
          <div class="msg-bubble <?= $isMe ? 'msg-me' : 'msg-them' ?>">
            <?= nl2br(htmlspecialchars($m['message'])) ?><br>
            <small style="opacity:.7;font-size:10px"><?= htmlspecialchars($m['created_at']) ?><?= !empty($m['is_rejected']) ? ' · REJECTED' : '' ?></small>
          </div>
        <?php endforeach; ?>
      </div>
      <form method="post" class="msg-input">
        <textarea name="message" rows="2" placeholder="Type a reply..." required></textarea>
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
