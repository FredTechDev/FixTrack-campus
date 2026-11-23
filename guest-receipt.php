<?php
/** Confirmation page shown after a guest submits a report. */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();
$code = $_GET['code'] ?? '';
$token = $_GET['token'] ?? '';
$report = get_guest_report($pdo, $code, $token);

$page_title = 'Report submitted';
$active_nav = 'guest';
include __DIR__ . '/includes/header.php';
?>

<?php if (!$report): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><i class="fas fa-link-slash"></i><h3>Invalid or expired link</h3><p>Check the link from your confirmation page.</p><a class="btn btn-primary" href="<?= e(url('guest-report.php')) ?>">Submit a new report</a></div></div></div>
<?php else: ?>
    <?php $trackUrl = url('track.php?code=' . urlencode($report['tracking_code']) . '&token=' . urlencode($token)); ?>
    <div class="card" style="max-width:640px;margin:0 auto">
        <div class="card-body" style="padding:2.5rem;text-align:center">
            <i class="fas fa-circle-check" style="font-size:3rem;color:var(--success)"></i>
            <h1 style="font-size:1.5rem;margin:1rem 0 .4rem">Report submitted</h1>
            <p class="text-muted">The facilities team has been notified.</p>
            <div style="background:var(--primary-light);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:1.25rem;margin:1.5rem 0">
                <div class="small text-muted">Your tracking code</div>
                <div class="tracking-code" style="font-size:1.4rem"><?= e($report['tracking_code']) ?></div>
            </div>
            <h2 style="font-size:1.05rem">Save your private tracking link</h2>
            <p class="small text-muted">Bookmark it or copy it somewhere safe. Anyone with this link can view the status and confirm the repair.</p>
            <a class="btn btn-primary btn-block text-break" style="word-break:break-all" href="<?= e($trackUrl) ?>"><?= e($trackUrl) ?></a>
            <div class="d-flex gap-2 mt-3" style="justify-content:center;flex-wrap:wrap">
                <button class="btn btn-outline" onclick="navigator.clipboard && navigator.clipboard.writeText(<?= json_encode($trackUrl) ?>).then(()=>alert('Link copied'))"><i class="fas fa-copy mr-1"></i>Copy link</button>
                <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print mr-1"></i>Print</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
