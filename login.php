<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/UserManager.php';
require_once __DIR__ . '/app/Auth.php';
require_once __DIR__ . '/app/Mailer.php';

use DevHire\UserManager;
use DevHire\Auth;
use DevHire\Mailer;

$pageTitle = 'Login - devHire';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $um = new UserManager();
    $result = $um->authenticate($email, $password);
    if (is_array($result) && isset($result['error'])) {
        $errors[] = $result['message'];
    } elseif ($result) {
        Auth::login($result);
        // Send login notification email
        $mailer = new Mailer();
        $mailer->sendLoginNotification($result['email'], $result['first_name']);
        // Redirect by role
        if ($result['user_type'] === 'company') {
            header('Location: /company/index.php');
        } elseif ($result['user_type'] === 'admin') {
            header('Location: /admin/index.php');
        } else {
            header('Location: /dashboard/index.php');
        }
        exit;
    } else {
        $errors[] = 'Invalid email or password.';
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <h2>Login to your account</h2>
    <?php if ($errors): ?><div class="error-list"><?php foreach($errors as $e) echo htmlspecialchars($e).'<br>'; ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
      <button class="btn btn-primary btn-block" type="submit">Login</button>
      <p class="form-note">No account? <a href="register.php">Register here</a></p>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
