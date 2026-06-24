<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/UserManager.php';
require_once __DIR__ . '/app/Mailer.php';

use DevHire\UserManager;

$pageTitle = 'Create an Account - devHire';
$errors = [];
$success = false;
$mailSent = false;
$verifyLinkDev = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userManager = new UserManager();
    $result = $userManager->registerUser([
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'user_type' => $_POST['user_type'] ?? 'user',
        'company_name' => $_POST['company_name'] ?? ''
    ]);
    if ($result['success']) {
        $success = true;
        $mailSent = $result['mail_sent'] ?? false;
        $verifyLinkDev = $result['verify_link'] ?? '';
    } else {
        $errors = $result['errors'] ?? ['Registration failed'];
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <h2>Create an Account</h2>

    <?php if ($success): ?>
      <?php if ($mailSent): ?>
        <div class="success-box">Registration successful! We sent an activation email – please check your inbox (and spam folder).</div>
      <?php else: ?>
        <div class="error-list">
          Registration saved, but the activation email could NOT be sent.<br>
          Check your SMTP settings in <code>mail_config.php</code>.<br>
          <small>Dev fallback link: <a href="<?= htmlspecialchars($verifyLinkDev) ?>"><?= htmlspecialchars($verifyLinkDev) ?></a></small>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="error-list"><ul><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="post" id="registerForm" novalidate>
      <div class="form-group">
        <label>Email address*</label>
        <input type="email" name="email" required>
        <small id="emailHint" style="font-size:11px"></small>
      </div>
      <div class="form-group">
        <label>Password*</label>
        <input type="password" name="password" required minlength="6">
      </div>
      <div class="form-group">
        <label>Confirm Password*</label>
        <input type="password" name="confirm_password" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>First Name*</label>
          <input type="text" name="first_name" required>
        </div>
        <div class="form-group">
          <label>Last Name*</label>
          <input type="text" name="last_name" required>
        </div>
      </div>
      <div class="form-group">
        <label>Phone Number</label>
        <input type="tel" name="phone">
      </div>
      <div class="form-group radio-group">
        <label>Register as *</label>
        <label><input type="radio" name="user_type" value="user" checked onchange="toggleCompany(false)"> Job Seeker – I want to find a job</label>
        <label><input type="radio" name="user_type" value="company" onchange="toggleCompany(true)"> Company – I want to post jobs</label>
      </div>
      <div class="form-group" id="companyNameWrap" style="display:none">
        <label>Company Name</label>
        <input type="text" name="company_name">
      </div>
      <button class="btn btn-primary btn-block" type="submit">Register</button>
      <p class="form-note">Already have an account? <a href="login.php">Login here</a></p>
    </form>
    <script>
      function toggleCompany(show){
        document.getElementById('companyNameWrap').style.display = show ? 'block' : 'none';
      }
    </script>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
