<?php
/**
 * Technician: view job details, update status and submit completion evidence.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_TECHNICIAN);
$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$uid = (int)$_SESSION['user_id'];
$report = get_report($pdo, $id);
$assignment = get_report_assignment($pdo, $id);

if (!$report || !$assignment || (int)$assignment['technician_id'] !== $uid) {
    set_flash('error', 'Job not found or not assigned to you.');
    redirect('technician/jobs.php');
}

$images = get_report_images($pdo, $id);
$evidence = array_filter($images, fn($i) => $i['image_type'] === 'evidence');
$completion = array_filter($images, fn($i) => $i['image_type'] === 'completion');
$history = get_status_history($pdo, $id);

$page_title = 'Job ' . $report['tracking_code'];
$active_nav = 'jobs';
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="<?= e(url('technician/jobs.php')) ?>" class="btn btn-outline btn-sm mb-3"><i class="fas fa-arrow-left mr-1"></i>Back to jobs</a>
    <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:1rem">
        <div>
            <div class="tracking-code mb-1"><?= e($report['tracking_code']) ?></div>
            <h1 style="font-size:1.6rem"><?= e($report['title']) ?></h1>
            <div class="text-muted small mt-1"><i class="fas fa-building mr-1"></i><?= e($report['facility_name']) ?> &middot; <?= e($report['room_number']) ?> &middot; Assigned <?= e(time_ago($assignment['assigned_at'])) ?></div>
        </div>
        <div class="d-flex gap-2">
            <?= format_status_badge($report['status']) ?>
            <?= format_priority_badge($report['priority']) ?>
            <?php if (is_overdue($report['deadline'], $report['status'])): ?><span class="badge badge-overdue">Overdue</span><?php endif; ?>
        </div>
    </div>
</div>

<div class="card mb-4"><div class="card-body"><?= workflow_stepper($report['status']) ?></div></div>

<div class="detail-grid">
    <div>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-align-left mr-2"></i>Issue description</h2></div>
            <div class="card-body"><div class="description-box"><?= e($report['description']) ?></div></div>
        </div>

        <?php if ($evidence): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-images mr-2"></i>Submitted evidence (<?= count($evidence) ?>)</h2></div>
            <div class="card-body"><div class="image-gallery">
                <?php foreach ($evidence as $img): ?><a class="gallery-item" href="<?= e(image_url($img['file_path'])) ?>" target="_blank" rel="noopener"><img src="<?= e(image_url($img['file_path'])) ?>" alt="Evidence" loading="lazy"><figcaption><?= e($img['original_filename']) ?></figcaption></a><?php endforeach; ?>
            </div></div>
        </div>
        <?php endif; ?>

        <?php if ($completion): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-image mr-2"></i>Photos you uploaded (<?= count($completion) ?>)</h2></div>
            <div class="card-body"><div class="image-gallery">
                <?php foreach ($completion as $img): ?><a class="gallery-item" href="<?= e(image_url($img['file_path'])) ?>" target="_blank" rel="noopener"><img src="<?= e(image_url($img['file_path'])) ?>" alt="Completion" loading="lazy"><figcaption><?= e($img['original_filename']) ?></figcaption></a><?php endforeach; ?>
            </div></div>
        </div>
        <?php endif; ?>

        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-timeline mr-2"></i>Update history</h2></div>
            <div class="card-body"><div class="timeline">
                <?php foreach ($history as $h): ?>
                    <div class="timeline-item"><div class="timeline-point"></div><div class="timeline-content">
                        <div class="timeline-header"><span class="timeline-title"><?= e($h['new_status']) ?></span><span class="timeline-time"><?= e(format_date($h['created_at'])) ?></span></div>
                        <div class="small text-muted">By <?= e($h['changed_by_name']) ?></div>
                        <?php if ($h['notes']): ?><div class="small mt-1"><?= e($h['notes']) ?></div><?php endif; ?>
                    </div></div>
                <?php endforeach; ?>
            </div></div>
        </div>
    </div>

    <aside>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-circle-info mr-2"></i>Job details</h2></div>
            <div class="card-body"><div class="meta-list">
                <div class="meta-item"><span class="meta-label">Category</span><span class="meta-value"><?= e($report['category_name']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Facility</span><span class="meta-value"><?= e($report['facility_name']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Location</span><span class="meta-value"><?= e($report['room_number']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Zone</span><span class="meta-value"><?= e($report['campus_zone']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Priority</span><span class="meta-value"><?= format_priority_badge($report['priority']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Deadline</span><span class="meta-value small"><?= $report['deadline'] ? e(format_date($report['deadline'])) : '&mdash;' ?></span></div>
                <div class="meta-item"><span class="meta-label">Reporter</span><span class="meta-value small"><?= e($report['student_name']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Contact</span><span class="meta-value small text-break"><?= e($report['student_email']) ?></span></div>
            </div>
            <?php if ($assignment['assignment_notes']): ?><div class="info-note mt-3"><?= e($assignment['assignment_notes']) ?></div><?php endif; ?>
        </div></div>

        <?php if ($report['status'] === STATUS_ASSIGNED): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-play mr-2"></i>Start this job</h2></div>
            <div class="card-body">
                <p class="small text-muted">Let the student and admin know you're on site.</p>
                <form method="post" action="<?= e(url('actions/technician-update.php')) ?>" data-validate novalidate>
                    <?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>"><input type="hidden" name="action" value="start">
                    <div class="form-group"><label class="form-label x-small" for="start_notes">Arrival notes</label>
                        <textarea class="form-control" id="start_notes" name="notes" rows="3" maxlength="2000" placeholder="e.g. Arrived on site, isolating power at the distribution board."></textarea></div>
                    <button class="btn btn-indigo btn-block" type="submit" style="background:var(--indigo);color:#fff"><i class="fas fa-play mr-2"></i>Mark as in progress</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (in_array($report['status'], [STATUS_ASSIGNED, STATUS_IN_PROGRESS], true)): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-clipboard-check mr-2"></i>Submit completed repair</h2></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('actions/technician-update.php')) ?>" enctype="multipart/form-data" data-validate novalidate>
                    <?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>"><input type="hidden" name="action" value="resolve">
                    <div class="form-group">
                        <label class="form-label" for="repair_notes">Repair notes <span class="required">*</span></label>
                        <textarea class="form-control" id="repair_notes" name="notes" rows="5" required data-min="10" data-max="5000" data-label="Repair notes" placeholder="What did you inspect, replace or repair? Any follow-up needed?"></textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                    <label class="form-label x-small">Completion photographs <span class="text-muted small">(optional, up to 5)</span></label>
                    <div class="dropzone-box">
                        <i class="fas fa-camera dropzone-icon"></i>
                        <div class="dropzone-text">Drag photos here or <strong>browse</strong></div>
                        <div class="form-hint">JPG, PNG or WebP &middot; max 5 MB each</div>
                        <input type="file" name="completion_photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                    </div>
                    <div class="preview-container mt-3"></div>
                    <div class="invalid-feedback d-none mt-2" data-upload-error></div>
                    <button class="btn btn-success btn-block mt-3" type="submit" data-confirm="Submit this repair for student verification?"><i class="fas fa-check mr-2"></i>Submit for verification</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($report['status'] === STATUS_RESOLVED): ?>
        <div class="card detail-panel"><div class="card-body"><div class="info-note"><i class="fas fa-hourglass-half mr-2"></i>Waiting for the student to verify this repair. You'll be notified if they reopen it.</div></div></div>
        <?php endif; ?>
    </aside>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
