<?php
/** Administrator analytics - simple, readable summaries. */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_ADMIN);
$pdo = get_db();

$total = (int)$pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
$resolved = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('RESOLVED','VERIFIED','CLOSED')")->fetchColumn();
$avgHours = $pdo->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM reports WHERE resolved_at IS NOT NULL")->fetchColumn();
$satisfied = (int)$pdo->query("SELECT COUNT(*) FROM repair_verifications WHERE is_satisfied = 1")->fetchColumn();

$statuses = [];
foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM reports GROUP BY status")->fetchAll() as $row) {
    $statuses[$row['status']] = (int)$row['n'];
}
$categories = $pdo->query("SELECT c.name, COUNT(r.id) AS n FROM categories c LEFT JOIN reports r ON r.category_id=c.id GROUP BY c.id ORDER BY n DESC")->fetchAll();
$maxCategory = max(1, ...array_column($categories, 'n'));

$page_title = 'Analytics';
$active_nav = 'analytics';
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4"><h1 style="font-size:1.6rem">Analytics</h1><p class="text-muted">Simple counts across all reports.</p></div>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-info"><div class="stat-label">Reports</div><div class="stat-value"><?= (int)$total ?></div></div><div class="stat-icon"><i class="fas fa-file-lines"></i></div></div>
    <div class="stat-card green"><div class="stat-info"><div class="stat-label">Resolved</div><div class="stat-value"><?= (int)$resolved ?></div></div><div class="stat-icon"><i class="fas fa-circle-check"></i></div></div>
    <div class="stat-card teal"><div class="stat-info"><div class="stat-label">Avg. resolution</div><div class="stat-value"><?= $avgHours !== null ? round((float)$avgHours, 1) : '—' ?>h</div></div><div class="stat-icon"><i class="fas fa-stopwatch"></i></div></div>
    <div class="stat-card orange"><div class="stat-info"><div class="stat-label">Satisfied verifications</div><div class="stat-value"><?= (int)$satisfied ?></div></div><div class="stat-icon"><i class="fas fa-star"></i></div></div>
</div>

<div class="analytics-grid">
    <div class="card"><div class="card-header"><h2 class="card-title">By status</h2></div><div class="card-body">
        <?php foreach (array_keys(WORKFLOW_STEPS) as $key): if ($key === STATUS_REJECTED) continue; $n = $statuses[$key] ?? 0; ?>
        <div class="meta-item" style="padding:.35rem 0"><span class="meta-label"><?= e(WORKFLOW_STEPS[$key]['label']) ?></span><span class="meta-value"><?= (int)$n ?></span></div>
        <?php endforeach; ?>
        <?php if (isset($statuses[STATUS_REJECTED])): ?><div class="meta-item" style="padding:.35rem 0"><span class="meta-label">Rejected</span><span class="meta-value"><?= (int)$statuses[STATUS_REJECTED] ?></span></div><?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header"><h2 class="card-title">By category</h2></div><div class="card-body"><div class="chart-bars">
        <?php foreach ($categories as $c): ?>
        <div class="bar-row"><div class="bar-label" title="<?= e($c['name']) ?>"><?= e($c['name']) ?></div><div class="bar-track"><div class="bar-fill" style="width:<?= (int)($c['n'] / $maxCategory * 100) ?>%"></div></div><div class="bar-value"><?= (int)$c['n'] ?></div></div>
        <?php endforeach; ?>
    </div></div></div>
</div>

<div class="card mt-4"><div class="card-body d-flex justify-between align-center" style="flex-wrap:wrap;gap:1rem">
    <div><strong>Export reports</strong><div class="small text-muted">Download the current data as CSV.</div></div>
    <a href="<?= e(url('export.php')) ?>" class="btn btn-outline"><i class="fas fa-download mr-2"></i>Export CSV</a>
</div></div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
