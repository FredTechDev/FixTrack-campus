<?php
/**
 * QR reporting hub - choose a facility to get its pre-filled report link.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$pdo = get_db();
$facilities = get_facilities($pdo);

$page_title = 'Report by QR';
$active_nav = 'new-report';
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4"><h1 style="font-size:1.7rem">Report by QR code</h1><p class="text-muted">QR codes posted around campus open the report form with the building already selected.</p></div>

<div class="alert alert-info mb-4"><i class="fas fa-circle-info mr-2"></i>If you scanned a code, you should already be on the right form. Otherwise choose your building below.</div>

<div class="feature-grid">
    <?php foreach ($facilities as $f): ?>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-building"></i></div>
            <h3><?= e($f['name']) ?></h3>
            <p><?= e($f['campus_zone']) ?> &middot; <?= e($f['building_code']) ?></p>
            <a class="btn btn-primary btn-sm mt-2" href="<?= e(url('guest-report.php?facility=' . (int)$f['id'])) ?>">Report an issue here</a>
            <a class="btn btn-outline btn-sm mt-2" href="<?= e(url('qr/facility.php?id=' . (int)$f['id'])) ?>">View label</a>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
