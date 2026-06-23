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

// check hires table
$hasHires = false;
try { $pdo->query("SELECT 1 FROM hires LIMIT 1"); $hasHires = true; } catch(\Throwable $e){}

// check rejected column
$hasRejected = false;
try { $pdo->query("SELECT is_rejected FROM messages LIMIT 1"); $hasRejected = true; } catch(\Throwable $e){}

// check sender column
$hasSender = false;
try { $pdo->query("SELECT sender_id FROM messages LIMIT 1"); $hasSender = true; } catch(\Throwable $e){}

$errorMsg = '';

// Hire / Reject actions
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['hire_job'], $_POST['hire_user']) && $hasHires) {
    $hj = (int)$_POST['hire_job'];
    $hu = (int)$_POST['hire_user'];
    try {
        $pdo->prepare('INSERT INTO hires (job_id, user_id, company_id) VALUES (?,?,?)')->execute([$hj, $hu, $companyId]);
        header('Location: applications.php?hired=1');
        exit;
    } catch (\Throwable $e) {
        $errorMsg = 'Hire failed: ' . htmlspecialchars($e->getMessage());
    }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reject_job'], $_POST['reject_user']) && $hasRejected) {
    $hj = (int)$_POST['reject_job'];
    $hu = (int)$_POST['reject_user'];
    try {
        $msgId = Messaging::sendMessage($hj, $hu, $companyId, $user['id'], 'company', 'Thank you for your application. We will not proceed.', false);
        $pdo->prepare('UPDATE messages SET is_rejected = 1 WHERE id = ?')->execute([$msgId]);
        header('Location: applications.php?rejected=1');
        exit;
    } catch (\Throwable $e) {
        $errorMsg = 'Reject failed: ' . htmlspecialchars($e->getMessage());
    }
}

// Only show ONE row per applicant per job – the first application message
if ($hasSender) {
    $sql = "
    SELECT MIN(m.id) as msg_id, MIN(m.created_at) as created_at,
           j.title as job_title, j.id as job_id,
           u.first_name, u.last_name, u.email, u.id as user_id,
           (SELECT message FROM messages m2 WHERE m2.job_id=j.id AND m2.user_id=u.id AND m2.company_id=? ORDER BY m2.created_at ASC LIMIT 1) as message
    FROM messages m
    JOIN jobs j ON j.id = m.job_id
    JOIN users u ON u.id = m.user_id
    WHERE m.company_id = ? AND m.is_application = 1
    GROUP BY j.id, u.id
    ORDER BY created_at DESC";
    $apps = $pdo->prepare($sql);
    $apps->execute([$companyId, $companyId]);
} else {
    $sql = "
    SELECT MIN(m.id) as msg_id, MIN(m.created_at) as created_at,
           j.title as job_title, j.id as job_id,
           u.first_name, u.last_name, u.email, u.id as user_id,
           (SELECT message FROM messages m2 WHERE m2.job_id=j.id AND m2.user_id=u.id AND m2.company_id=? ORDER BY m2.created_at ASC LIMIT 1) as message
    FROM messages m
    JOIN jobs j ON j.id = m.job_id
    JOIN users u ON u.id = m.user_id
    WHERE m.company_id = ?
    GROUP BY j.id, u.id
    ORDER BY created_at DESC";
    $apps = $pdo->prepare($sql);
    $apps->execute([$companyId, $companyId]);
}
$applications = $apps->fetchAll();

// fetch hired / rejected status
$hiredMap = [];
$rejectedMap = [];
if ($applications) {
    if ($hasHires) {
        $h = $pdo->prepare('SELECT job_id, user_id FROM hires WHERE company_id = ?');
        $h->execute([$companyId]);
        foreach ($h as $row) { $hiredMap[$row['job_id'].'_'.$row['user_id']] = true; }
    }
    if ($hasRejected) {
        $r = $pdo->query("SELECT DISTINCT job_id, user_id FROM messages WHERE company_id = $companyId AND sender_type='company' AND is_rejected=1");
        foreach ($r as $row) { $rejectedMap[$row['job_id'].'_'.$row['user_id']] = true; }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Applications - devHire</title>
<link rel="stylesheet" href="/devhire/assets/css/style.css"></head>
<body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
<h1>Applications</h1>
<p class="welcome-sub">Applicants who applied to your jobs</p>
<?php if($errorMsg): ?><div class="error-list" style="max-width:800px;margin-bottom:12px"><?= $errorMsg ?></div><?php endif; ?>
<?php if(isset($_GET['hired'])): ?><div class="success-box" style="max-width:800px;margin-bottom:12px">User hired!</div><?php endif; ?>
<?php if(isset($_GET['rejected'])): ?><div class="error-list" style="max-width:800px;margin-bottom:12px">Application rejected.</div><?php endif; ?>
<div class="card">
<table class="table">
<tr><th>Date</th><th>Job</th><th>Applicant</th><th>Email</th><th>Application message</th><th>Status / Actions</th></tr>
<?php foreach($applications as $a): 
  $key = $a['job_id'].'_'.$a['user_id'];
  $isHired = isset($hiredMap[$key]);
  $isRejected = isset($rejectedMap[$key]);
?>
<tr>
<td><?= htmlspecialchars(substr($a['created_at'],0,16)) ?></td>
<td><?= htmlspecialchars($a['job_title']) ?></td>
<td><?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?></td>
<td><?= htmlspecialchars($a['email']) ?></td>
<td style="max-width:260px"><?= htmlspecialchars(mb_strimwidth($a['message'] ?? '',0,80,'…')) ?></td>
<td style="white-space:nowrap">
  <a class="btn btn-sm btn-outline" href="messages.php?job=<?= $a['job_id'] ?>&user=<?= $a['user_id'] ?>">Message</a>
  <?php if($isHired): ?>
    <span class="status-pill status-active">Hired ✓</span>
  <?php elseif($isRejected): ?>
    <span class="status-pill status-rejected">Rejected</span>
  <?php else: ?>
    <?php if($hasHires): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('Hire <?= htmlspecialchars($a['first_name'], ENT_QUOTES) ?>?')">
      <input type="hidden" name="hire_job" value="<?= $a['job_id'] ?>">
      <input type="hidden" name="hire_user" value="<?= $a['user_id'] ?>">
      <button class="btn btn-sm btn-primary">Hire</button>
    </form>
    <?php endif; ?>
    <?php if($hasRejected): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('Reject this applicant?')">
      <input type="hidden" name="reject_job" value="<?= $a['job_id'] ?>">
      <input type="hidden" name="reject_user" value="<?= $a['user_id'] ?>">
      <button class="btn btn-sm btn-outline" style="color:#b3263a;border-color:#b3263a">Reject</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php if(empty($applications)): ?><p style="color:#777">No applications yet.</p><?php endif; ?>
</div>
</div>
</div>
</body></html>
