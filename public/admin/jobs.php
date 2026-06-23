<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
use DevHire\Auth;
use DevHire\Database;

Auth::requireLogin('admin');
$pdo = Database::getConnection();

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok(): bool { return ($_POST['csrf'] ?? '') === ($_SESSION['csrf'] ?? ''); }

if (isset($_GET['toggle_job'])) {
    $id = (int)$_GET['toggle_job'];
    $pdo->query("UPDATE jobs SET is_active = NOT is_active, is_approved = 1 WHERE id = $id");
    header('Location: jobs.php'); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_job']) && csrf_ok()) {
    $pdo->prepare('DELETE FROM jobs WHERE id=?')->execute([(int)$_POST['delete_job']]);
    header('Location: jobs.php'); exit;
}
if (isset($_GET['approve_job'])) {
    $id = (int)$_GET['approve_job'];
    $pdo->prepare('UPDATE jobs SET is_approved=1, is_active=1 WHERE id=?')->execute([$id]);
    header('Location: jobs.php'); exit;
}

$jobs = $pdo->query("SELECT j.*, c.company_name FROM jobs j JOIN companies c ON c.id=j.company_id ORDER BY j.is_approved ASC, j.created_at DESC")->fetchAll();
$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Jobs - Admin</title><link rel="stylesheet" href="/devhire/assets/css/style.css"></head>
<body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Welcome Back!<br>Hi, <?= htmlspecialchars($user['first_name']) ?></h1>
  <p class="welcome-sub">Approve / activate / delete job listings</p>
  <div class="card">
    <h3>All Jobs</h3>
    <table class="table">
      <tr><th>Title</th><th>Company</th><th>Status</th><th>Actions</th></tr>
      <?php foreach($jobs as $j):
        $status = $j['is_approved'] ? ($j['is_active'] ? 'Active' : 'Closed') : 'Pending';
        $class = $j['is_approved'] && $j['is_active'] ? 'status-active' : (!$j['is_approved'] ? 'status-in_progress' : 'status-closed');
      ?>
      <tr>
        <td><?= htmlspecialchars($j['title']) ?></td>
        <td><?= htmlspecialchars($j['company_name']) ?></td>
        <td><span class="status-pill <?= $class ?>"><?= $status ?></span></td>
        <td>
          <?php if(!$j['is_approved']): ?>
            <a class="btn btn-sm btn-primary" href="?approve_job=<?= $j['id'] ?>">Approve</a>
          <?php endif; ?>
          <a class="btn btn-sm btn-outline" href="?toggle_job=<?= $j['id'] ?>">Toggle active</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete job permanently?')">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <input type="hidden" name="delete_job" value="<?= $j['id'] ?>">
            <button class="btn btn-sm" style="color:#b3263a;border-color:#b3263a;background:#fff">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</div>
</body></html>
