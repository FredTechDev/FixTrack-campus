<?php
/**
 * Technician dashboard - assigned work overview.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_TECHNICIAN);
$pdo = get_db();
$uid = (int)$_SESSION['user_id'];

function tech_count(PDO $pdo, int $uid, string $extra = ''): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments a WHERE a.technician_id = ? AND a.status <> 'reassigned' $extra");
    $stmt->execute([$uid]);
    return (int)$stmt->fetchColumn();
}
$assigned  = tech_count($pdo, $uid);
$pending   = tech_count($pdo, $uid, "AND a.status = 'pending'");
$active    = tech_count($pdo, $uid, "AND a.status = 'in_progress'");
$completed = tech_count($pdo, $uid, "AND a.status = 'completed'");
$overdue   = (int)$pdo->query("SELECT COUNT(*) FROM assignments a JOIN reports r ON r.id = a.report_id WHERE a.technician_id = $uid AND a.status <> 'reassigned' AND r.deadline < NOW() AND r.status NOT IN ('RESOLVED','VERIFIED','CLOSED','REJECTED')")->fetchColumn();

$jobs = $pdo->prepare("
    SELECT r.*, c.name AS category_name, f.name AS facility_name, a.status AS assignment_status, a.assigned_at
    FROM assignments a
    JOIN reports r ON r.id = a.report_id
    JOIN categories c ON c.id = r.category_id
    JOIN facilities f ON f.id = r.facility_id
    WHERE a.technician_id = ? AND a.status <> 'reassigned'
    ORDER BY FIELD(r.priority,'critical','high','medium','low') ASC, r.deadline ASC
    LIMIT 8
");
$jobs->execute([$uid]);
$jobs = $jobs->fetchAll();

$updates = $pdo->prepare("
    SELECT h.*, r.tracking_code, r.title
    FROM status_history h
    JOIN reports r ON r.id = h.report_id
    JOIN assignments a ON a.report_id = r.id
    WHERE a.technician_id = ? AND h.changed_by = ?
    ORDER BY h.created_at DESC LIMIT 6
");
$updates->execute([$uid, $uid]);
$updates = $updates->fetchAll();

$page_title = 'My dashboard';
$active_nav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-4" style="flex-wrap:wrap;gap:1rem">
    <div><h1 style="font-size:1.7rem">Hello, <?= e(explode(' ', $_SESSION['user_name'])[0]) ?> 🛠️</h1><p class="text-muted">Your assigned maintenance work at a glance.</p></div>
    <a href="<?= e(url('technician/jobs.php')) ?>" class="btn btn-primary"><i class="fas fa-wrench mr-2"></i>View all jobs</a>
</div>

<?php if ($overdue > 0): ?><div class="alert alert-danger mb-4"><i class="fas fa-triangle-exclamation mr-2"></i><strong><?= (int)$overdue ?></strong> of your active jobs are past their deadline.</div><?php endif; ?>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-info"><div class="stat-label">Assigned jobs</div><div class="stat-value"><?= (int)$assigned ?></div><div class="stat-desc">All time</div></div><div class="stat-icon"><i class="fas fa-clipboard-list"></i></div></div>
    <div class="stat-card orange"><div class="stat-info"><div class="stat-label">Pending</div><div class="stat-value"><?= (int)$pending ?></div><div class="stat-desc">Not started yet</div></div><div class="stat-icon"><i class="fas fa-hourglass-start"></i></div></div>
    <div class="stat-card indigo"><div class="stat-info"><div class="stat-label">Active</div><div class="stat-value"><?= (int)$active ?></div><div class="stat-desc">Work in progress</div></div><div class="stat-icon"><i class="fas fa-screwdriver-wrench"></i></div></div>
    <div class="stat-card green"><div class="stat-info"><div class="stat-label">Completed</div><div class="stat-value"><?= (int)$completed ?></div><div class="stat-desc">Submitted for verification</div></div><div class="stat-icon"><i class="fas fa-circle-check"></i></div></div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-between align-center"><h2 class="card-title"><i class="fas fa-list-check mr-2"></i>Your job queue</h2><a class="btn btn-outline btn-sm" href="<?= e(url('technician/jobs.php')) ?>">View all</a></div>
    <div class="card-body p-0">
        <?php if (!$jobs): ?><div class="empty-state"><i class="fas fa-broom"></i><h3>No jobs assigned</h3><p>New assignments from the facilities office will appear here.</p></div>
        <?php else: ?><div class="table-responsive"><table class="table">
            <thead><tr><th>Code</th><th>Issue</th><th>Priority</th><th>Status</th><th>Deadline</th><th>Assigned</th><th></th></tr></thead><tbody>
            <?php foreach ($jobs as $j): ?>
                <tr>
                    <td class="tracking-code"><?= e($j['tracking_code']) ?></td>
                    <td><strong><?= e(mb_substr($j['title'], 0, 48)) ?></strong><div class="x-small text-muted"><?= e($j['facility_name']) ?> &middot; <?= e($j['room_number']) ?></div></td>
                    <td><?= format_priority_badge($j['priority']) ?></td>
                    <td><?= format_status_badge($j['status']) ?></td>
                    <td class="small nowrap"><?= $j['deadline'] ? e(format_date($j['deadline'], 'M j, g:i A')) : '&mdash;' ?><?php if (is_overdue($j['deadline'], $j['status'])): ?><div><span class="badge badge-overdue">Overdue</span></div><?php endif; ?></td>
                    <td class="small text-muted nowrap"><?= e(time_ago($j['assigned_at'])) ?></td>
                    <td><a class="btn btn-outline btn-sm" href="<?= e(url('technician/job-view.php?id=' . (int)$j['id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</div>

<div class="card"><div class="card-header"><h2 class="card-title"><i class="fas fa-clock-rotate-left mr-2"></i>Your recent updates</h2></div>
    <div class="card-body"><div class="timeline">
        <?php if (!$updates): ?><p class="text-muted">No status updates recorded yet.</p><?php endif; ?>
        <?php foreach ($updates as $u): ?>
            <div class="timeline-item"><div class="timeline-point"></div><div class="timeline-content">
                <div class="timeline-header"><span class="timeline-title"><?= e($u['new_status']) ?> &middot; <?= e($u['tracking_code']) ?></span><span class="timeline-time"><?= e(time_ago($u['created_at'])) ?></span></div>
                <?php if ($u['notes']): ?><div class="small text-muted"><?= e(mb_substr($u['notes'], 0, 140)) ?></div><?php endif; ?>
            </div></div>
        <?php endforeach; ?>
    </div></div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
