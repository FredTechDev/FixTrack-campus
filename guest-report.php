<?php
/** Public guest report form - no account required. */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();
$categories = get_categories($pdo);
$facilities = get_facilities($pdo);
$selFacility = (int)($_GET['facility'] ?? 0);
$selCategory = (int)($_GET['category'] ?? 0);

$page_title = 'Report an issue (guest)';
$active_nav = 'guest';
include __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
    <h1 style="font-size:1.6rem">Report an issue without an account</h1>
    <p class="text-muted">Tell us what's broken and where. You'll get a private link to track the repair — keep it safe.</p>
</div>

<form method="post" action="<?= e(url('actions/guest-report-create.php')) ?>" enctype="multipart/form-data" data-validate novalidate>
    <?= csrf_field() ?>
    <div class="detail-grid">
        <div>
            <div class="card detail-panel">
                <div class="card-header"><h2 class="card-title"><i class="fas fa-user mr-2"></i>Your details</h2></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label" for="guest_name">Your name <span class="required">*</span></label>
                            <input class="form-control" type="text" id="guest_name" name="guest_name" required data-min="2" data-max="100" data-label="Name" placeholder="e.g. Wanjiku Kamau"><div class="invalid-feedback"></div></div>
                        <div class="form-group"><label class="form-label" for="guest_email">Email <span class="text-muted small">(optional)</span></label>
                            <input class="form-control" type="email" id="guest_email" name="guest_email" data-type="email" data-label="Email" placeholder="wanjiku@example.co.ke"><div class="invalid-feedback"></div>
                            <div class="form-hint">Only used by the facilities team if they need to contact you.</div></div>
                    </div>
                </div>
            </div>

            <div class="card detail-panel">
                <div class="card-header"><h2 class="card-title"><i class="fas fa-circle-info mr-2"></i>Issue details</h2></div>
                <div class="card-body">
                    <div class="form-group"><label class="form-label" for="issue_title">Issue title <span class="required">*</span></label>
                        <input class="form-control" type="text" id="issue_title" name="title" required data-min="5" data-max="200" data-label="Issue title" placeholder="e.g. Broken chair on the ground floor"><div class="invalid-feedback"></div></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label" for="category_id">Category <span class="required">*</span></label>
                            <select class="form-control" id="category_id" name="category_id" required data-label="Category"><option value="">Choose a category</option>
                                <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" data-sla="<?= (int)$c['default_sla_hours'] ?>" data-priority="<?= e($c['default_priority']) ?>" <?= $selCategory === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                            </select><div class="invalid-feedback"></div><div class="form-hint" id="sla-hint">Target response depends on the category.</div></div>
                        <div class="form-group"><label class="form-label" for="priority">Priority <span class="required">*</span></label>
                            <select class="form-control" id="priority" name="priority" required data-label="Priority">
                                <?php foreach (PRIORITIES as $key => $p): ?><option value="<?= e($key) ?>" <?= $key === 'medium' ? 'selected' : '' ?>><?= e($p['label']) ?></option><?php endforeach; ?>
                            </select><div class="invalid-feedback"></div></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label" for="facility_id">Building <span class="required">*</span></label>
                            <select class="form-control" id="facility_id" name="facility_id" required data-label="Building"><option value="">Choose a building</option>
                                <?php foreach ($facilities as $f): ?><option value="<?= (int)$f['id'] ?>" <?= $selFacility === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
                            </select><div class="invalid-feedback"></div></div>
                        <div class="form-group"><label class="form-label" for="room_number">Room / location <span class="required">*</span></label>
                            <input class="form-control" type="text" id="room_number" name="room_number" required data-min="1" data-max="100" data-label="Location" placeholder="e.g. 2nd floor, near the stairs"><div class="invalid-feedback"></div></div>
                    </div>
                    <div class="form-group"><label class="form-label" for="description">Description <span class="required">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" required data-min="10" data-max="5000" data-label="Description" placeholder="What is damaged? Any safety concerns?"></textarea><div class="invalid-feedback"></div></div>
                    <label class="form-label x-small">Photo <span class="text-muted small">(optional, up to 5)</span></label>
                    <div class="dropzone-box"><i class="fas fa-camera dropzone-icon"></i><div class="dropzone-text">Drop images here or <strong>browse</strong></div>
                        <input type="file" name="evidence[]" accept="image/jpeg,image/png,image/webp" multiple></div>
                    <div class="preview-container mt-3"></div>
                    <div class="invalid-feedback d-none mt-2" data-upload-error></div>
                </div>
            </div>

            <button class="btn btn-primary btn-lg" type="submit"><i class="fas fa-paper-plane mr-2"></i>Submit report</button>
            <a class="btn btn-outline btn-lg" href="<?= e(url('index.php')) ?>">Cancel</a>
        </div>
        <aside><div class="card"><div class="card-body"><div class="info-note">
            <i class="fas fa-key mr-2"></i>After submitting you'll receive a <strong>tracking code</strong> and a <strong>private link</strong>. Save that link — it's the only way to check progress or confirm the repair without an account. Register later with the same email and your guest reports will be linked to your account.
        </div></div></div></aside>
    </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
