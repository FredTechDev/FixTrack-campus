<?php
/**
 * FixTrack Campus - Public landing page.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('dashboard.php');

$pdo = get_db();
$categoryCount = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn();
$facilityCount = (int)$pdo->query("SELECT COUNT(*) FROM facilities WHERE is_active = 1")->fetchColumn();
$resolvedCount = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('RESOLVED','VERIFIED','CLOSED')")->fetchColumn();
$categories = get_categories($pdo);

$page_title = 'Welcome';
$active_nav = 'home';
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-grid">
        <div class="hero-content">
            <span class="eyebrow"><i class="fas fa-bolt mr-1"></i> Campus Operations, Simplified</span>
            <h1>Report it. Track it.<br><span>See it fixed.</span></h1>
            <p class="lead">A single place for students, administrators and maintenance technicians to manage campus facilities &mdash; from the first report to the final verification.</p>
            <div class="hero-actions">
                <a href="<?= e(url('register.php')) ?>" class="btn btn-lg btn-light"><i class="fas fa-paper-plane mr-2"></i>Report an Issue</a>
                <a href="#how-it-works" class="btn btn-lg btn-ghost"><i class="fas fa-circle-play mr-2"></i>How it works</a>
            </div>
            <div class="d-flex gap-4 mt-4" style="flex-wrap:wrap">
                <div><strong style="font-size:1.4rem"><?= (int)$categoryCount ?></strong><div class="x-small" style="color:#cbd5e1">Issue categories</div></div>
                <div><strong style="font-size:1.4rem"><?= (int)$facilityCount ?></strong><div class="x-small" style="color:#cbd5e1">Campus facilities</div></div>
                <div><strong style="font-size:1.4rem"><?= (int)$resolvedCount ?></strong><div class="x-small" style="color:#cbd5e1">Issues resolved</div></div>
            </div>
        </div>
        <aside class="hero-art" aria-hidden="true">
            <div class="mini-row"><span class="mini-icon" style="background:#fef2f2;color:#ef4444"><i class="fas fa-bolt"></i></span><div><div class="mini-title">Sparking wall socket</div><div class="mini-sub">Engineering Block &middot; <span style="color:#dc2626">Critical</span></div></div></div>
            <div class="mini-row"><span class="mini-icon" style="background:#e0e7ff;color:#6366f1"><i class="fas fa-screwdriver-wrench"></i></span><div><div class="mini-title">Technician dispatched</div><div class="mini-sub">Samuel Sparks &middot; In progress</div></div></div>
            <div class="mini-row"><span class="mini-icon" style="background:#ecfdf5;color:#10b981"><i class="fas fa-check"></i></span><div><div class="mini-title">Repair verified</div><div class="mini-sub">Student confirmed &middot; Closed</div></div></div>
            <div class="mini-row" style="background:#1e40af;color:#fff"><span class="mini-icon" style="background:rgba(255,255,255,.15);color:#fff"><i class="fas fa-qrcode"></i></span><div><div class="mini-title">Scan &middot; Report &middot; Resolve</div><div class="mini-sub" style="color:#bfdbfe">QR codes on every facility</div></div></div>
        </aside>
    </div>
</section>

<section class="section" id="features">
    <div class="section-head">
        <h2>Everything the facilities team needs</h2>
        <p>Built around a clear, auditable workflow so nothing gets lost between the report and the repair.</p>
    </div>
    <div class="feature-grid">
        <article class="feature-card"><div class="feature-icon"><i class="fas fa-circle-plus"></i></div><h3>Easy issue reporting</h3><p>Capture the title, location, category, priority and photo evidence in one guided form.</p></article>
        <article class="feature-card"><div class="feature-icon" style="background:#e0e7ff;color:#6366f1"><i class="fas fa-route"></i></div><h3>Transparent tracking</h3><p>Every status change is recorded, so students always know exactly where their report stands.</p></article>
        <article class="feature-card"><div class="feature-icon" style="background:#ecfdf5;color:#10b981"><i class="fas fa-user-gear"></i></div><h3>Smart assignments</h3><p>Administrators match jobs to the right technician and set clear response deadlines.</p></article>
        <article class="feature-card"><div class="feature-icon" style="background:#fffbeb;color:#f59e0b"><i class="fas fa-star"></i></div><h3>Repair verification</h3><p>Students confirm the fix and rate the work. Unsuccessful repairs can be reopened instantly.</p></article>
        <article class="feature-card"><div class="feature-icon" style="background:#f0fdfa;color:#14b8a6"><i class="fas fa-chart-simple"></i></div><h3>Actionable analytics</h3><p>Monitor workload, overdue jobs, category trends and resolution performance at a glance.</p></article>
        <article class="feature-card"><div class="feature-icon" style="background:#fef2f2;color:#ef4444"><i class="fas fa-shield-halved"></i></div><h3>Secure by design</h3><p>Hashed passwords, sessions, role-based access, CSRF tokens and prepared statements throughout.</p></article>
    </div>
</section>

<section class="section" id="categories">
    <div class="section-head"><h2>Covering every corner of campus</h2><p>Choose from eight categories, each with its own target response time.</p></div>
    <div style="display:flex;flex-wrap:wrap;gap:.7rem">
        <?php foreach ($categories as $cat): ?>
            <span class="category-chip"><i class="fas fa-<?= e($cat['icon']) ?>"></i><?= e($cat['name']) ?></span>
        <?php endforeach; ?>
    </div>
</section>

<section class="section" id="how-it-works">
    <div class="section-head"><h2>From report to resolution in eight steps</h2></div>
    <div class="steps-grid">
        <?php
        $steps = [
            ['Report', 'Student submits an issue with evidence.'],
            ['Review', 'Administrator validates the report.'],
            ['Approve', 'Valid issues are approved and prioritised.'],
            ['Assign', 'The right technician receives the job.'],
            ['In progress', 'Repairs are carried out on site.'],
            ['Resolved', 'Completion notes and photos are added.'],
            ['Verified', 'Student confirms the repair works.'],
            ['Closed', 'Ticket archived with a full history.'],
        ];
        foreach ($steps as $i => $step): ?>
            <div class="step-card"><div class="step-num"><?= $i + 1 ?></div><h3><?= e($step[0]) ?></h3><p><?= e($step[1]) ?></p></div>
        <?php endforeach; ?>
    </div>
</section>

<section class="section" style="text-align:center;padding:3rem;background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-lg)">
    <h2 style="font-size:1.8rem;margin-bottom:.6rem">Notice something broken on campus?</h2>
    <p class="text-muted" style="margin-bottom:1.5rem">It takes less than two minutes to file a report and get a tracking code.</p>
    <a href="<?= e(url('register.php')) ?>" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane mr-2"></i>Create your free account</a>
    <div class="mt-3 small"><a href="<?= e(url('login.php')) ?>">Already registered? Sign in</a></div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
