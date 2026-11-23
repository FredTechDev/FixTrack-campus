<?php
/** Administrator dashboard. */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_ADMIN);
$pdo = get_db();

$total      = (int)$pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
$unassigned = (int)$pdo->query("SELECT COUNT(*) FROM reports r WHERE r.status='APPROVED' AND NOT EXISTS (SELECT 1 FROM assignments a WHERE a.report_id=r.id AND a.status<>'reassigned')")->fetchColumn();
$active     = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('ASSIGNED','IN PROGRESS')")->fetchColumn();
$overdue    = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE deadline<NOW() AND status NOT IN ('RESOLVED','VERIFIED','CLOSED','REJECTED')")->fetchColumn();
$resolved   = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('RESOLVED','VERIFIED','CLOSED')")->fetchColumn();

$reports = $pdo->query("
    SELECT r.*, c.name AS category_name, f.name AS facility_name,
           COALESCE(u.full_name, r.guest_name) AS reporter, r.is_guest
    FROM reports r JOIN categories c ON c.id=r.category_id
    JOIN facilities f ON f.id=r.facility_id LEFT JOIN users u ON u.id=r.student_id
    ORDER BY r.updated_at DESC LIMIT 10
")->fetchAll();

$page_title = 'Overview';
$active_nav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-4" style="flex-wrap:wrap;gap:1rem">
    <div><h1 style="font-size:1.6rem">Operations overview</h1><p class="text-muted">Current maintenance activity.</p></div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('admin/reports.php?status=APPROVED')) ?>" class="btn btn-outline">Unassigned</a>
        <a href="<?= e(url('admin/analytics.php')) ?>" class="btn btn-primary">Analytics</a>
    </div>
</div>

<?php if ($unassigned || $overdue): ?><div class="alert alert-warning mb-4"><i class="fas fa-triangle-exclamation mr-2"></i><?= (int)$unassigned ?> unassigned &middot; <?= (int)$overdue ?> overdue</div><?php endif; ?>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-info"><div class="stat-label">Total reports</div><div class="stat-value"><?= (int)$total ?></div></div><div class="stat-icon"><i class="fas fa-file-lines"></i></div></div>
    <div class="stat-card orange"><div class="stat-info"><div class="stat-label">Unassigned</div><div class="stat-value"><?= (int)$unassigned ?></div></div><div class="stat-icon"><i class="fas fa-user-slash"></i></div></div>
    <div class="stat-card indigo"><div class="stat-info"><div class="stat-label">Active jobs</div><div class="stat-value"><?= (int)$active ?></div></div><div class="stat-icon"><i class="fas fa-screwdriver-wrench"></i></div></div>
    <div class="stat-card red"><div class="stat-info"><div class="stat-label">Overdue</div><div class="stat-value"><?= (int)$overdue ?></div></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
    <div class="stat-card green"><div class="stat-info"><div class="stat-label">Resolved</div><div class="stat-value"><?= (int)$resolved ?></div></div><div class="stat-icon"><i class="fas fa-circle-check"></i></div></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-between align-center"><h2 class="card-title">All reports</h2><a class="btn btn-outline btn-sm" href="<?= e(url('admin/reports.php')) ?>">View all</a></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table">
        <thead><tr><th>Code</th><th>Issue</th><th>Reporter</th><th>Priority</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
        <?php foreach ($reports as $r): ?><tr>
            <td class="tracking-code"><?= e($r['tracking_code']) ?></td>
            <td><strong><?= e($r['title']) ?></strong><div class="x-small text-muted"><?= e($r['facility_name']) ?></div></td>
            <td class="small"><?= e($r['reporter']) ?><?php if ($r['is_guest']): ?> <span class="badge badge-soft">Guest</span><?php endif; ?></td>
            <td><?= format_priority_badge($r['priority']) ?></td>
            <td><?= format_status_badge($r['status']) ?></td>
            <td class="small text-muted nowrap"><?= e(time_ago($r['updated_at'])) ?></td>
            <td><a class="btn btn-outline btn-sm" href="<?= e(url('admin/report-view.php?id=' . (int)$r['id'])) ?>">Manage</a></td>
        </tr><?php endforeach; ?>
        </tbody></table></div></div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
