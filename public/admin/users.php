<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
use DevHire\Auth;
use DevHire\Database;

Auth::requireLogin('admin');
$pdo = Database::getConnection();
$user = Auth::user();

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok(): bool { return ($_POST['csrf'] ?? '') === ($_SESSION['csrf'] ?? ''); }

if (isset($_GET['toggle_user'])) {
    $id = (int)$_GET['toggle_user'];
    $pdo->query("UPDATE users SET is_approved = NOT is_approved WHERE id = $id");
    header('Location: users.php'); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_user']) && csrf_ok()) {
    $id = (int)$_POST['delete_user'];
    if ($id !== $user['id']) {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    }
    header('Location: users.php'); exit;
}

$users = $pdo->query("SELECT id, email, first_name, last_name, user_type, is_active, is_approved, created_at FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Users - Admin</title><link rel="stylesheet" href="/devhire/assets/css/style.css"></head>
<body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Welcome Back!<br>Hi, <?= htmlspecialchars($user['first_name']) ?></h1>
  <p class="welcome-sub">Ban / approve / delete users</p>
  <div class="card">
    <h3>Users</h3>
    <table class="table">
      <tr><th>Email</th><th>Name</th><th>Type</th><th>Active</th><th>Approved</th><th>Actions</th></tr>
      <?php foreach($users as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></td>
        <td><?= $u['user_type'] ?></td>
        <td><?= $u['is_active']?'Yes':'No' ?></td>
        <td><?= $u['is_approved']?'Yes':'No' ?></td>
        <td>
          <a class="btn btn-sm btn-outline" href="?toggle_user=<?= $u['id'] ?>"><?= $u['is_approved'] ? 'Ban' : 'Unban' ?></a>
          <?php if ($user['id'] != $u['id']): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete user permanently? CASCADE deletes companies/jobs/messages.')">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <input type="hidden" name="delete_user" value="<?= $u['id'] ?>">
            <button class="btn btn-sm" style="color:#b3263a;border-color:#b3263a;background:#fff">Delete</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</div>
</body></html>
