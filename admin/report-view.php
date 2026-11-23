<?php
/**
 * Administrator: manage a single report through review, assignment and closure.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_ADMIN);
$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$report = get_report($pdo, $id);
if (!$report) {
    set_flash('error', 'Report not found.');
    redirect('admin/reports.php');
}

$images      = get_report_images($pdo, $id);
$evidence    = array_filter($images, fn($i) => $i['image_type'] === 'evidence');
$completion  = array_filter($images, fn($i) => $i['image_type'] === 'completion');
$history     = get_status_history($pdo, $id);
$assignment  = get_report_assignment($pdo, $id);
$technicians = get_technicians($pdo, (int)$report['category_id']);
$allTechs    = $pdo->query("SELECT id, full_name, specialization FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY full_name")->fetchAll();
$verification = null;
$vstmt = $pdo->prepare("SELECT v.*, u.full_name AS student_name FROM repair_verifications v JOIN users u ON u.id = v.student_id WHERE v.report_id = ? LIMIT 1");
$vstmt->execute([$id]);
$verification = $vstmt->fetch() ?: null;

$page_title = 'Manage ' . $report['tracking_code'];
$active_nav = 'reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="<?= e(url('admin/reports.php')) ?>" class="btn btn-outline btn-sm mb-3"><i class="fas fa-arrow-left mr-1"></i>All reports</a>
    <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:1rem">
        <div>
            <div class="tracking-code mb-1"><?= e($report['tracking_code']) ?></div>
            <h1 style="font-size:1.6rem"><?= e($report['title']) ?></h1>
            <div class="text-muted small mt-1">
                <i class="fas fa-building mr-1"></i><?= e($report['facility_name']) ?> &middot; <?= e($report['room_number']) ?> &middot;
                Reported by <?= e($report['student_name']) ?>
                <?php if ($report['is_guest']): ?><span class="badge badge-soft">Guest report</span><?php endif; ?>
                <?= e(time_ago($report['created_at'])) ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?= format_status_badge($report['status']) ?>
            <?= format_priority_badge($report['priority']) ?>
            <?php if (is_overdue($report['deadline'], $report['status'])): ?><span class="badge badge-overdue">Overdue</span><?php endif; ?>
        </div>
    </div>
</div>

<div class="card mb-4"><div class="card-body"><?= workflow_stepper($report['status']) ?></div></div>

<?php if ($report['status'] === STATUS_REJECTED): ?>
<div class="reopen-banner mb-4"><i class="fas fa-circle-xmark mr-2"></i><strong>Rejected:</strong> <?= e($report['rejection_reason']) ?></div>
<?php endif; ?>

<div class="detail-grid">
    <div>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-align-left mr-2"></i>Description</h2></div>
            <div class="card-body"><div class="description-box"><?= e($report['description']) ?></div></div>
        </div>

        <?php if ($evidence): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-images mr-2"></i>Evidence (<?= count($evidence) ?>)</h2></div>
            <div class="card-body"><div class="image-gallery">
                <?php foreach ($evidence as $img): ?><a class="gallery-item" href="<?= e(image_url($img['file_path'])) ?>" target="_blank" rel="noopener"><img src="<?= e(image_url($img['file_path'])) ?>" alt="Evidence" loading="lazy"><figcaption><?= e($img['original_filename']) ?></figcaption></a><?php endforeach; ?>
            </div></div>
        </div>
        <?php endif; ?>

        <?php if ($completion): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-image mr-2"></i>Completion photos (<?= count($completion) ?>)</h2></div>
            <div class="card-body"><div class="image-gallery">
                <?php foreach ($completion as $img): ?><a class="gallery-item" href="<?= e(image_url($img['file_path'])) ?>" target="_blank" rel="noopener"><img src="<?= e(image_url($img['file_path'])) ?>" alt="Completion" loading="lazy"><figcaption><?= e($img['original_filename']) ?></figcaption></a><?php endforeach; ?>
            </div></div>
        </div>
        <?php endif; ?>

        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-timeline mr-2"></i>Status history</h2></div>
            <div class="card-body"><div class="timeline">
                <?php foreach ($history as $h): ?>
                    <div class="timeline-item"><div class="timeline-point"></div><div class="timeline-content">
                        <div class="timeline-header"><span class="timeline-title"><?= e($h['new_status']) ?></span><span class="timeline-time"><?= e(format_date($h['created_at'])) ?></span></div>
                        <div class="small text-muted">By <?= e($h['changed_by_name']) ?> (<?= e(humanize($h['changed_by_role'])) ?>)</div>
                        <?php if ($h['notes']): ?><div class="small mt-1"><?= e($h['notes']) ?></div><?php endif; ?>
                    </div></div>
                <?php endforeach; ?>
            </div></div>
        </div>
    </div>

    <aside>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-circle-info mr-2"></i>Report details</h2></div>
            <div class="card-body"><div class="meta-list">
                <div class="meta-item"><span class="meta-label">Category</span><span class="meta-value"><?= e($report['category_name']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Facility</span><span class="meta-value"><?= e($report['facility_name']) ?> (<?= e($report['building_code']) ?>)</span></div>
                <div class="meta-item"><span class="meta-label">Zone</span><span class="meta-value"><?= e($report['campus_zone']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Room</span><span class="meta-value"><?= e($report['room_number']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Reporter</span><span class="meta-value"><?= e($report['student_name']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Contact</span><span class="meta-value small text-break"><?= e($report['student_email']) ?></span></div>
                <div class="meta-item"><span class="meta-label">Student ID</span><span class="meta-value small"><?= e($report['student_identifier'] ?? '&mdash;') ?></span></div>
                <div class="meta-item"><span class="meta-label">Deadline</span><span class="meta-value small"><?= $report['deadline'] ? e(format_date($report['deadline'])) : '&mdash;' ?></span></div>
                <div class="meta-item"><span class="meta-label">Submitted</span><span class="meta-value small"><?= e(format_date($report['created_at'])) ?></span></div>
            </div></div>
        </div>

        <?php if ($assignment): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-user-gear mr-2"></i>Current assignment</h2></div>
            <div class="card-body">
                <div class="d-flex align-center gap-2 mb-3"><span class="user-avatar"><?= e(strtoupper(mb_substr($assignment['technician_name'], 0, 2))) ?></span>
                    <div><strong><?= e($assignment['technician_name']) ?></strong><div class="x-small text-muted"><?= e($assignment['specialization'] ?? 'Technician') ?></div></div>
                </div>
                <div class="meta-list">
                    <div class="meta-item"><span class="meta-label">Assigned</span><span class="meta-value small"><?= e(format_date($assignment['assigned_at'])) ?></span></div>
                    <div class="meta-item"><span class="meta-label">Status</span><span class="meta-value small"><?= e(humanize($assignment['status'])) ?></span></div>
                    <?php if ($assignment['completed_at']): ?><div class="meta-item"><span class="meta-label">Completed</span><span class="meta-value small"><?= e(format_date($assignment['completed_at'])) ?></span></div><?php endif; ?>
                </div>
                <?php if ($assignment['assignment_notes']): ?><div class="info-note mt-3"><?= e($assignment['assignment_notes']) ?></div><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($verification): ?>
        <div class="card detail-panel"><div class="card-body">
            <h3 class="mb-2" style="font-size:1rem"><i class="fas fa-star mr-2"></i>Student verification</h3>
            <div class="small">Satisfied: <strong><?= $verification['is_satisfied'] ? 'Yes' : 'No' ?></strong> &middot; Rating: <span class="star-display"><?= str_repeat('★', (int)$verification['rating']) ?></span></div>
            <?php if ($verification['feedback_notes']): ?><div class="small text-muted mt-2"><?= e($verification['feedback_notes']) ?></div><?php endif; ?>
        </div></div>
        <?php endif; ?>

        <?php if (in_array($report['status'], [STATUS_REPORTED, STATUS_UNDER_REVIEW], true)): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-magnifying-glass mr-2"></i>Review report</h2></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('actions/report-review.php')) ?>" data-validate novalidate>
                    <?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                    <div class="form-group">
                        <label class="form-label x-small" for="review_notes">Notes / rejection reason</label>
                        <textarea class="form-control" id="review_notes" name="notes" rows="3" maxlength="2000" placeholder="Optional for approval; required for rejection."></textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="d-flex gap-2" style="flex-wrap:wrap">
                        <?php if ($report['status'] === STATUS_REPORTED): ?>
                            <button class="btn btn-outline" type="submit" name="decision" value="review"><i class="fas fa-eye mr-1"></i>Under review</button>
                        <?php endif; ?>
                        <button class="btn btn-success" type="submit" name="decision" value="approve"><i class="fas fa-check mr-1"></i>Approve</button>
                        <button class="btn btn-danger" type="submit" name="decision" value="reject" data-confirm="Reject this report? The student will be notified."><i class="fas fa-xmark mr-1"></i>Reject</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (in_array($report['status'], [STATUS_APPROVED, STATUS_ASSIGNED], true)): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-user-plus mr-2"></i><?= $assignment ? 'Reassign technician' : 'Assign technician' ?></h2></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('actions/report-assign.php')) ?>" data-validate novalidate>
                    <?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                    <div class="form-group">
                        <label class="form-label" for="technician_id">Technician <span class="required">*</span></label>
                        <select class="form-control" id="technician_id" name="technician_id" required data-label="Technician">
                            <option value="">Choose a technician</option>
                            <optgroup label="Suggested for this category">
                                <?php foreach ($technicians as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $assignment && (int)$assignment['technician_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?> &mdash; <?= e($t['specialization'] ?? 'General') ?></option><?php endforeach; ?>
                            </optgroup>
                            <?php if (count($allTechs) > count($technicians)): ?>
                            <optgroup label="All technicians">
                                <?php foreach ($allTechs as $t): if (in_array((int)$t['id'], array_column($technicians, 'id'), true)) continue; ?><option value="<?= (int)$t['id'] ?>" <?= $assignment && (int)$assignment['technician_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option><?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label x-small" for="deadline_hours">Response deadline (hours)</label>
                        <input class="form-control" type="number" id="deadline_hours" name="deadline_hours" min="1" max="720" value="<?= (int)(PRIORITIES[$report['priority']]['default_hours'] ?? 48) ?>">
                        <div class="form-hint">Leave blank to use the priority default of <?= (int)(PRIORITIES[$report['priority']]['default_hours'] ?? 48) ?>h.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label x-small" for="assign_notes">Instructions for technician</label>
                        <textarea class="form-control" id="assign_notes" name="notes" rows="3" maxlength="2000" placeholder="Access notes, safety requirements, expected work..."><?= e($assignment['assignment_notes'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-paper-plane mr-2"></i><?= $assignment ? 'Reassign job' : 'Assign job' ?></button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!in_array($report['status'], [STATUS_CLOSED, STATUS_REJECTED], true)): ?>
        <div class="card detail-panel">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-flag mr-2"></i>Priority &amp; deadline</h2></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('actions/report-priority.php')) ?>" data-validate novalidate>
                    <?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label x-small" for="priority">Priority</label>
                            <select class="form-control" id="priority" name="priority"><?php foreach (PRIORITIES as $key => $p): ?><option value="<?= e($key) ?>" <?= $report['priority'] === $key ? 'selected' : '' ?>><?= e($p['label']) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="form-group"><label class="form-label x-small" for="deadline_hours2">Deadline (h)</label>
                            <input class="form-control" type="number" id="deadline_hours2" name="deadline_hours" min="1" max="720" placeholder="Keep current">
                        </div>
                    </div>
                    <button class="btn btn-outline btn-block" type="submit"><i class="fas fa-rotate mr-2"></i>Update priority</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($report['status'] === STATUS_VERIFIED): ?>
        <div class="card detail-panel"><div class="card-body">
            <form method="post" action="<?= e(url('actions/report-close.php')) ?>" data-confirm="Close this report?">
                <?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                <button class="btn btn-success btn-block" type="submit"><i class="fas fa-lock mr-2"></i>Close report</button>
            </form>
        </div></div>
        <?php endif; ?>
    </aside>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
