<?php
/**
 * A printable QR label for a single facility.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare("SELECT * FROM facilities WHERE id = ? AND is_active = 1");
$s->execute([$id]);
$facility = $s->fetch();
if (!$facility) {
    set_flash('error', 'Facility not found.');
    redirect('qr/report.php');
}
$target = url('student/report-new.php?facility=' . (int)$facility['id']);

$page_title = 'QR label - ' . $facility['name'];
$active_nav = '';
$page_scripts = ['assets/vendor/qrcode/qrcode.min.js', 'assets/js/qr.js'];
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4"><a href="<?= e(url('qr/report.php')) ?>" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left mr-1"></i>All QR labels</a></div>

<div class="card" style="max-width:520px;margin:0 auto;text-align:center">
    <div class="card-body" style="padding:3rem">
        <div style="font-size:1.1rem;font-weight:800;letter-spacing:.08em;color:var(--primary)"><?= e(APP_NAME) ?></div>
        <h1 style="font-size:1.5rem;margin:1rem 0 .3rem"><?= e($facility['name']) ?></h1>
        <p class="text-muted"><?= e($facility['building_code']) ?> &middot; <?= e($facility['campus_zone']) ?></p>
        <div data-qr-url="<?= e($target) ?>" data-qr-size="240" style="display:inline-block;margin:1.5rem 0"></div>
        <h2 style="font-size:1.2rem">Something broken here?</h2>
        <p class="text-muted small">Scan this code with your phone camera to report a maintenance issue in this building.</p>
        <a class="btn btn-primary btn-lg mt-3" href="<?= e($target) ?>"><i class="fas fa-qrcode mr-2"></i>Open report form</a>
        <div class="x-small text-muted mt-4 text-break"><?= e($target) ?></div>
    </div>
</div>

<div class="text-center mt-4"><button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print mr-2"></i>Print this label</button></div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
