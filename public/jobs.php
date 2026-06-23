<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
use DevHire\Database;
$pageTitle = 'Jobs - devHire';
require __DIR__ . '/../includes/header.php';

$pdo = Database::getConnection();
$q = trim($_GET['q'] ?? '');
$where = 'j.is_active=1 AND j.is_approved=1';
$params = [];
if ($q) { $where .= ' AND (j.title LIKE ? OR j.description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$stmt = $pdo->prepare("SELECT j.*, c.company_name, cat.name as cat_name FROM jobs j JOIN companies c ON c.id=j.company_id JOIN job_categories cat ON cat.id=j.category_id WHERE $where ORDER BY j.created_at DESC LIMIT 50");
$stmt->execute($params);
$jobs = $stmt->fetchAll();
?>
<main class="container" style="padding:48px 28px">
  <h1 style="margin-bottom:8px; font-size:34px; font-weight:800">Job listings</h1>
  <p class="muted" style="margin-bottom:20px">Browse verified IT positions.</p>
  <form class="search-box" style="margin-bottom:26px; max-width:520px"><input type="text" name="q" placeholder="Search for a position..." value="<?= htmlspecialchars($q) ?>"><button class="btn btn-primary btn-sm">Search</button></form>
  <div class="job-list">
  <?php foreach($jobs as $job): ?>
    <div class="job-item">
      <div><strong><?= htmlspecialchars($job['title']) ?></strong><div class="meta"><?= htmlspecialchars($job['cat_name']) ?> · <?= htmlspecialchars($job['company_name']) ?></div></div>
      <div><a class="btn btn-primary btn-sm" href="login.php">Apply →</a></div>
    </div>
  <?php endforeach; ?>
  <?php if(empty($jobs)): ?><p>No jobs found.</p><?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
