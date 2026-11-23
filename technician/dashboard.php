<?php
/** Technician dashboard. */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_TECHNICIAN);
$pdo = get_db();
$uid = (int)$_SESSION['user_id'];

function tech_count(PDO $pdo, int $uid, string $extra = ''): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments a WHERE a.technician_id=? AND a.status<>'reassigned' $extra");
    $stmt->execute([$uid]);
    return (int)$stmt->fetchColumn();
}
$assigned  = tech_count($pdo, $uid);
$pending   = tech_count($pdo, $uid, "AND a.status='pending'");
$active    = tech_count($pdo, $uid, "AND a.status='in_progress'");
$completed = tech_count($pdo, $uid, "AND a.status='completed'");

$stmt = $pdo->prepare("SELECT r.*, f.name AS facility_name, a.status AS job_status
    FROM assignments a JOIN reports r ON r.id=a.report_id
    JOIN facilities f ON f.id=r.facility_id
    WHERE a.technician_id=? AND a.status<>'reassigned'
    ORDER BY FIELD(r.priority,'critical','high','medium','low'), r.deadline ASC");
$stmt->execute([$uid]);
$jobs = $stmt->fetchAll();

$page_title = 'My dashboard';
$active_nav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-4" style="flex-wrap:wrap;gap:1rem">
    <div><h1 style="font-size:1.6rem">Hello, <?= e(explode(' ', $_SESSION['user_name'])[0]) ?></h1><p class="text-muted">Your assigned jobs.</p></div>
    <a href="<?= e(url('technician/jobs.php')) ?>" class="btn btn-primary"><i class="fas fa-wrench mr-2"></i>All jobs</a>
</div>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-info"><div class="stat-label">Assigned</div><div class="stat-value"><?= (int)$assigned ?></div></div><div class="stat-icon"><i class="fas fa-clipboard-list"></i></div></div>
    <div class="stat-card orange"><div class="stat-info"><div class="stat-label">Pending</div><div class="stat-value"><?= (int)$pending ?></div></div><div class="stat-icon"><i class="fas fa-hourglass-start"></i></div></div>
    <div class="stat-card indigo"><div class="stat-info"><div class="stat-label">Active</div><div class="stat-value"><?= (int)$active ?></div></div><div class="stat-icon"><i class="fas fa-screwdriver-wrench"></i></div></div>
    <div class="stat-card green"><div class="stat-info"><div class="stat-label">Completed</div><div class="stat-value"><?= (int)$completed ?></div></div><div class="stat-icon"><i class="fas fa-circle-check"></i></div></div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Job queue</h2></div>
    <div class="card-body p-0">
        <?php if (!$jobs): ?><div class="empty-state"><i class="fas fa-broom"></i><h3>No jobs assigned</h3></div>
        <?php else: ?><div class="table-responsive"><table class="table">
            <thead><tr><th>Code</th><th>Issue</th><th>Priority</th><th>Status</th><th></th></tr></thead><tbody>
            <?php foreach ($jobs as $j): ?><tr>
                <td class="tracking-code"><?= e($j['tracking_code']) ?></td>
                <td><strong><?= e($j['title']) ?></strong><div class="x-small text-muted"><?= e($j['facility_name']) ?></div></td>
                <td><?= format_priority_badge($j['priority']) ?></td>
                <td><?= format_status_badge($j['status']) ?></td>
                <td><a class="btn btn-outline btn-sm" href="<?= e(url('technician/job-view.php?id=' . (int)$j['id'])) ?>">Open</a></td>
            </tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
