<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
use DevHire\Auth;
use DevHire\Database;

Auth::requireLogin('admin');
$pdo = Database::getConnection();
$user = Auth::user();

$stats = [
  'users' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='user'")->fetchColumn(),
  'companies' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='company'")->fetchColumn(),
  'jobs' => $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
  'pending_jobs' => $pdo->query("SELECT COUNT(*) FROM jobs WHERE is_approved=0")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin - devHire</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Welcome Back!<br>Hi, <?= htmlspecialchars($user['first_name']) ?></h1>
  <p class="welcome-sub">Admin dashboard – manage the platform.</p>

  <div class="kpi-row">
    <div class="kpi"><div class="n"><?= (int)$stats['users'] ?></div><div class="l">Job seekers</div></div>
    <div class="kpi"><div class="n"><?= (int)$stats['companies'] ?></div><div class="l">Companies</div></div>
    <div class="kpi"><div class="n"><?= (int)$stats['jobs'] ?> <small style="color:#b3263a">(<?= (int)$stats['pending_jobs'] ?> pending)</small></div><div class="l">Total jobs</div></div>
  </div>

  <div class="card">
    <h3>Quick actions</h3>
    <p style="font-size:13px;line-height:2">
      <a class="btn btn-primary btn-sm" href="jobs.php">Review Jobs (<?= (int)$stats['pending_jobs'] ?> pending)</a>
      <a class="btn btn-outline btn-sm" href="users.php">Manage Users</a>
      <a class="btn btn-outline btn-sm" href="categories.php">Categories</a>
    </p>
  </div>
</div>
</div>
</body></html>
