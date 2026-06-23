<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Messaging.php';
use DevHire\Auth;
use DevHire\Database;
use DevHire\Messaging;

Auth::requireLogin('user');
$user = Auth::user();
$pdo = Database::getConnection();
$userId = $user['id'];

// Dismiss application (X button)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_job_id'])) {
    Messaging::dismissApplication($userId, (int)$_POST['dismiss_job_id']);
    header('Location: index.php');
    exit;
}

// Stats
$applications = $pdo->prepare("SELECT COUNT(DISTINCT job_id) FROM messages WHERE user_id = ?");
$applications->execute([$userId]);
$appCount = $applications->fetchColumn();
$unreadMsg = Messaging::getUnreadCountUser($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - devHire</title>
<link rel="stylesheet" href="/devhire/assets/css/style.css">
</head>
<body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Welcome Back!<br>Hi, <?= htmlspecialchars($user['first_name']) ?></h1>
  <p class="welcome-sub">Here are jobs recommended for you and the status of your application</p>

  <div class="kpi-row">
    <div class="kpi"><div class="n"><?= (int)$appCount ?></div><div class="l">Active Applications</div></div>
    <div class="kpi"><div class="n">0</div><div class="l">Saved Jobs</div></div>
    <div class="kpi"><div class="n"><?= (int)$unreadMsg ?></div><div class="l">Messages</div></div>
  </div>

  <div class="card">
    <div class="search-toolbar"><input type="text" placeholder="Search..." id="appSearch"><button class="btn btn-primary btn-sm" onclick="filterApps()">Search</button></div>
    <h3>My application<br>In progress</h3>
    <table class="table" id="appTable">
      <tr><th>Position</th><th>Company</th><th>Date</th><th>Status</th><th></th></tr>
      <?php
      // Get one row per job the user applied to, exclude dismissed
      $hasDismiss = false;
      try { $pdo->query("SELECT 1 FROM application_dismissals LIMIT 1"); $hasDismiss = true; } catch(\Throwable $e){}
      $dismissJoin = $hasDismiss ? "LEFT JOIN application_dismissals ad ON ad.user_id = m.user_id AND ad.job_id = m.job_id WHERE m.user_id = ? AND ad.user_id IS NULL" : "WHERE m.user_id = ?";

      $stmt = $pdo->prepare("
        SELECT j.id as job_id, j.title, co.company_name, co.id as company_id,
               MIN(m.created_at) as applied_at
        FROM messages m
        JOIN jobs j ON j.id = m.job_id
        JOIN companies co ON co.id = m.company_id
        $dismissJoin
        GROUP BY j.id, co.id
        ORDER BY applied_at DESC
      ");
      $stmt->execute([$userId]);
      $apps = $stmt->fetchAll();
      foreach($apps as $row){
        $status = Messaging::getApplicationStatus((int)$row['job_id'], $userId, (int)$row['company_id']);
        $map = [
          'hired' => ['Hired', 'status-active'],
          'interview' => ['Interview', 'status-interview'],
          'pending' => ['Pending', 'status-in_progress'],
          'rejected' => ['Rejected', 'status-rejected'],
        ];
        [$label, $class] = $map[$status] ?? ['Pending','status-in_progress'];
        echo "<tr class='app-row'>
          <td>".htmlspecialchars($row['title'])."</td>
          <td>".htmlspecialchars($row['company_name'])."</td>
          <td>".substr($row['applied_at'],0,10)."</td>
          <td><span class='status-pill $class'>$label</span></td>
          <td style='text-align:right;white-space:nowrap'>
            <a class='btn btn-sm btn-outline' href='messages.php?job=".$row['job_id']."&company=".$row['company_id']."'>Open</a>
            <form method='post' style='display:inline' onsubmit=\"return confirm('Remove this application from your list? This only hides it for you.')\">
              <input type='hidden' name='dismiss_job_id' value='".$row['job_id']."'>
              <button type='submit' title='Dismiss' style='border:none;background:none;cursor:pointer;color:#999;font-size:16px;padding:0 4px'>✕</button>
            </form>
          </td>
        </tr>";
      }
      if (empty($apps)) {
        echo "<tr><td colspan='5' style='color:#777'>No applications yet. Go to Jobs to apply.</td></tr>";
      }
      ?>
    </table>
  </div>
</div>
</div>
<script>
function filterApps(){
  const q = document.getElementById('appSearch').value.toLowerCase();
  document.querySelectorAll('.app-row').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
document.getElementById('appSearch')?.addEventListener('input', filterApps);
</script>
</body>
</html>
