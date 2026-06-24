<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
use DevHire\Auth;
use DevHire\Database;

Auth::requireLogin('company');
$user = Auth::user();
$pdo = Database::getConnection();
$companyId = Auth::getCompanyId($user['id']);

$categories = $pdo->query('SELECT * FROM job_categories ORDER BY name')->fetchAll();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $companyId) {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $deadline = $_POST['application_deadline'] ?? null;
    $description = trim($_POST['description'] ?? '');

    if ($categoryId <= 0) $errors[] = 'Select a category';
    if ($title === '') $errors[] = 'Title required';
    if ($description === '') $errors[] = 'Description required';

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO jobs (company_id, category_id, title, description, location, application_deadline, is_active, is_approved) VALUES (?,?,?,?,?,?,FALSE,FALSE)');
        $stmt->execute([$companyId, $categoryId, $title, $description, $location, $deadline ?: null]);
        $success = true;
    }
}

$companyName = 'Company';
if ($companyId) {
    $stmt = $pdo->prepare('SELECT company_name FROM companies WHERE id=?');
    $stmt->execute([$companyId]);
    $companyName = $stmt->fetchColumn() ?: $companyName;
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Listing - devHire</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head><body>
<div class="dashboard">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
  <h1>Welcome Back!<br>Hi, <?= htmlspecialchars($companyName) ?></h1>
  <p class="welcome-sub">Manage your listings, reply to applicants and track performance.</p>

  <div class="new-job-card">
    <h3>New job listing</h3>
    <p class="desc">Provide the category, position and a detailed description.</p>
    <?php if($success): ?><div class="success-box">Listing submitted! Awaiting admin approval.</div><?php endif; ?>
    <?php if($errors): ?><div class="error-list"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>
    <form method="post" id="jobForm">
      <div class="grid-2">
        <div class="form-group"><label>Job category</label>
          <select name="category_id" required>
            <option value="">Select...</option>
            <?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Position</label><input type="text" name="title" required></div>
        <div class="form-group"><label>Location</label><input type="text" name="location"></div>
        <div class="form-group"><label>Application deadline</label><input type="date" name="application_deadline"></div>
      </div>
      <div class="form-group"><label>Description</label><textarea name="description" rows="6" required></textarea></div>
      <div class="form-actions">
        <a href="index.php" style="font-size:12px; color:#666">Cancel</a>
        <button class="btn btn-primary" type="submit">Publish listing</button>
      </div>
    </form>
  </div>
</div>
</div>
<script>
"use strict";
document.getElementById('jobForm')?.addEventListener('submit', function(e){
  const title = this.title.value.trim();
  const desc = this.description.value.trim();
  if(title.length < 3 || desc.length < 10){
    e.preventDefault();
    alert('Please fill title and a detailed description (min 10 chars).');
  }
});
</script>
</body></html>
