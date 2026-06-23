<?php
$current = basename($_SERVER['SCRIPT_NAME']);
?>
<div class="sidebar">
  <div>
    <div class="logo"><a href="/devhire/public/index.php">devHire</a></div>
    <small class="role">Role<br><strong>Admin</strong></small>
    <nav>
      <a href="/devhire/public/admin/index.php" class="<?= $current==='index.php'?'active':'' ?>">Overview</a>
      <a href="/devhire/public/admin/jobs.php" class="<?= $current==='jobs.php'?'active':'' ?>">Jobs</a>
      <a href="/devhire/public/admin/users.php" class="<?= $current==='users.php'?'active':'' ?>">Users</a>
      <a href="/devhire/public/admin/categories.php" class="<?= $current==='categories.php'?'active':'' ?>">Categories</a>
   
    </nav>
  </div>
  <div><a href="/devhire/public/logout.php" style="font-size:12px;color:#555">Logout</a></div>
</div>
