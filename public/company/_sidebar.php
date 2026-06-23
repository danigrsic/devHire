<?php
$current = basename($_SERVER['SCRIPT_NAME']);
$unreadMsg = 0;
$companyId = 0;
if (isset($_SESSION['user_id']) && ($_SESSION['user_type'] ?? '') === 'company') {
    require_once __DIR__ . '/../../app/Auth.php';
    require_once __DIR__ . '/../../app/Messaging.php';
    $companyId = \DevHire\Auth::getCompanyId((int)$_SESSION['user_id']) ?? 0;
    if ($companyId) {
        $unreadMsg = \DevHire\Messaging::getUnreadCountCompany($companyId);
    }
}
?>
<div class="sidebar">
  <div>
    <div class="logo"><a href="/devhire/public/index.php">devHire</a></div>
    <small class="role">Role<br><strong>Company</strong></small>
    <nav>
      <a href="/devhire/public/company/index.php" class="<?= $current==='index.php'?'active':'' ?>">Overview</a>
      <a href="/devhire/public/company/new_listing.php" class="<?= $current==='new_listing.php'?'active':'' ?>">New Listing</a>
      <a href="/devhire/public/company/applications.php" class="<?= $current==='applications.php'?'active':'' ?>">Applications</a>
      <a href="/devhire/public/company/messages.php" class="<?= $current==='messages.php'?'active':'' ?>">Messages <?php if($unreadMsg>0): ?><span style="background:#b3263a;color:#fff;border-radius:99px;padding:1px 6px;font-size:10px"><?= $unreadMsg ?></span><?php endif; ?></a>
      <a href="/devhire/public/company/security.php" class="<?= $current==='security.php'?'active':'' ?>">Security</a>
      <a href="/devhire/public/company/profile.php" class="<?= $current==='profile.php'?'active':'' ?>">Profile</a>
    </nav>
  </div>
  <div><a href="/devhire/public/logout.php" style="font-size:12px;color:#555">Logout</a></div>
</div>
