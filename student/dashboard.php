<?php
/**
 * Student dashboard - personal report overview.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_STUDENT);
$pdo = get_db();
$uid = (int)$_SESSION['user_id'];

function student_count(PDO $pdo, int $uid, string $where, array $params = []): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE student_id = ? AND " . $where);
    $stmt->execute(array_merge([$uid], $params));
    return (int)$stmt->fetchColumn();
}

$total      = student_count($pdo, $uid, '1=1');
$pending    = student_count($pdo, $uid, "status IN ('REPORTED','UNDER REVIEW','APPROVED')");
$inProgress = student_count($pdo, $uid, "status IN ('ASSIGNED','IN PROGRESS')");
$resolved   = student_count($pdo, $uid, "status IN ('RESOLVED','VERIFIED','CLOSED')");
$awaiting   = student_count($pdo, $uid, "status = 'RESOLVED'");
$overdue    = student_count($pdo, $uid, "deadline < NOW() AND status NOT IN ('RESOLVED','VERIFIED','CLOSED','REJECTED')");

$stmt = $pdo->prepare("
    SELECT r.*, c.name AS category_name, f.name AS facility_name, a.full_name AS technician_name
    FROM reports r
    JOIN categories c ON c.id = r.category_id
    JOIN facilities f ON f.id = r.facility_id
    LEFT JOIN assignments asg ON asg.report_id = r.id AND asg.status <> 'reassigned'
    LEFT JOIN users a ON a.id = asg.technician_id
    WHERE r.student_id = ?
    ORDER BY r.updated_at DESC
    LIMIT 6
");
$stmt->execute([$uid]);
$recent = $stmt->fetchAll();

$nstmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
$nstmt->execute([$uid]);
$notifications = $nstmt->fetchAll();

$page_title = 'My dashboard';
$active_nav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-between align-center mb-4" style="flex-wrap:wrap;gap:1rem">
    <div>
        <h1 style="font-size:1.7rem">Hello, <?= e(explode(' ', $_SESSION['user_name'])[0]) ?> 👋</h1>
        <p class="text-muted">Here's what's happening with your reported issues.</p>
    </div>
    <a href="<?= e(url('student/report-new.php')) ?>" class="btn btn-primary"><i class="fas fa-circle-plus mr-2"></i>Report an issue</a>
</div>

<?php if ($awaiting > 0): ?>
    <div class="alert alert-warning alert-auto mb-4">
        <i class="fas fa-star mr-2"></i>
        You have <strong><?= (int)$awaiting ?></strong> repair<?= $awaiting === 1 ? '' : 's' ?> waiting for your verification.
        <a href="<?= e(url('student/reports.php?status=RESOLVED')) ?>" class="font-bold">Review now &rarr;</a>
    </div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-info"><div class="stat-label">Total reports</div><div class="stat-value"><?= (int)$total ?></div><div class="stat-desc">All issues you've submitted</div></div><div class="stat-icon"><i class="fas fa-inbox"></i></div></div>
    <div class="stat-card orange"><div class="stat-info"><div class="stat-label">Pending</div><div class="stat-value"><?= (int)$pending ?></div><div class="stat-desc">Awaiting admin review</div></div><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div></div>
    <div class="stat-card indigo"><div class="stat-info"><div class="stat-label">In progress</div><div class="stat-value"><?= (int)$inProgress ?></div><div class="stat-desc">Assigned or being repaired</div></div><div class="stat-icon"><i class="fas fa-screwdriver-wrench"></i></div></div>
    <div class="stat-card green"><div class="stat-info"><div class="stat-label">Resolved</div><div class="stat-value"><?= (int)$resolved ?></div><div class="stat-desc">Verified or closed tickets</div></div><div class="stat-icon"><i class="fas fa-circle-check"></i></div></div>
</div>

<div class="detail-grid">
    <div>
        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <h2 class="card-title"><i class="fas fa-clock-rotate-left mr-2"></i>Recently updated</h2>
                <a href="<?= e(url('student/reports.php')) ?>" class="btn btn-outline btn-sm">View all</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$recent): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><h3>No reports yet</h3><p>When you submit your first issue, it will appear here.</p><a href="<?= e(url('student/report-new.php')) ?>" class="btn btn-primary">Submit a report</a></div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table" id="recentReports">
                        <thead><tr><th>Tracking code</th><th>Issue</th><th>Priority</th><th>Status</th><th>Updated</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td class="tracking-code"><?= e($r['tracking_code']) ?></td>
                                <td><strong><?= e(mb_substr($r['title'], 0, 48)) ?></strong><div class="x-small text-muted"><?= e($r['facility_name']) ?> &middot; <?= e($r['room_number']) ?></div></td>
                                <td><?= format_priority_badge($r['priority']) ?></td>
                                <td>
                                    <?= format_status_badge($r['status']) ?>
                                    <?php if (is_overdue($r['deadline'], $r['status'])): ?><span class="badge badge-overdue ml-1">Overdue</span><?php endif; ?>
                                </td>
                                <td class="small text-muted nowrap"><?= e(time_ago($r['updated_at'])) ?></td>
                                <td><a href="<?= e(url('student/report-view.php?id=' . (int)$r['id'])) ?>" class="btn btn-outline btn-sm">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <aside>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-bell mr-2"></i>Latest alerts</h2></div>
            <div class="card-body">
                <?php if (!$notifications): ?><p class="text-muted small">No notifications yet.</p>
                <?php else: foreach ($notifications as $n): ?>
                    <div style="padding:.6rem 0;border-bottom:1px solid var(--border-color)">
                        <div class="small font-bold <?= $n['is_read'] ? '' : 'text-primary' ?>"><?= e($n['title']) ?></div>
                        <div class="x-small text-muted"><?= e(mb_substr($n['message'], 0, 90)) ?></div>
                        <div class="x-small text-muted mt-1"><?= e(time_ago($n['created_at'])) ?></div>
                    </div>
                <?php endforeach; endif; ?>
                <a href="<?= e(url('notifications.php')) ?>" class="btn btn-outline btn-sm btn-block mt-3">All notifications</a>
            </div>
        </div>

        <?php if ($overdue > 0): ?>
        <div class="card detail-panel"><div class="card-body">
            <div class="info-note" style="border-color:var(--danger);background:var(--danger-light);color:#991b1b">
                <i class="fas fa-triangle-exclamation mr-2"></i><strong><?= (int)$overdue ?></strong> of your active reports are past their response deadline.
            </div>
        </div></div>
        <?php endif; ?>

        <div class="card"><div class="card-body text-center">
            <i class="fas fa-qrcode" style="font-size:2rem;color:var(--primary)"></i>
            <h3 class="mt-2" style="font-size:1rem">Report by QR code</h3>
            <p class="text-muted small">Scan the code posted in your building to open a pre-filled report form.</p>
            <a href="<?= e(url('qr/report.php')) ?>" class="btn btn-outline btn-sm">Try the QR form</a>
        </div></div>
    </aside>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
