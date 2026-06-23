<?php
declare(strict_types=1);
$pageTitle = 'devHire - IT Job Marketplace';
require __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../app/Database.php';
use DevHire\Database;
$pdo = Database::getConnection();
$stats = [
  'jobs' => $pdo->query('SELECT COUNT(*) FROM jobs WHERE is_active=1 AND is_approved=1')->fetchColumn(),
  'companies' => $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn(),
  'developers' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='user'")->fetchColumn(),
];

// latest 3 active jobs
$latestJobs = [];
try {
  $latestJobs = $pdo->query("SELECT j.title, c.company_name, cat.name as cat FROM jobs j JOIN companies c ON c.id=j.company_id JOIN job_categories cat ON cat.id=j.category_id WHERE j.is_active=1 AND j.is_approved=1 ORDER BY j.created_at DESC LIMIT 3")->fetchAll();
} catch (\Throwable $e) {}
?>
<div class="hero-wrap">
<main class="hero">
  <div class="hero-copy">
    <h1>IT Job Marketplace<br><span class="muted">Where developers<br>find their next role</span></h1>
    <p class="sub">Connect with the best IT companies. Land your dream position, or post a job for the next superstar joining your team — all on one platform.</p>
    
    <form action="jobs.php" method="get" class="search-box">
      <input type="text" name="q" placeholder="Search for a position... e.g. React, PHP">
      <button class="btn btn-primary btn-sm" type="submit">Search</button>
    </form>

    <div class="hero-stats">
      <div><strong><?= (int)$stats['jobs'] ?></strong>Active jobs</div>
      <div><strong><?= (int)$stats['companies'] ?></strong>IT companies</div>
      <div><strong><?= (int)$stats['developers'] ?></strong>Developers</div>
    </div>
  </div>
  <div class="hero-art">
    <div class="hero-blob"></div>
  </div>
</main>
</div>

<section class="home-section">
  <div class="section-head">
    <h2>Why devHire?</h2>
    <p>Built for developers, trusted by tech companies.</p>
  </div>
  <div class="feature-grid">
    <div class="feature-card">
      <div class="icon">⚡</div>
      <h3>Fast applications</h3>
      <p>Apply in one click, chat directly with hiring teams inside devHire Messages.</p>
    </div>
    <div class="feature-card">
      <div class="icon">🎯</div>
      <h3>Curated IT roles</h3>
      <p>Frontend, Backend, DevOps, QA – every listing is reviewed by an admin.</p>
    </div>
    <div class="feature-card">
      <div class="icon">🔒</div>
      <h3>Secure & simple</h3>
      <p>Email activation, bcrypt passwords, role-based dashboards for Seekers, Companies and Admins.</p>
    </div>
  </div>

  <?php if ($latestJobs): ?>
  <div class="section-head" style="margin-top:44px">
    <h2>Latest openings</h2>
  </div>
  <div class="job-list" style="max-width:760px;margin:0 auto">
    <?php foreach($latestJobs as $j): ?>
    <div class="job-item">
      <div><strong><?= htmlspecialchars($j['title']) ?></strong><div class="meta"><?= htmlspecialchars($j['cat']) ?> · <?= htmlspecialchars($j['company_name']) ?></div></div>
      <a class="btn btn-outline btn-sm" href="jobs.php">View →</a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="cta-band">
    <div>
      <h3>Hiring developers?</h3>
      <p>Post a job in 60 seconds. Admin approval included.</p>
    </div>
    <div>
      <a href="register.php" class="btn">Create company account →</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
