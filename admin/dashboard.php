<?php
/**
 * Administrator dashboard - operational overview and analytics summary.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_ADMIN);
$pdo = get_db();

$total       = (int)$pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
$unassigned  = (int)$pdo->query("SELECT COUNT(*) FROM reports r WHERE r.status = 'APPROVED' AND NOT EXISTS (SELECT 1 FROM assignments a WHERE a.report_id = r.id AND a.status <> 'reassigned')")->fetchColumn();
$active      = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('ASSIGNED','IN PROGRESS')")->fetchColumn();
$overdue     = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE deadline < NOW() AND status NOT IN ('RESOLVED','VERIFIED','CLOSED','REJECTED')")->fetchColumn();
$resolved    = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('RESOLVED','VERIFIED','CLOSED')")->fetchColumn();
$pendingReview = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('REPORTED','UNDER REVIEW')")->fetchColumn();
$awaitClose  = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'VERIFIED'")->fetchColumn();

$critical = $pdo->query("SELECT r.*, f.name AS facility_name FROM reports r JOIN facilities f ON f.id = r.facility_id WHERE r.priority = 'critical' AND r.status NOT IN ('RESOLVED','VERIFIED','CLOSED','REJECTED') ORDER BY r.created_at DESC LIMIT 5")->fetchAll();

$recent = $pdo->query("
    SELECT r.*, c.name AS category_name, f.name AS facility_name, u.full_name AS student_name,
           t.full_name AS technician_name
    FROM reports r
    JOIN categories c ON c.id = r.category_id
    JOIN facilities f ON f.id = r.facility_id
    JOIN users u ON u.id = r.student_id
    LEFT JOIN assignments a ON a.report_id = r.id AND a.status <> 'reassigned'
    LEFT JOIN users t ON t.id = a.technician_id
    ORDER BY r.updated_at DESC LIMIT 8
")->fetchAll();

$workload = $pdo->query("
    SELECT u.id, u.full_name, u.specialization,
           COUNT(a.id) AS total_assigned,
           SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) AS active_jobs,
           SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed_jobs
    FROM users u
    LEFT JOIN assignments a ON a.technician_id = u.id AND a.status <> 'reassigned'
    WHERE u.role = 'technician' AND u.is_active = 1
    GROUP BY u.id ORDER BY active_jobs DESC, total_assigned DESC
")->fetchAll();

$byCategory = $pdo->query("
    SELECT c.name, c.icon, COUNT(r.id) AS total,
           SUM(CASE WHEN r.status IN ('RESOLVED','VERIFIED','CLOSED') THEN 1 ELSE 0 END) AS done
    FROM categories c LEFT JOIN reports r ON r.category_id = c.id
    GROUP BY c.id ORDER BY total DESC, c.name ASC
")->fetchAll();

$maxCat = max(1, ...array_column($byCategory, 'total'));
$colors = ['#1e40af', '#0ea5e9', '#14b8a6', '#6366f1', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444'];

$page_title = 'Operations overview';
$active_nav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-4" style="flex-wrap:wrap;gap:1rem">
    <div><h1 style="font-size:1.7rem">Operations overview</h1><p class="text-muted">Live snapshot of campus maintenance activity.</p></div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('admin/reports.php?status=APPROVED')) ?>" class="btn btn-outline"><i class="fas fa-inbox mr-2"></i>Unassigned</a>
        <a href="<?= e(url('admin/analytics.php')) ?>" class="btn btn-primary"><i class="fas fa-chart-simple mr-2"></i>Full analytics</a>
    </div>
</div>

<?php if ($overdue > 0 || $unassigned > 0 || $pendingReview > 0): ?>
<div class="alert alert-warning alert-auto mb-4">
    <i class="fas fa-triangle-exclamation mr-2"></i>
    <strong>Needs attention:</strong>
    <?= (int)$pendingReview ?> awaiting review &middot;
    <?= (int)$unassigned ?> unassigned &middot;
    <?= (int)$overdue ?> overdue
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-info"><div class="stat-label">Total reports</div><div class="stat-value"><?= (int)$total ?></div><div class="stat-desc">All time</div></div><div class="stat-icon"><i class="fas fa-file-lines"></i></div></div>
    <div class="stat-card orange"><div class="stat-info"><div class="stat-label">Unassigned</div><div class="stat-value"><?= (int)$unassigned ?></div><div class="stat-desc">Approved, awaiting dispatch</div></div><div class="stat-icon"><i class="fas fa-user-slash"></i></div></div>
    <div class="stat-card indigo"><div class="stat-info"><div class="stat-label">Active jobs</div><div class="stat-value"><?= (int)$active ?></div><div class="stat-desc">Assigned or in progress</div></div><div class="stat-icon"><i class="fas fa-screwdriver-wrench"></i></div></div>
    <div class="stat-card red"><div class="stat-info"><div class="stat-label">Overdue</div><div class="stat-value"><?= (int)$overdue ?></div><div class="stat-desc">Past response deadline</div></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
    <div class="stat-card green"><div class="stat-info"><div class="stat-label">Resolved</div><div class="stat-value"><?= (int)$resolved ?></div><div class="stat-desc">Verified or closed</div></div><div class="stat-icon"><i class="fas fa-circle-check"></i></div></div>
    <div class="stat-card teal"><div class="stat-info"><div class="stat-label">Awaiting close</div><div class="stat-value"><?= (int)$awaitClose ?></div><div class="stat-desc">Verified by students</div></div><div class="stat-icon"><i class="fas fa-stamp"></i></div></div>
</div>

<div class="analytics-grid mb-4">
    <div class="card">
        <div class="card-header"><h2 class="card-title"><i class="fas fa-layer-group mr-2"></i>Reports by category</h2></div>
        <div class="card-body"><div class="chart-bars">
            <?php foreach ($byCategory as $i => $cat): ?>
                <div class="bar-row">
                    <div class="bar-label" title="<?= e($cat['name']) ?>"><?= e($cat['name']) ?></div>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= (int)($cat['total'] / $maxCat * 100) ?>%;background:<?= $colors[$i % count($colors)] ?>"></div></div>
                    <div class="bar-value"><?= (int)$cat['total'] ?></div>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="card-title"><i class="fas fa-users-gear mr-2"></i>Technician workload</h2></div>
        <div class="card-body p-0">
            <?php if (!$workload): ?><p class="text-muted p-4">No technicians yet.</p>
            <?php else: foreach ($workload as $w): ?>
                <div class="mini-stat" style="padding:.85rem 1.25rem">
                    <span class="rank"><?= e(strtoupper(mb_substr($w['full_name'], 0, 1))) ?></span>
                    <div class="info"><strong><?= e($w['full_name']) ?></strong><small><?= e($w['specialization'] ?? 'General maintenance') ?></small></div>
                    <div style="text-align:right"><div class="value"><?= (int)$w['active_jobs'] ?> active</div><small class="text-muted"><?= (int)$w['completed_jobs'] ?> completed</small></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<?php if ($critical): ?>
<div class="card mb-4">
    <div class="card-header"><h2 class="card-title" style="color:var(--danger)"><i class="fas fa-triangle-exclamation mr-2"></i>Open critical issues</h2></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table">
        <thead><tr><th>Code</th><th>Issue</th><th>Facility</th><th>Status</th><th>Reported</th><th></th></tr></thead><tbody>
        <?php foreach ($critical as $c): ?>
            <tr><td class="tracking-code"><?= e($c['tracking_code']) ?></td><td><strong><?= e(mb_substr($c['title'], 0, 50)) ?></strong></td><td class="small"><?= e($c['facility_name']) ?></td><td><?= format_status_badge($c['status']) ?></td><td class="small text-muted nowrap"><?= e(time_ago($c['created_at'])) ?></td><td><a class="btn btn-outline btn-sm" href="<?= e(url('admin/report-view.php?id=' . (int)$c['id'])) ?>">Review</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-between align-center"><h2 class="card-title"><i class="fas fa-clock-rotate-left mr-2"></i>Recently updated reports</h2><a class="btn btn-outline btn-sm" href="<?= e(url('admin/reports.php')) ?>">View all</a></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table">
        <thead><tr><th>Code</th><th>Issue</th><th>Reporter</th><th>Technician</th><th>Priority</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
        <?php foreach ($recent as $r): ?>
            <tr>
                <td class="tracking-code"><?= e($r['tracking_code']) ?></td>
                <td><strong><?= e(mb_substr($r['title'], 0, 44)) ?></strong><div class="x-small text-muted"><?= e($r['facility_name']) ?></div></td>
                <td class="small"><?= e($r['student_name']) ?></td>
                <td class="small"><?= $r['technician_name'] ? e($r['technician_name']) : '<span class="text-muted">&mdash;</span>' ?></td>
                <td><?= format_priority_badge($r['priority']) ?></td>
                <td><?= format_status_badge($r['status']) ?><?php if (is_overdue($r['deadline'], $r['status'])): ?><div class="mt-1"><span class="badge badge-overdue">Overdue</span></div><?php endif; ?></td>
                <td class="small text-muted nowrap"><?= e(time_ago($r['updated_at'])) ?></td>
                <td><a class="btn btn-outline btn-sm" href="<?= e(url('admin/report-view.php?id=' . (int)$r['id'])) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
