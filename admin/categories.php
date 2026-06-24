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

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok(): bool { return ($_POST['csrf'] ?? '') === ($_SESSION['csrf'] ?? ''); }

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['new_category']) && csrf_ok()) {
    $name = trim($_POST['new_category']);
    if ($name !== '') { try { $pdo->prepare('INSERT INTO job_categories (name) VALUES (?)')->execute([$name]); } catch (\Throwable $e) {} }
    header('Location: categories.php'); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_category']) && csrf_ok()) {
    try { $pdo->prepare('DELETE FROM job_categories WHERE id=?')->execute([(int)$_POST['delete_category']]); } catch(\Throwable $e) {}
    header('Location: categories.php'); exit;
}

$cats = $pdo->query("SELECT * FROM job_categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Categories - Admin</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Welcome Back!<br>Hi, <?= htmlspecialchars($user['first_name']) ?></h1>
  <p class="welcome-sub">Manage job categories</p>
  <div class="card" style="max-width:560px">
    <h3>Job Categories</h3>
    <table class="table">
      <?php foreach($cats as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['name']) ?></td>
        <td style="text-align:right">
          <form method="post" onsubmit="return confirm('Delete category?')">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <input type="hidden" name="delete_category" value="<?= $c['id'] ?>">
            <button class="btn btn-sm btn-outline" style="color:#b3263a;border-color:#b3263a">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <form method="post" style="margin-top:14px; display:flex; gap:8px">
      <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
      <input name="new_category" placeholder="New category name" required style="flex:1;padding:8px;border:1px solid #ccc;border-radius:8px">
      <button class="btn btn-primary btn-sm">Add</button>
    </form>
  </div>
</div>
</div>
</body></html>
