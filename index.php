<?php
/** Public landing page. */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('dashboard.php');

$pdo = get_db();
$reportCount = (int)$pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();

$page_title = 'Welcome';
$active_nav = 'home';
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding:3rem 2rem">
    <span class="eyebrow">Campus maintenance, made simple</span>
    <h1>Report it. Track it.<br><span>See it fixed.</span></h1>
    <p class="lead">One place for students, administrators and technicians to manage campus repairs — from first report to final verification.</p>
    <div class="hero-actions">
        <a href="<?= e(url('register.php')) ?>" class="btn btn-lg btn-light"><i class="fas fa-paper-plane mr-2"></i>Report an issue</a>
        <a href="<?= e(url('login.php')) ?>" class="btn btn-lg btn-ghost"><i class="fas fa-right-to-bracket mr-2"></i>Sign in</a>
        <a href="<?= e(url('guest-report.php')) ?>" class="btn btn-lg btn-ghost"><i class="fas fa-user-clock mr-2"></i>Report as guest</a>
    </div>
</section>

<section class="section">
    <div class="section-head"><h2>How it works</h2><p><?= (int)$reportCount ?> issue(s) currently tracked on this campus.</p></div>
    <div class="feature-grid">
        <article class="feature-card"><div class="feature-icon"><i class="fas fa-circle-plus"></i></div><h3>1. Report</h3><p>Students submit the issue, location and a photo.</p></article>
        <article class="feature-card"><div class="feature-icon"><i class="fas fa-user-gear"></i></div><h3>2. Assign</h3><p>Administrators review and dispatch a technician.</p></article>
        <article class="feature-card"><div class="feature-icon"><i class="fas fa-star"></i></div><h3>3. Verify</h3><p>Students confirm the fix or reopen the issue.</p></article>
    </div>
</section>

<section class="section" style="text-align:center">
    <a href="<?= e(url('register.php')) ?>" class="btn btn-primary btn-lg">Create your free account</a>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
