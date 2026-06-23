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
$companyId = Auth::getCompanyId($user['id']);

if (!$companyId) { die('Company profile not found.'); }

$stmt = $pdo->prepare('SELECT * FROM companies WHERE id=?');
$stmt->execute([$companyId]);
$company = $stmt->fetch();

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim($_POST['company_name'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $upd = $pdo->prepare('UPDATE companies SET company_name=?, website=?, address=?, description=? WHERE id=?');
    $upd->execute([$companyName, $website, $address, $description, $companyId]);
    $saved = true;
    $company['company_name'] = $companyName;
    $company['website'] = $website;
    $company['address'] = $address;
    $company['description'] = $description;
}

$companyNameHeader = $company['company_name'] ?? $user['first_name'];
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Company Profile - devHire</title>
<link rel="stylesheet" href="/devhire/assets/css/style.css">
</head><body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Welcome Back!<br>Hi, <?= htmlspecialchars($companyNameHeader) ?></h1>
  <p class="welcome-sub">Manage your listings, reply to applicants and track performance.</p>

  <div class="auth-card" style="max-width:480px;margin:0">
    <h2>Company Information</h2>
    <p style="font-size:11px;color:#666;margin-bottom:14px">Update your public company profile.</p>
    <?php if($saved): ?><div class="success-box">Profile saved.</div><?php endif; ?>
    <form method="post">
      <div class="form-group"><label>Company Name*</label><input type="text" name="company_name" value="<?= htmlspecialchars($company['company_name']) ?>" required></div>
      <div class="form-row">
        <div class="form-group"><label>Website</label><input type="url" name="website" value="<?= htmlspecialchars($company['website'] ?? '') ?>"></div>
        <div class="form-group"><label>Address</label><input type="text" name="address" value="<?= htmlspecialchars($company['address'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label>Description</label><textarea name="description" rows="4"><?= htmlspecialchars($company['description'] ?? '') ?></textarea></div>
      <button class="btn btn-primary btn-block" type="submit">Save changes</button>
      <p class="form-note"><a href="index.php">Cancel</a></p>
    </form>
  </div>
</div>
</div>
</body></html>
