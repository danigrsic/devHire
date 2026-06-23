<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isLoggedIn = isset($_SESSION['user_id']);
$userType = $_SESSION['user_type'] ?? null;

// Auto-detect base URL (/devhire or /devhire/public)
$scriptDir = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$base = '';
if (strpos($scriptDir, '/devhire') !== false) {
    $base = substr($scriptDir, 0, strpos($scriptDir, '/devhire') + strlen('/devhire'));
}
if ($base === '') $base = '/devhire';
define('BASE_URL', rtrim($base, '/'));

$cssPath = __DIR__ . '/../assets/css/style.css';
$cssVer = file_exists($cssPath) ? filemtime($cssPath) : time();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'devHire - IT Job Marketplace' ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= $cssVer ?>">
</head>
<body>
<div class="page">
<div class="page-main">
<header class="site-header">
  <div class="header-inner">
    <div class="logo"><a href="<?= BASE_URL ?>/public/index.php">devHire</a></div>
    <nav class="nav-links">
      <a href="<?= BASE_URL ?>/public/index.php">Home</a>
      <a href="<?= BASE_URL ?>/public/jobs.php">Jobs</a>
    </nav>
    <div class="auth-buttons">
      <?php if ($isLoggedIn): 
        $dash = $userType === 'company' ? 'company' : ($userType === 'admin' ? 'admin' : 'dashboard');
      ?>
        <a href="<?= BASE_URL ?>/public/<?= $dash ?>/index.php" class="btn btn-outline btn-sm">Dashboard</a>
        <a href="<?= BASE_URL ?>/public/logout.php" class="btn btn-primary btn-sm">Logout</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/public/login.php" class="btn btn-outline btn-sm">Login</a>
        <a href="<?= BASE_URL ?>/public/register.php" class="btn btn-primary btn-sm">Register</a>
      <?php endif; ?>
    </div>
  </div>
</header>
