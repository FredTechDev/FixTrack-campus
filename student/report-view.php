<?php
/**
 * Student: full detail view with progress timeline and verification actions.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_STUDENT);
$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$report = get_report($pdo, $id);

if (!$report || (int)$report['student_id'] !== (int)$_SESSION['user_id']) {
    set_flash('error', 'Report not found or you do not have permission to view it.');
    redirect('student/reports.php');
}

$images      = get_report_images($pdo, $id);
$evidence    = array_filter($images, fn($i) => $i['image_type'] === 'evidence');
$completion  = array_filter($images, fn($i) => $i['image_type'] === 'completion');
$history     = get_status_history($pdo, $id);
$assignment  = get_report_assignment($pdo, $id);
$verification = null;
$vstmt = $pdo->prepare("SELECT * FROM repair_verifications WHERE report_id = ? LIMIT 1");
$vstmt->execute([$id]);
$verification = $vstmt->fetch() ?: null;

$page_title = 'Report ' . $report['tracking_code'];
$active_nav = 'my-reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="<?= e(url('student/reports.php')) ?>" class="btn btn-outline btn-sm mb-3"><i class="fas fa-arrow-left mr-1"></i>Back to my reports</a>
    <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:1rem">
        <div>
            <div class="tracking-code mb-1"><?= e($report['tracking_code']) ?></div>
            <h1 style="font-size:1.6rem"><?= e($report['title']) ?></h1>
            <div class="text-muted small mt-1">
                <i class="fas fa-building mr-1"></i><?= e($report['facility_name']) ?> &middot;
                <?= e($report['room_number']) ?> &middot;
                Reported <?= e(time_ago($report['created_at'])) ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?= format_status_badge($report['status']) ?>
            <?= format_priority_badge($report['priority']) ?>
            <?php if (is_overdue($report['deadline'], $report['status'])): ?><span class="badge badge-overdue">Overdue</span><?php endif; ?>
        </div>
    </div>
</div>

<div class="card mb-4"><div class="card-body">
    <?= workflow_stepper($report['status']) ?>
    <?php if ($report['status'] === STATUS_REJECTED): ?>
        <div class="reopen-banner mt-3"><i class="fas fa-circle-xmark mr-2"></i><strong>Report rejected:</strong> <?= e($report['rejection_reason']) ?></div>
    <?php endif; ?>
</div></div>

<div class="detail-grid">
    <div>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-align-left mr-2"></i>Description</h2></div>
            <div class="card-body"><div class="description-box"><?= e($report['description']) ?></div></div>
        </div>

        <?php if ($evidence): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-images mr-2"></i>Submitted evidence (<?= count($evidence) ?>)</h2></div>
            <div class="card-body"><div class="image-gallery">
                <?php foreach ($evidence as $img): ?>
                    <a class="gallery-item" href="<?= e(image_url($img['file_path'])) ?>" target="_blank" rel="noopener">
                        <img src="<?= e(image_url($img['file_path'])) ?>" alt="Evidence: <?= e($img['original_filename']) ?>" loading="lazy">
                        <figcaption><?= e($img['original_filename']) ?></figcaption>
                    </a>
                <?php endforeach; ?>
            </div></div>
        </div>
        <?php endif; ?>

        <?php if ($completion): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-image mr-2"></i>Completion photos (<?= count($completion) ?>)</h2></div>
            <div class="card-body"><div class="image-gallery">
                <?php foreach ($completion as $img): ?>
                    <a class="gallery-item" href="<?= e(image_url($img['file_path'])) ?>" target="_blank" rel="noopener">
                        <img src="<?= e(image_url($img['file_path'])) ?>" alt="Completion photo: <?= e($img['original_filename']) ?>" loading="lazy">
                        <figcaption><?= e($img['original_filename']) ?></figcaption>
                    </a>
                <?php endforeach; ?>
            </div></div>
        </div>
        <?php endif; ?>

        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-timeline mr-2"></i>Status history</h2></div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($history as $h): ?>
                        <div class="timeline-item">
                            <div class="timeline-point"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="timeline-title"><?= e($h['new_status']) ?></span>
                                    <span class="timeline-time"><?= e(format_date($h['created_at'])) ?></span>
                                </div>
                                <div class="small text-muted">By <?= e($h['changed_by_name']) ?> (<?= e(humanize($h['changed_by_role'])) ?>)</div>
                                <?php if ($h['notes']): ?><div class="small mt-1"><?= e($h['notes']) ?></div><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <aside>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-circle-info mr-2"></i>Report details</h2></div>
            <div class="card-body"><div class="meta-list">
                <div class="meta-item"><span class="meta-label">Tracking code</span><span class="meta-value tracking-code"><?= e($report['tracking_code']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Category</span><span class="meta-value"><?= e($report['category_name']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Facility</span><span class="meta-value"><?= e($report['facility_name']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Room / location</span><span class="meta-value"><?= e($report['room_number']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Priority</span><span class="meta-value"><?= format_priority_badge($report['priority']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Deadline</span><span class="meta-value"><?= $report['deadline'] ? e(format_date($report['deadline'])) : '&mdash;' ?></span></div>
                <div class="meta-item"><span class="meta-label">Submitted</span><span class="meta-value"><?= e(format_date($report['created_at'])) ?></span></div>
                <div class="meta-item"><span class="meta-label">Last updated</span><span class="meta-value"><?= e(time_ago($report['updated_at'])) ?></span></div>
            </div></div>
        </div>

        <?php if ($assignment): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-user-gear mr-2"></i>Assigned technician</h2></div>
            <div class="card-body">
                <div class="d-flex align-center gap-2 mb-3">
                    <span class="user-avatar"><?= e(strtoupper(mb_substr($assignment['technician_name'], 0, 2))) ?></span>
                    <div><strong><?= e($assignment['technician_name']) ?></strong><div class="x-small text-muted"><?= e($assignment['specialization'] ?? 'Maintenance technician') ?></div></div>
                </div>
                <div class="meta-list">
                    <div class="meta-item"><span class="meta-label">Email</span><span class="meta-value small text-break"><?= e($assignment['technician_email']) ?></span></div>
                    <div class="meta-item"><span class="meta-label">Phone</span><span class="meta-value small"><?= e($assignment['technician_phone'] ?? '&mdash;') ?></span></div>
                    <div class="meta-item"><span class="meta-label">Assigned</span><span class="meta-value small"><?= e(time_ago($assignment['assigned_at'])) ?></span></div>
                </div>
                <?php if ($assignment['assignment_notes']): ?>
                    <div class="info-note mt-3"><?= e($assignment['assignment_notes']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($verification): ?>
        <div class="card detail-panel"><div class="card-body">
            <h3 class="mb-2" style="font-size:1rem"><i class="fas fa-star mr-2"></i>Your verification</h3>
            <div class="small">Satisfied: <strong><?= $verification['is_satisfied'] ? 'Yes' : 'No' ?></strong></div>
            <div class="small">Rating: <span class="star-display"><?= str_repeat('★', (int)$verification['rating']) . str_repeat('☆', 5 - (int)$verification['rating']) ?></span></div>
            <?php if ($verification['feedback_notes']): ?><div class="small text-muted mt-2"><?= e($verification['feedback_notes']) ?></div><?php endif; ?>
        </div></div>
        <?php endif; ?>

        <?php if ($report['status'] === STATUS_RESOLVED): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-clipboard-check mr-2"></i>Verify this repair</h2></div>
            <div class="card-body">
                <p class="small text-muted mb-3">The technician marked this job as resolved. Please test the fix and tell us how it went.</p>
                <form method="post" action="<?= e(url('actions/report-verify.php')) ?>" data-validate novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                    <div class="form-group">
                        <label class="form-label">Was the issue fixed?</label>
                        <label style="display:inline-flex;align-items:center;gap:.4rem;margin-right:1rem"><input type="radio" name="satisfied" value="1" required checked> Yes, it's fixed</label>
                        <label style="display:inline-flex;align-items:center;gap:.4rem"><input type="radio" name="satisfied" value="0"> No, still broken</label>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="rating">Rate the repair</label>
                        <div class="star-rating" role="radiogroup" aria-label="Rating">
                            <?php for ($s = 5; $s >= 1; $s--): ?>
                                <input type="radio" id="star<?= $s ?>" name="rating" value="<?= $s ?>" <?= $s === 5 ? 'checked' : '' ?>><label for="star<?= $s ?>" title="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">&#9733;</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="feedback">Feedback</label>
                        <textarea class="form-control" id="feedback" name="feedback" rows="3" maxlength="2000" placeholder="Optional when satisfied. Required if the issue is not fixed."></textarea>
                    </div>
                    <button class="btn btn-success btn-block" type="submit"><i class="fas fa-check mr-2"></i>Submit verification</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (in_array($report['status'], [STATUS_VERIFIED, STATUS_CLOSED], true)): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title" style="color:var(--danger)"><i class="fas fa-rotate-left mr-2"></i>Still not right?</h2></div>
            <div class="card-body">
                <p class="small text-muted mb-3">If the problem came back, reopen the issue and the team will follow up.</p>
                <form method="post" action="<?= e(url('actions/report-reopen.php')) ?>" data-validate
                      data-confirm="Reopen this issue? The maintenance team will be notified." novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                    <div class="form-group">
                        <label class="form-label" for="reason">What is still wrong? <span class="required">*</span></label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required data-min="10" data-max="2000" data-label="Reason" placeholder="Describe the remaining problem..."></textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                    <button class="btn btn-danger btn-block" type="submit"><i class="fas fa-rotate-left mr-2"></i>Reopen issue</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </aside>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
