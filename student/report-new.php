<?php
/**
 * Student: submit a new maintenance report.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_STUDENT);
$pdo = get_db();
$categories = get_categories($pdo);
$facilities = get_facilities($pdo);

$old = ['category_id' => 0, 'facility_id' => 0, 'room_number' => '', 'title' => '', 'description' => '', 'priority' => 'medium'];
if (isset($_GET['facility'])) {
    $old['facility_id'] = (int)$_GET['facility'];
}
if (isset($_GET['category'])) {
    $old['category_id'] = (int)$_GET['category'];
}

$page_title = 'Report an issue';
$active_nav = 'new-report';
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 style="font-size:1.7rem">Report a maintenance issue</h1>
    <p class="text-muted">Tell us what's wrong and where. You'll receive a tracking code immediately.</p>
</div>

<form method="post" action="<?= e(url('actions/report-create.php')) ?>" enctype="multipart/form-data" data-validate novalidate>
    <?= csrf_field() ?>
    <div class="detail-grid">
        <div>
            <div class="card detail-panel">
                <div class="card-header"><h2 class="card-title"><i class="fas fa-circle-info mr-2"></i>Issue details</h2></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label" for="issue_title">Issue title <span class="required">*</span></label>
                        <input class="form-control" type="text" id="issue_title" name="title" required
                               data-min="5" data-max="200" data-label="Issue title"
                               value="<?= e($old['title']) ?>" placeholder="e.g. Leaking pipe under the 2nd floor sink">
                        <div class="invalid-feedback"></div>
                        <div class="form-hint">A short, specific summary helps us route your report quickly.</div>
                        <div id="duplicate-warning" class="alert alert-warning mt-2 d-none" role="status"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="category_id">Issue category <span class="required">*</span></label>
                            <select class="form-control" id="category_id" name="category_id" required data-label="Category">
                                <option value="">Choose a category</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"
                                            data-sla="<?= (int)$c['default_sla_hours'] ?>"
                                            data-priority="<?= e($c['default_priority']) ?>"
                                            <?= $old['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"></div>
                            <div class="form-hint" id="sla-hint">Target response time depends on the category.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="priority">Priority level <span class="required">*</span></label>
                            <select class="form-control" id="priority" name="priority" required data-label="Priority">
                                <?php foreach (PRIORITIES as $key => $p): ?>
                                    <option value="<?= e($key) ?>" <?= $old['priority'] === $key ? 'selected' : '' ?>><?= e($p['label']) ?> &mdash; <?= (int)$p['default_hours'] ?>h target</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Detailed description <span class="required">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="6" required
                                  data-min="10" data-max="5000" data-label="Description"
                                  placeholder="Describe what happened, when you noticed it, and any safety concerns..."><?= e($old['description']) ?></textarea>
                        <div class="invalid-feedback"></div>
                        <div class="form-hint">Include anything that will help the technician prepare (symptoms, safety risks, access notes).</div>
                    </div>
                </div>
            </div>

            <div class="card detail-panel">
                <div class="card-header"><h2 class="card-title"><i class="fas fa-camera mr-2"></i>Photographic evidence</h2></div>
                <div class="card-body">
                    <label class="form-label">Location <span class="required">*</span></label>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label x-small" for="facility_id">Building / facility</label>
                            <select class="form-control" id="facility_id" name="facility_id" required data-label="Building">
                                <option value="">Choose a building</option>
                                <?php foreach ($facilities as $f): ?>
                                    <option value="<?= (int)$f['id'] ?>" <?= $old['facility_id'] === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?> (<?= e($f['building_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label x-small" for="room_number">Room / specific location</label>
                            <input class="form-control" type="text" id="room_number" name="room_number" required
                                   data-min="1" data-max="100" data-label="Room / location"
                                   value="<?= e($old['room_number']) ?>" placeholder="e.g. Lab E-204, 3rd floor west wing">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <label class="form-label mt-3">Attach photos <span class="text-muted small">(optional, up to 5)</span></label>
                    <div class="dropzone-box">
                        <i class="fas fa-cloud-arrow-up dropzone-icon"></i>
                        <div class="dropzone-text">Drag &amp; drop images here, or <strong>browse</strong></div>
                        <div class="form-hint">JPG, PNG or WebP &middot; maximum 5 MB each</div>
                        <input type="file" name="evidence[]" id="evidence" accept="image/jpeg,image/png,image/webp" multiple>
                    </div>
                    <div class="preview-container mt-3"></div>
                    <div class="invalid-feedback d-none mt-2" data-upload-error></div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-lg" type="submit"><i class="fas fa-paper-plane mr-2"></i>Submit report</button>
                <a href="<?= e(url('student/dashboard.php')) ?>" class="btn btn-outline btn-lg">Cancel</a>
            </div>
        </div>

        <aside>
            <div class="card detail-panel"><div class="card-body">
                <h3 class="mb-3" style="font-size:1rem"><i class="fas fa-lightbulb mr-2"></i>Before you submit</h3>
                <ul style="padding-left:1.1rem;color:var(--text-secondary);font-size:.88rem;line-height:1.8">
                    <li>Check whether a similar issue has already been reported (we'll warn you as you type).</li>
                    <li>Give the exact room, floor or nearest landmark.</li>
                    <li>For electrical or water hazards, mention any immediate safety risk.</li>
                    <li>Photos help technicians bring the right tools on the first visit.</li>
                </ul>
            </div></div>
            <div class="card detail-panel"><div class="card-body">
                <div class="info-note"><i class="fas fa-shield-halved mr-2"></i>Your report is recorded with a unique tracking code and a full status history.</div>
            </div></div>
        </aside>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
