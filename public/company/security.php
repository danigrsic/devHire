<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
use DevHire\Auth;
use DevHire\Database;

Auth::requireLogin('company');
$user = Auth::user();
$pdo = Database::getConnection();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $newHash = password_hash($new, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $user['id']]);
        $success = true;
    }
}

// company name for header
$companyId = Auth::getCompanyId($user['id']);
$companyName = $user['first_name'];
if ($companyId) {
    $s = $pdo->prepare('SELECT company_name FROM companies WHERE id=?');
    $s->execute([$companyId]);
    $companyName = $s->fetchColumn() ?: $companyName;
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Security - devHire</title>
<link rel="stylesheet" href="/devhire/assets/css/style.css"></head>
<body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Welcome Back!<br>Hi, <?= htmlspecialchars($companyName) ?></h1>
  <p class="welcome-sub">Manage your listings, reply to applicants and track performance.</p>
  <div class="auth-card" style="max-width:380px;margin:0">
    <h2>Change password</h2>
    <p style="font-size:11px;color:#666;margin-bottom:14px">Choose a strong password — at least 8 characters, letters and numbers.</p>
    <?php if($success): ?><div class="success-box">Password updated successfully.</div><?php endif; ?>
    <?php if($errors): ?><div class="error-list"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group"><label>Current password</label><input type="password" name="current_password" required></div>
      <div class="form-group"><label>New password</label><input type="password" name="new_password" required minlength="8"></div>
      <div class="form-group"><label>Confirm new password</label><input type="password" name="confirm_password" required minlength="8"></div>
      <button class="btn btn-primary btn-block" type="submit">Update password</button>
      <p class="form-note"><a href="#">I forgot my password</a></p>
    </form>
  </div>
</div>
</div>
</body></html>
