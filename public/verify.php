<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/UserManager.php';
use DevHire\UserManager;

$token = $_GET['token'] ?? '';
$ok = false;
if ($token) {
    $um = new UserManager();
    $ok = $um->activateUser($token);
}
$pageTitle = 'Account verification';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth-page"><div class="auth-card">
<?php if ($ok): ?>
  <div class="success-box">Account activated! You can now <a href="login.php">login</a>.</div>
<?php else: ?>
  <div class="error-list">Invalid or expired activation token.</div>
<?php endif; ?>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
