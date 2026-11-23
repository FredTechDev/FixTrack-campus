<?php
/**
 * Administrator: manage campus buildings / facilities.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_ADMIN);
$pdo = get_db();
$editId = (int)($_GET['edit'] ?? 0);
$facilities = $pdo->query("SELECT f.*, (SELECT COUNT(*) FROM reports r WHERE r.facility_id = f.id) AS report_count FROM facilities f ORDER BY f.name")->fetchAll();

$edit = null;
if ($editId > 0) {
    $s = $pdo->prepare("SELECT * FROM facilities WHERE id = ?");
    $s->execute([$editId]);
    $edit = $s->fetch() ?: null;
}
$form = [
    'id' => $edit['id'] ?? 0,
    'name' => $edit['name'] ?? '',
    'building_code' => $edit['building_code'] ?? '',
    'campus_zone' => $edit['campus_zone'] ?? '',
    'description' => $edit['description'] ?? '',
    'total_floors' => $edit['total_floors'] ?? 1,
    'is_active' => $edit ? (int)$edit['is_active'] : 1,
];

$page_title = 'Manage facilities';
$active_nav = 'facilities';
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4"><h1 style="font-size:1.7rem">Campus facilities</h1><p class="text-muted">Buildings available when filing a maintenance report.</p></div>

<div class="card mb-4">
    <div class="card-header d-flex justify-between align-center"><h2 class="card-title"><i class="fas fa-<?= $edit ? 'pen' : 'building-circle-plus' ?> mr-2"></i><?= $edit ? 'Edit facility' : 'Add facility' ?></h2>
        <?php if ($edit): ?><a class="btn btn-outline btn-sm" href="<?= e(url('admin/facilities.php')) ?>">Cancel edit</a><?php endif; ?></div>
    <div class="card-body">
        <form method="post" action="<?= e(url('actions/facility-save.php')) ?>" data-validate novalidate>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Facility name <span class="required">*</span></label><input class="form-control" type="text" name="name" required data-min="2" data-max="150" data-label="Facility name" value="<?= e($form['name']) ?>"><div class="invalid-feedback"></div></div>
                <div class="form-group"><label class="form-label">Building code <span class="required">*</span></label><input class="form-control" type="text" name="building_code" required pattern="[A-Za-z0-9\-]{2,30}" data-label="Building code" value="<?= e($form['building_code']) ?>" placeholder="ENG-B1"><div class="invalid-feedback"></div></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Campus zone <span class="required">*</span></label><input class="form-control" type="text" name="campus_zone" required data-min="2" data-max="100" data-label="Campus zone" value="<?= e($form['campus_zone']) ?>"><div class="invalid-feedback"></div></div>
                <div class="form-group"><label class="form-label">Floors</label><input class="form-control" type="number" name="total_floors" min="1" max="200" value="<?= (int)$form['total_floors'] ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" maxlength="1000"><?= e($form['description']) ?></textarea></div>
            <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:1rem">
                <label style="display:flex;align-items:center;gap:.5rem"><input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>> Active / visible in report form</label>
                <button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk mr-2"></i><?= $edit ? 'Save changes' : 'Add facility' ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table">
    <thead><tr><th>Facility</th><th>Code</th><th>Zone</th><th>Floors</th><th>Reports</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($facilities as $f): ?>
        <tr>
            <td><strong><?= e($f['name']) ?></strong><?php if ($f['description']): ?><div class="x-small text-muted"><?= e(mb_substr($f['description'], 0, 70)) ?></div><?php endif; ?></td>
            <td><span class="badge badge-soft"><?= e($f['building_code']) ?></span></td>
            <td class="small"><?= e($f['campus_zone']) ?></td>
            <td class="small"><?= (int)$f['total_floors'] ?></td>
            <td class="small"><?= (int)$f['report_count'] ?></td>
            <td><?= $f['is_active'] ? '<span class="badge badge-status-closed">Active</span>' : '<span class="badge badge-status-rejected">Inactive</span>' ?></td>
            <td class="nowrap">
                <a class="btn btn-outline btn-sm" href="<?= e(url('admin/facilities.php?edit=' . (int)$f['id'])) ?>">Edit</a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('qr/facility.php?id=' . (int)$f['id'])) ?>" title="QR code"><i class="fas fa-qrcode"></i></a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div></div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
