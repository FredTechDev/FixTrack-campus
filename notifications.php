<?php
/**
 * All roles: notification centre.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$pdo = get_db();
$uid = (int)$_SESSION['user_id'];
$role = $_SESSION['user_role'];

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
$stmt->execute([$uid]);
$notifications = $stmt->fetchAll();
$unread = get_unread_notifications_count($pdo, $uid);

$reportBase = $role === ROLE_STUDENT ? 'student/report-view.php' : ($role === ROLE_ADMIN ? 'admin/report-view.php' : 'technician/job-view.php');

$page_title = 'Notifications';
$active_nav = 'notifications';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-4" style="flex-wrap:wrap;gap:1rem">
    <div><h1 style="font-size:1.7rem">Notifications</h1><p class="text-muted"><?= (int)$unread ?> unread alert<?= $unread === 1 ? '' : 's' ?></p></div>
    <?php if ($unread > 0): ?>
    <form method="post" action="<?= e(url('actions/notification-read.php')) ?>" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="all" value="1">
        <button class="btn btn-outline" type="submit"><i class="fas fa-check-double mr-2"></i>Mark all as read</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (!$notifications): ?>
            <div class="empty-state"><i class="fas fa-bell"></i><h3>No notifications yet</h3><p>Updates about your reports will appear here.</p></div>
        <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <?php
            $icon = [
                'assignment' => 'user-check', 'status_change' => 'arrows-rotate',
                'verification_needed' => 'star', 'rejection' => 'circle-xmark',
                'critical_alert' => 'triangle-exclamation', 'new_report' => 'inbox',
                'verification' => 'circle-check', 'reopen' => 'rotate-left',
            ][$n['type']] ?? 'bell';
            ?>
            <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border-color);display:flex;gap:1rem;align-items:flex-start;<?= $n['is_read'] ? '' : 'background:var(--primary-light)' ?>">
                <span class="stat-icon" style="width:38px;height:38px;font-size:1rem;flex-shrink:0;background:#fff;color:var(--primary);border:1px solid var(--border-color)"><i class="fas fa-<?= e($icon) ?>"></i></span>
                <div style="flex:1">
                    <div class="font-bold <?= $n['is_read'] ? '' : 'text-primary' ?>"><?= e($n['title']) ?></div>
                    <div class="small text-muted mt-1"><?= e($n['message']) ?></div>
                    <div class="x-small text-muted mt-1"><i class="fas fa-clock mr-1"></i><?= e(format_date($n['created_at'])) ?></div>
                    <?php if ($n['report_id']): ?>
                        <a class="btn btn-outline btn-sm mt-2" href="<?= e(url($reportBase . '?id=' . (int)$n['report_id'])) ?>">View report</a>
                    <?php endif; ?>
                </div>
                <?php if (!$n['is_read']): ?>
                <form method="post" action="<?= e(url('actions/notification-read.php')) ?>" class="inline-form">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                    <button class="btn btn-outline btn-sm" type="submit" title="Mark as read"><i class="fas fa-check"></i></button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
