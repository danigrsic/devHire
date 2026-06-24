<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
use DevHire\Auth;
use DevHire\Database;

Auth::requireLogin('user');
$user = Auth::user();
$pdo = Database::getConnection();

// ensure bio column exists
try { $pdo->query("SELECT bio FROM users LIMIT 1"); } catch(\Throwable $e) {
    try { $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER phone"); } catch(\Throwable $e2) {}
}

$stmt = $pdo->prepare('SELECT first_name, last_name, email, phone, bio FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    if ($firstName === '' || $lastName === '') {
        $errors[] = 'First and last name are required.';
    } else {
        $pdo->prepare('UPDATE users SET first_name=?, last_name=?, phone=?, bio=? WHERE id=?')
            ->execute([$firstName, $lastName, $phone, $bio, $user['id']]);
        $_SESSION['first_name'] = $firstName;
        $success = true;
        $profile = ['first_name'=>$firstName,'last_name'=>$lastName,'email'=>$profile['email'],'phone'=>$phone,'bio'=>$bio];
        $user['first_name'] = $firstName;
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile - devHire</title>
<link rel="stylesheet" href="/assets/css/style.css"></head>
<body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Welcome Back!<br>Hi, <?= htmlspecialchars($user['first_name']) ?></h1>
  <p class="welcome-sub">Here are jobs recommended for you and the status of your application</p>

  <div class="auth-card" style="max-width:420px;margin:0">
    <h2>Personal Information</h2>
    <p style="font-size:11px;color:#666;margin-bottom:14px">Update your contact details and bio.</p>
    <?php if($success): ?><div class="success-box">Profile saved.</div><?php endif; ?>
    <?php if($errors): ?><div class="error-list"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-row">
        <div class="form-group"><label>First name</label><input type="text" name="first_name" value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>" required></div>
        <div class="form-group"><label>Last name</label><input type="text" name="last_name" value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Email</label><input type="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" disabled></div>
        <div class="form-group"><label>Phone number</label><input type="tel" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label>Bio</label><textarea name="bio" rows="4"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea></div>
      <button class="btn btn-primary btn-block" type="submit">Save changes</button>
      <p class="form-note"><a href="index.php">Cancel</a></p>
    </form>
  </div>
</div>
</div>
</body></html>
