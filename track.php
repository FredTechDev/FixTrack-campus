<?php
/** Public tracking page for guest reports - secured by tracking code + private token. */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();
$code = $_GET['code'] ?? '';
$token = $_GET['token'] ?? '';
$report = get_guest_report($pdo, $code, $token);

$page_title = 'Track your report';
$active_nav = 'guest';
include __DIR__ . '/includes/header.php';
?>

<?php if (!$report): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><i class="fas fa-link-slash"></i><h3>Invalid or expired link</h3><p>Check the private link from your confirmation page.</p></div></div></div>
<?php else: ?>
    <?php
    $images = get_report_images($pdo, (int)$report['id']);
    $history = get_status_history($pdo, (int)$report['id']);
    $trackUrl = url('track.php?code=' . urlencode($code) . '&token=' . urlencode($token));
    ?>
    <div class="mb-4">
        <div class="tracking-code"><?= e($report['tracking_code']) ?></div>
        <h1 style="font-size:1.5rem"><?= e($report['title']) ?></h1>
        <div class="text-muted small"><?= e($report['facility_name']) ?> &middot; <?= e($report['room_number']) ?> &middot; Reported <?= e(time_ago($report['created_at'])) ?></div>
    </div>

    <div class="card mb-4"><div class="card-body"><?= workflow_stepper($report['status']) ?></div></div>

    <div class="detail-grid">
        <div>
            <div class="card detail-panel"><div class="card-header"><h2 class="card-title">Description</h2></div>
                <div class="card-body"><div class="description-box"><?= e($report['description']) ?></div></div></div>

            <?php if ($images): ?>
            <div class="card detail-panel"><div class="card-header"><h2 class="card-title">Photos</h2></div>
                <div class="card-body"><div class="image-gallery">
                    <?php foreach ($images as $img): ?><a class="gallery-item" href="<?= e(image_url($img['file_path'])) ?>" target="_blank" rel="noopener"><img src="<?= e(image_url($img['file_path'])) ?>" alt="Report photo" loading="lazy"></a><?php endforeach; ?>
                </div></div></div>
            <?php endif; ?>

            <div class="card detail-panel"><div class="card-header"><h2 class="card-title">Status history</h2></div>
                <div class="card-body"><div class="timeline">
                    <?php foreach ($history as $h): ?><div class="timeline-item"><div class="timeline-point"></div><div class="timeline-content">
                        <div class="timeline-header"><span class="timeline-title"><?= e($h['new_status']) ?></span><span class="timeline-time"><?= e(format_date($h['created_at'])) ?></span></div>
                        <?php if ($h['notes']): ?><div class="small text-muted"><?= e($h['notes']) ?></div><?php endif; ?>
                    </div></div><?php endforeach; ?>
                </div></div></div>
        </div>

        <aside>
            <div class="card detail-panel"><div class="card-header"><h2 class="card-title">Current status</h2></div>
                <div class="card-body">
                    <div class="mb-3"><?= format_status_badge($report['status']) ?> <?= format_priority_badge($report['priority']) ?></div>
                    <div class="meta-list">
                        <div class="meta-item"><span class="meta-label">Category</span><span class="meta-value"><?= e($report['category_name']) ?></span></div>
                        <div class="meta-item"><span class="meta-label">Building</span><span class="meta-value"><?= e($report['facility_name']) ?></span></div>
                        <div class="meta-item"><span class="meta-label">Location</span><span class="meta-value"><?= e($report['room_number']) ?></span></div>
                        <div class="meta-item"><span class="meta-label">Deadline</span><span class="meta-value small"><?= $report['deadline'] ? e(format_date($report['deadline'])) : '&mdash;' ?></span></div>
                    </div>
                    <?php if ($assignment = get_report_assignment($pdo, (int)$report['id'])): ?>
                        <div class="info-note mt-3">Assigned to <strong><?= e($assignment['technician_name']) ?></strong>.</div>
                    <?php endif; ?>
                    <?php if ($report['status'] === STATUS_REJECTED): ?>
                        <div class="reopen-banner mt-3"><strong>Rejected:</strong> <?= e($report['rejection_reason']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($report['status'] === STATUS_RESOLVED): ?>
            <div class="card detail-panel"><div class="card-header"><h2 class="card-title">Was it fixed?</h2></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('actions/guest-verify.php')) ?>" data-validate novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="code" value="<?= e($code) ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
                        <label style="display:block;margin-bottom:.75rem"><input type="radio" name="satisfied" value="1" required checked> Yes, it's fixed</label>
                        <label style="display:block;margin-bottom:1rem"><input type="radio" name="satisfied" value="0"> No, still broken</label>
                        <div class="form-group"><label class="form-label x-small" for="guest_rating">Rating</label>
                            <select class="form-control" id="guest_rating" name="rating"><?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>" <?= $s === 5 ? 'selected' : '' ?>><?= $s ?> star<?= $s > 1 ? 's' : '' ?></option><?php endfor; ?></select></div>
                        <div class="form-group"><label class="form-label x-small" for="guest_feedback">Feedback</label>
                            <textarea class="form-control" id="guest_feedback" name="feedback" rows="3" maxlength="2000" placeholder="Optional if fixed; required if not."></textarea></div>
                        <button class="btn btn-success btn-block" type="submit">Submit</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($report['status'], [STATUS_VERIFIED, STATUS_CLOSED], true)): ?>
            <div class="card detail-panel"><div class="card-header"><h2 class="card-title">Still not right?</h2></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('actions/guest-reopen.php')) ?>" data-validate novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="code" value="<?= e($code) ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
                        <div class="form-group"><label class="form-label" for="guest_reason">What is still wrong? <span class="required">*</span></label>
                            <textarea class="form-control" id="guest_reason" name="reason" rows="3" required data-min="10" data-max="2000" data-label="Reason"></textarea><div class="invalid-feedback"></div></div>
                        <button class="btn btn-danger btn-block" type="submit">Reopen issue</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="card"><div class="card-body small text-muted text-break">Bookmark this page to return: <a href="<?= e($trackUrl) ?>"><?= e($trackUrl) ?></a></div></div>
        </aside>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
