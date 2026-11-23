<?php
/** Student dashboard. */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_STUDENT);
$pdo = get_db();
$uid = (int)$_SESSION['user_id'];

function count_for_student(PDO $pdo, int $uid, string $where): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE student_id = ? AND $where");
    $stmt->execute([$uid]);
    return (int)$stmt->fetchColumn();
}
$total      = count_for_student($pdo, $uid, '1=1');
$pending    = count_for_student($pdo, $uid, "status IN ('REPORTED','UNDER REVIEW','APPROVED')");
$active     = count_for_student($pdo, $uid, "status IN ('ASSIGNED','IN PROGRESS')");
$awaiting   = count_for_student($pdo, $uid, "status = 'RESOLVED'");

$stmt = $pdo->prepare("SELECT r.*, c.name AS category_name, f.name AS facility_name
    FROM reports r JOIN categories c ON c.id = r.category_id
    JOIN facilities f ON f.id = r.facility_id
    WHERE r.student_id = ? ORDER BY r.updated_at DESC LIMIT 5");
$stmt->execute([$uid]);
$reports = $stmt->fetchAll();

$page_title = 'My dashboard';
$active_nav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-4" style="flex-wrap:wrap;gap:1rem">
    <div><h1 style="font-size:1.6rem">Hello, <?= e(explode(' ', $_SESSION['user_name'])[0]) ?></h1><p class="text-muted">Your reported issues.</p></div>
    <a href="<?= e(url('student/report-new.php')) ?>" class="btn btn-primary"><i class="fas fa-circle-plus mr-2"></i>Report an issue</a>
</div>

<?php if ($awaiting): ?><div class="alert alert-warning mb-4"><i class="fas fa-star mr-2"></i>You have <?= (int)$awaiting ?> repair waiting for verification. <a href="<?= e(url('student/reports.php?status=RESOLVED')) ?>">Review now &rarr;</a></div><?php endif; ?>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-info"><div class="stat-label">Total</div><div class="stat-value"><?= (int)$total ?></div></div><div class="stat-icon"><i class="fas fa-inbox"></i></div></div>
    <div class="stat-card orange"><div class="stat-info"><div class="stat-label">Pending</div><div class="stat-value"><?= (int)$pending ?></div></div><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div></div>
    <div class="stat-card indigo"><div class="stat-info"><div class="stat-label">In progress</div><div class="stat-value"><?= (int)$active ?></div></div><div class="stat-icon"><i class="fas fa-screwdriver-wrench"></i></div></div>
    <div class="stat-card green"><div class="stat-info"><div class="stat-label">To verify</div><div class="stat-value"><?= (int)$awaiting ?></div></div><div class="stat-icon"><i class="fas fa-star"></i></div></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-between align-center"><h2 class="card-title">Recent reports</h2><a class="btn btn-outline btn-sm" href="<?= e(url('student/reports.php')) ?>">View all</a></div>
    <div class="card-body p-0">
        <?php if (!$reports): ?><div class="empty-state"><i class="fas fa-inbox"></i><h3>No reports yet</h3></div>
        <?php else: ?><div class="table-responsive"><table class="table">
            <thead><tr><th>Code</th><th>Issue</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
            <?php foreach ($reports as $r): ?><tr>
                <td class="tracking-code"><?= e($r['tracking_code']) ?></td>
                <td><strong><?= e($r['title']) ?></strong><div class="x-small text-muted"><?= e($r['facility_name']) ?></div></td>
                <td><?= format_status_badge($r['status']) ?></td>
                <td class="small text-muted nowrap"><?= e(time_ago($r['updated_at'])) ?></td>
                <td><a class="btn btn-outline btn-sm" href="<?= e(url('student/report-view.php?id=' . (int)$r['id'])) ?>">Open</a></td>
            </tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
