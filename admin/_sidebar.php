<?php
$current = basename($_SERVER['SCRIPT_NAME']);
?>
<div class="sidebar">
  <div>
    <div class="logo"><a href="/devhire/index.php">devHire</a></div>
    <small class="role">Role<br><strong>Admin</strong></small>
    <nav>
      <a href="/admin/index.php" class="<?= $current==='index.php'?'active':'' ?>">Overview</a>
      <a href="/admin/jobs.php" class="<?= $current==='jobs.php'?'active':'' ?>">Jobs</a>
      <a href="/admin/users.php" class="<?= $current==='users.php'?'active':'' ?>">Users</a>
      <a href="/admin/categories.php" class="<?= $current==='categories.php'?'active':'' ?>">Categories</a>
   
    </nav>
  </div>
  <div><a href="/logout.php" style="font-size:12px;color:#555">Logout</a></div>
</div>
