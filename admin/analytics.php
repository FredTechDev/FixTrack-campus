<?php
/**
 * Administrator: performance analytics and visual summaries.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_ADMIN);
$pdo = get_db();

$statusCounts = [];
foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM reports GROUP BY status")->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int)$row['n'];
}
$total = array_sum($statusCounts);

$statusMeta = [
    'REPORTED' => ['Reported', '#94a3b8'], 'UNDER REVIEW' => ['Under review', '#0ea5e9'],
    'APPROVED' => ['Approved', '#3b82f6'], 'ASSIGNED' => ['Assigned', '#f59e0b'],
    'IN PROGRESS' => ['In progress', '#6366f1'], 'RESOLVED' => ['Resolved', '#14b8a6'],
    'VERIFIED' => ['Verified', '#06b6d4'], 'CLOSED' => ['Closed', '#10b981'],
    'REJECTED' => ['Rejected', '#ef4444'],
];
$segments = [];
$offset = 0;
foreach ($statusMeta as $key => $meta) {
    $n = $statusCounts[$key] ?? 0;
    if (!$n || !$total) continue;
    $start = $offset / $total * 100;
    $offset += $n;
    $end = $offset / $total * 100;
    $segments[] = $meta[1] . ' ' . $start . '% ' . $end . '%';
}
$donutStyle = $segments ? 'background:conic-gradient(' . implode(',', $segments) . ')' : 'background:#e2e8f0';

$priorityRows = $pdo->query("SELECT priority, COUNT(*) AS n FROM reports GROUP BY priority ORDER BY FIELD(priority,'critical','high','medium','low')")->fetchAll();
$maxPriority = max(1, ...array_column($priorityRows, 'n'));

$categoryRows = $pdo->query("SELECT c.name, COUNT(r.id) AS n, SUM(CASE WHEN r.status IN ('RESOLVED','VERIFIED','CLOSED') THEN 1 ELSE 0 END) AS done FROM categories c LEFT JOIN reports r ON r.category_id = c.id GROUP BY c.id ORDER BY n DESC")->fetchAll();
$maxCategory = max(1, ...array_column($categoryRows, 'n'));

$monthly = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, COUNT(*) AS n FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym ASC")->fetchAll();
$months = [];
for ($i = 5; $i >= 0; $i--) { $months[date('Y-m', strtotime("-$i months"))] = 0; }
foreach ($monthly as $m) { $months[$m['ym']] = (int)$m['n']; }
$maxMonth = max(1, ...array_values($months));

$techRows = $pdo->query("
    SELECT u.full_name, COUNT(a.id) AS assigned,
           SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed,
           SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) AS active
    FROM users u LEFT JOIN assignments a ON a.technician_id = u.id AND a.status <> 'reassigned'
    WHERE u.role = 'technician' AND u.is_active = 1
    GROUP BY u.id ORDER BY completed DESC
")->fetchAll();

$facilityRows = $pdo->query("SELECT f.name, COUNT(r.id) AS n FROM facilities f LEFT JOIN reports r ON r.facility_id = f.id GROUP BY f.id ORDER BY n DESC LIMIT 6")->fetchAll();
$maxFacility = max(1, ...array_column($facilityRows, 'n'));

$sla = $pdo->query("SELECT
    SUM(CASE WHEN deadline IS NOT NULL THEN 1 ELSE 0 END) AS with_deadline,
    SUM(CASE WHEN deadline IS NOT NULL AND resolved_at IS NOT NULL AND resolved_at <= deadline THEN 1 ELSE 0 END) AS met
    FROM reports WHERE status IN ('RESOLVED','VERIFIED','CLOSED')")->fetch();
$slaTotal = (int)($sla['with_deadline'] ?? 0);
$slaMet = (int)($sla['met'] ?? 0);
$slaPct = $slaTotal ? round($slaMet / $slaTotal * 100) : 0;

$feedback = $pdo->query("SELECT COUNT(*) AS n, AVG(rating) AS avg_rating, SUM(is_satisfied = 1) AS satisfied FROM repair_verifications")->fetch();
$avgRating = $feedback['avg_rating'] ? number_format((float)$feedback['avg_rating'], 1) : 'N/A';
$satisfaction = (int)$feedback['n'] ? round((int)$feedback['satisfied'] / (int)$feedback['n'] * 100) : 0;

$avgHours = $pdo->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM reports WHERE resolved_at IS NOT NULL")->fetchColumn();

$page_title = 'Analytics';
$active_nav = 'analytics';
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4"><h1 style="font-size:1.7rem">Analytics &amp; performance</h1><p class="text-muted">Data across all reports, assignments and verifications.</p></div>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-info"><div class="stat-label">All reports</div><div class="stat-value"><?= (int)$total ?></div><div class="stat-desc">Lifetime submissions</div></div><div class="stat-icon"><i class="fas fa-file-lines"></i></div></div>
    <div class="stat-card teal"><div class="stat-info"><div class="stat-label">Avg. resolution</div><div class="stat-value"><?= $avgHours !== null ? round((float)$avgHours, 1) : 'N/A' ?><span style="font-size:1rem">h</span></div><div class="stat-desc">Report to resolved</div></div><div class="stat-icon"><i class="fas fa-stopwatch"></i></div></div>
    <div class="stat-card green"><div class="stat-info"><div class="stat-label">SLA compliance</div><div class="stat-value"><?= (int)$slaPct ?>%</div><div class="stat-desc"><?= (int)$slaMet ?> of <?= (int)$slaTotal ?> on time</div></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
    <div class="stat-card orange"><div class="stat-info"><div class="stat-label">Satisfaction</div><div class="stat-value"><?= (int)$satisfaction ?>%</div><div class="stat-desc">Avg rating <?= e($avgRating) ?>/5</div></div><div class="stat-icon"><i class="fas fa-star"></i></div></div>
</div>

<div class="analytics-grid mb-4">
    <div class="card"><div class="card-header"><h2 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Status distribution</h2></div>
        <div class="card-body"><div class="donut-wrap">
            <div class="donut" style="<?= e($donutStyle) ?>"><div class="donut-center"><strong><?= (int)$total ?></strong><span>reports</span></div></div>
            <ul class="legend">
                <?php foreach ($statusMeta as $key => $meta): $n = $statusCounts[$key] ?? 0; if (!$n) continue; ?>
                    <li><span class="dot" style="background:<?= $meta[1] ?>"></span><?= e($meta[0]) ?><span class="count"><?= (int)$n ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div></div>
    </div>

    <div class="card"><div class="card-header"><h2 class="card-title"><i class="fas fa-chart-column mr-2"></i>Reports per month (last 6)</h2></div>
        <div class="card-body"><div class="chart-bars">
            <?php foreach ($months as $ym => $n): ?>
                <div class="bar-row"><div class="bar-label"><?= e(date('M Y', strtotime($ym . '-01'))) ?></div><div class="bar-track"><div class="bar-fill" style="width:<?= (int)($n / $maxMonth * 100) ?>%"></div></div><div class="bar-value"><?= (int)$n ?></div></div>
            <?php endforeach; ?>
        </div></div>
    </div>

    <div class="card"><div class="card-header"><h2 class="card-title"><i class="fas fa-flag mr-2"></i>Priority breakdown</h2></div>
        <div class="card-body"><div class="chart-bars">
            <?php $pcolors = ['#ef4444','#f97316','#3b82f6','#64748b']; foreach ($priorityRows as $i => $pr): ?>
                <div class="bar-row"><div class="bar-label"><?= e(ucfirst($pr['priority'])) ?></div><div class="bar-track"><div class="bar-fill" style="width:<?= (int)($pr['n'] / $maxPriority * 100) ?>%;background:<?= $pcolors[$i % 4] ?>"></div></div><div class="bar-value"><?= (int)$pr['n'] ?></div></div>
            <?php endforeach; ?>
        </div></div>
    </div>

    <div class="card"><div class="card-header"><h2 class="card-title"><i class="fas fa-building mr-2"></i>Facility hotspots</h2></div>
        <div class="card-body"><div class="chart-bars">
            <?php foreach ($facilityRows as $f): ?>
                <div class="bar-row"><div class="bar-label" title="<?= e($f['name']) ?>"><?= e($f['name']) ?></div><div class="bar-track"><div class="bar-fill" style="width:<?= (int)($f['n'] / $maxFacility * 100) ?>%;background:#8b5cf6"></div></div><div class="bar-value"><?= (int)$f['n'] ?></div></div>
            <?php endforeach; ?>
        </div></div>
    </div>

    <div class="card"><div class="card-header"><h2 class="card-title"><i class="fas fa-layer-group mr-2"></i>Category volume vs resolved</h2></div>
        <div class="card-body"><div class="chart-bars">
            <?php foreach ($categoryRows as $c): ?>
                <div class="bar-row"><div class="bar-label" title="<?= e($c['name']) ?>"><?= e($c['name']) ?></div><div class="bar-track"><div class="bar-fill" style="width:<?= (int)($c['n'] / $maxCategory * 100) ?>%"></div></div><div class="bar-value"><?= (int)$c['n'] ?></div></div>
            <?php endforeach; ?>
        </div><p class="x-small text-muted mt-3">Resolved: <?php foreach ($categoryRows as $c) echo e(mb_substr($c['name'],0,12)) . ' ' . (int)$c['done'] . ' &middot; '; ?></p></div>
    </div>

    <div class="card"><div class="card-header"><h2 class="card-title"><i class="fas fa-users-gear mr-2"></i>Technician performance</h2></div>
        <div class="card-body p-0">
            <?php if (!$techRows): ?><p class="text-muted p-4">No technicians.</p><?php endif; ?>
            <?php foreach ($techRows as $t): ?>
                <div class="mini-stat" style="padding:.85rem 1.25rem"><span class="rank"><?= e(strtoupper(mb_substr($t['full_name'],0,1))) ?></span>
                    <div class="info"><strong><?= e($t['full_name']) ?></strong><small><?= (int)$t['active'] ?> active &middot; <?= (int)$t['assigned'] ?> total</small></div>
                    <div class="value"><?= (int)$t['completed'] ?> done</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card"><div class="card-body d-flex justify-between align-center" style="flex-wrap:wrap;gap:1rem">
    <div><i class="fas fa-file-csv mr-2 text-primary"></i><strong>Export raw data</strong><div class="small text-muted">Download reports as CSV for spreadsheets or further analysis.</div></div>
    <a href="<?= e(url('export.php')) ?>" class="btn btn-outline"><i class="fas fa-download mr-2"></i>Export all reports</a>
</div></div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
