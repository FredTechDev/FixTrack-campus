<?php
/**
 * Administrator: printable QR codes that open a pre-filled report form.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_ADMIN);
$pdo = get_db();
$facilities = $pdo->query("SELECT * FROM facilities WHERE is_active = 1 ORDER BY name")->fetchAll();
$categories = get_categories($pdo);

$page_title = 'QR codes';
$active_nav = 'qr-codes';
$page_scripts = ['assets/vendor/qrcode/qrcode.min.js', 'assets/js/qr.js'];
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 style="font-size:1.7rem">QR-based equipment reporting</h1>
    <p class="text-muted">Print these codes and place them in each building. Scanning opens a report form with the facility pre-selected.</p>
</div>

<div class="alert alert-info mb-4"><i class="fas fa-circle-info mr-2"></i>The QR code encodes an absolute URL. After printing, confirm the QR text on the label matches this site's address.</div>

<div class="qr-grid">
    <?php foreach ($facilities as $f): ?>
        <?php $target = url('guest-report.php?facility=' . (int)$f['id']); ?>
        <div class="qr-card">
            <div data-qr-url="<?= e($target) ?>" data-qr-size="170" style="display:inline-block"></div>
            <h3><?= e($f['name']) ?></h3>
            <p><?= e($f['building_code']) ?> &middot; <?= e($f['campus_zone']) ?></p>
            <a class="btn btn-outline btn-sm mt-2" href="<?= e(url('qr/facility.php?id=' . (int)$f['id'])) ?>">Open label</a>
            <div class="x-small text-muted mt-2 text-break"><?= e($target) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mt-4"><div class="card-header"><h2 class="card-title"><i class="fas fa-link mr-2"></i>Direct links by category</h2></div>
    <div class="card-body">
        <p class="small text-muted">You can also link straight to a specific category form, for example:</p>
        <ul class="small">
            <?php foreach (array_slice($categories, 0, 4) as $c): ?>
                <li class="mb-1"><strong><?= e($c['name']) ?>:</strong> <span class="text-break"><?= e(url('guest-report.php?category=' . (int)$c['id'])) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
