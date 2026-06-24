<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
use DevHire\Auth;
use DevHire\Database;

Auth::requireLogin('company');
$user = Auth::user();
$pdo = Database::getConnection();
$companyId = Auth::getCompanyId($user['id']);

$companyName = 'Company';
if ($companyId) {
    $stmt = $pdo->prepare('SELECT company_name FROM companies WHERE id=?');
    $stmt->execute([$companyId]);
    $companyName = $stmt->fetchColumn() ?: $companyName;
}

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$where = 'j.company_id = ?';
$params = [$companyId];
if ($statusFilter === 'active') { $where .= ' AND j.is_approved=1 AND j.is_active=1'; }
elseif ($statusFilter === 'pending') { $where .= ' AND j.is_approved=0'; }
elseif ($statusFilter === 'closed') { $where .= ' AND j.is_approved=1 AND j.is_active=0'; }

$activeListings = $pdo->prepare('SELECT COUNT(*) FROM jobs WHERE company_id=? AND is_active=1 AND is_approved=1');
$activeListings->execute([$companyId]);
$activeCount = $activeListings->fetchColumn();

$jobsStmt = $pdo->prepare("SELECT j.*, cat.name as category_name FROM jobs j JOIN job_categories cat ON cat.id=j.category_id WHERE $where ORDER BY j.created_at DESC");
$jobsStmt->execute($params);
$jobs = $jobsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Company Dashboard - devHire</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Welcome Back!<br>Hi, <?= htmlspecialchars($companyName) ?></h1>
  <p class="welcome-sub">Manage your listings, reply to applicants and track performance.</p>

  <div class="kpi-row">
    <div class="kpi"><div class="n"><?= (int)$activeCount ?></div><div class="l">Active listing</div></div>
    <div class="kpi"><div class="n">0</div><div class="l">New applicants</div></div>
    <div class="kpi"><div class="n">0</div><div class="l">Messages</div></div>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <h3>Active and past positions</h3>
      <form method="get" style="font-size:12px">
        Status: <select name="status" onchange="this.form.submit()">
          <option value="all" <?= $statusFilter==='all'?'selected':'' ?>>All</option>
          <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
          <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
          <option value="closed" <?= $statusFilter==='closed'?'selected':'' ?>>Closed</option>
        </select>
      </form>
    </div>
    <table class="table">
      <tr><th>Position</th><th>Category</th><th>Deadline</th><th>Status</th></tr>
      <?php foreach($jobs as $j): 
        $status = $j['is_approved'] ? ($j['is_active'] ? 'Active' : 'Closed') : 'Pending';
        $class = $j['is_active'] && $j['is_approved'] ? 'status-active' : 'status-closed';
        if (!$j['is_approved']) $class='status-in_progress';
      ?>
      <tr>
        <td><?= htmlspecialchars($j['title']) ?></td>
        <td><?= htmlspecialchars($j['category_name']) ?></td>
        <td><?= htmlspecialchars($j['application_deadline'] ?? '-') ?></td>
        <td><span class="status-pill <?= $class ?>"><?= $status ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php if(empty($jobs)): ?><p style="color:#777;font-size:13px">No jobs in this filter.</p><?php endif; ?>
  </div>
</div>
</div>
</body>
</html>
