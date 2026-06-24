<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Messaging.php';
use DevHire\Auth;
use DevHire\Database;
use DevHire\Messaging;

Auth::requireLogin('user');
$user = Auth::user();
$pdo = Database::getConnection();

// Handle apply
$applyMsg = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['apply_job_id'])) {
    $jobId = (int)$_POST['apply_job_id'];
    $msg = trim($_POST['message'] ?? 'I am interested in this position.');
    // prevent duplicate applications
    if (Messaging::hasUserApplied($jobId, $user['id'])) {
        $applyMsg = 'already_applied';
    } else {
        $stmt = $pdo->prepare('SELECT company_id FROM jobs WHERE id=?');
        $stmt->execute([$jobId]);
        $companyId = $stmt->fetchColumn();
        if ($companyId) {
            Messaging::sendMessage($jobId, $user['id'], (int)$companyId, $user['id'], 'user', $msg, true);
            $applyMsg = 'ok';
        }
    }
}

// Filters
$q = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? 'all'; // all / active / pending / closed
$where = '1=1';
$params = [];
if ($q) { $where .= ' AND j.title LIKE ?'; $params[] = "%$q%"; }
if ($category) { $where .= ' AND j.category_id = ?'; $params[] = $category; }
if ($statusFilter === 'active') { $where .= ' AND j.is_approved=1 AND j.is_active=1'; }
elseif ($statusFilter === 'pending') { $where .= ' AND j.is_approved=0'; }
elseif ($statusFilter === 'closed') { $where .= ' AND j.is_approved=1 AND j.is_active=0'; }

$sql = "SELECT j.*, c.company_name, cat.name as category_name 
FROM jobs j 
JOIN companies c ON c.id=j.company_id
JOIN job_categories cat ON cat.id=j.category_id
WHERE $where ORDER BY j.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$categories = $pdo->query('SELECT * FROM job_categories ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jobs - devHire</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Welcome Back!<br>Hi, <?= htmlspecialchars($user['first_name']) ?></h1>
  <p class="welcome-sub">Here are jobs recommended for you and the status of your application</p>

  <div class="jobs-layout">
    <div class="filter-panel">
      <h4>Advanced search</h4>
      <form method="get">
        <div class="form-group"><label>Category</label>
          <select name="category"><option value="">Any</option>
          <?php foreach($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= ($category==$cat['id']?'selected':'') ?>><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Status</label>
          <select name="status">
            <option value="all" <?= $statusFilter==='all'?'selected':'' ?>>All</option>
            <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
            <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending approval</option>
            <option value="closed" <?= $statusFilter==='closed'?'selected':'' ?>>Closed</option>
          </select>
        </div>
        <div class="form-group"><label>Search title</label><input type="text" name="q" value="<?= htmlspecialchars($q) ?>"></div>
        <button class="btn btn-primary btn-block" type="submit">Search</button>
        <a href="jobs.php" class="btn btn-outline btn-block" style="margin-top:8px">Reset Filters</a>
      </form>
    </div>

    <div class="job-list">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px">
        <h4>Job listing</h4>
        <input id="jobSearchLive" type="text" placeholder="Search" style="padding:6px 10px; border:1px solid #ccc; border-radius:8px; font-size:12px">
      </div>
      <?php if ($applyMsg === 'ok'): ?><div class="success-box">Application sent! Check Messages for replies.</div><?php endif; ?>
      <?php if ($applyMsg === 'already_applied'): ?><div class="error-list">You have already applied to this job. Check Messages to continue the conversation.</div><?php endif; ?>
      <?php foreach($jobs as $job): 
        $isActive = $job['is_approved'] && $job['is_active'];
        $isPending = !$job['is_approved'];
        $isClosed = $job['is_approved'] && !$job['is_active'];
        $statusLabel = $isActive ? 'Active' : ($isPending ? 'Pending' : 'Closed');
        $statusClass = $isActive ? 'status-active' : ($isPending ? 'status-in_progress' : 'status-closed');
        $alreadyApplied = Messaging::hasUserApplied((int)$job['id'], $user['id']);
      ?>
      <div class="job-item">
        <div>
          <strong><?= htmlspecialchars($job['title']) ?> <span class="status-pill <?= $statusClass ?>" style="margin-left:6px; font-weight:400"><?= $statusLabel ?></span></strong>
          <div class="meta"><?= htmlspecialchars($job['category_name']) ?> · <?= htmlspecialchars($job['company_name']) ?> · Deadline: <?= htmlspecialchars($job['application_deadline'] ?? 'Open') ?></div>
        </div>
        <div class="job-actions">
          <?php if ($isActive): ?>
            <?php if ($alreadyApplied): ?>
              <a class="btn btn-outline btn-sm" href="messages.php?job=<?= $job['id'] ?>">Open messages →</a>
            <?php else: ?>
              <form method="post" style="display:inline" class="applyForm" data-company="<?= htmlspecialchars($job['company_name'], ENT_QUOTES) ?>" data-title="<?= htmlspecialchars($job['title'], ENT_QUOTES) ?>">
                <input type="hidden" name="apply_job_id" value="<?= $job['id'] ?>">
                <input type="hidden" name="message" value="">
                <button class="btn btn-primary btn-sm applyBtn" type="submit">Apply →</button>
              </form>
            <?php endif; ?>
          <?php else: ?>
            <span style="font-size:11px;color:#888"><?= $isPending ? 'Awaiting approval' : 'Closed' ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(empty($jobs)): ?><p style="color:#777; font-size:13px">No jobs found.</p><?php endif; ?>
    </div>
  </div>
</div>
</div>
<script src="/assets/js/app.js"></script>
<script>
"use strict";
document.querySelectorAll('.applyForm').forEach(f => {
  f.addEventListener('submit', e => {
    const company = f.dataset.company;
    const title = f.dataset.title;
    const m = prompt('Message to ' + company + ':', 'Hello, I am interested in the ' + title + ' position.');
    if (m === null) { e.preventDefault(); return; }
    f.querySelector('input[name="message"]').value = m.trim() || ('Hello, I am interested in the ' + title + ' position.');
  });
});
</script>
</body>
</html>
