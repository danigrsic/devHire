<?php
$current = basename($_SERVER['SCRIPT_NAME']);
// unread count
$unreadMsg = 0;
if (isset($_SESSION['user_id']) && ($_SESSION['user_type'] ?? '') === 'user') {
    require_once __DIR__ . '/../app/Messaging.php';
    $unreadMsg = \DevHire\Messaging::getUnreadCountUser((int)$_SESSION['user_id']);
}
?>
<div class="sidebar">
  <div>
    <div class="logo"><a href="/index.php">devHire</a></div>
    <small class="role">Role<br><strong>User</strong></small>
    <nav>
      <a href="/dashboard/index.php" class="<?= $current==='index.php'?'active':'' ?>">Overview</a>
      <a href="/dashboard/jobs.php" class="<?= $current==='jobs.php'?'active':'' ?>">Jobs</a>
      <a href="/dashboard/messages.php" class="<?= $current==='messages.php'?'active':'' ?>">Messages <?php if($unreadMsg>0): ?><span style="background:#b3263a;color:#fff;border-radius:99px;padding:1px 6px;font-size:10px"><?= $unreadMsg ?></span><?php endif; ?></a>
      <a href="/dashboard/security.php" class="<?= $current==='security.php'?'active':'' ?>">Security</a>
      <a href="/dashboard/profile.php" class="<?= $current==='profile.php'?'active':'' ?>">Profile</a>
    </nav>
  </div>
  <div><a href="/logout.php" style="font-size:12px;color:#555">Logout</a></div>
</div>
