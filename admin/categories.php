<?php
/**
 * Administrator: manage issue categories, priorities and SLA targets.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_ADMIN);
$pdo = get_db();
$editId = (int)($_GET['edit'] ?? 0);
$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM reports r WHERE r.category_id = c.id) AS report_count FROM categories c ORDER BY c.name")->fetchAll();

$edit = null;
if ($editId > 0) {
    $s = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $s->execute([$editId]);
    $edit = $s->fetch() ?: null;
}
$form = [
    'id' => $edit['id'] ?? 0,
    'name' => $edit['name'] ?? '',
    'slug' => $edit['slug'] ?? '',
    'description' => $edit['description'] ?? '',
    'icon' => $edit['icon'] ?? 'wrench',
    'default_priority' => $edit['default_priority'] ?? 'medium',
    'default_sla_hours' => $edit['default_sla_hours'] ?? 48,
    'is_active' => $edit ? (int)$edit['is_active'] : 1,
];

$page_title = 'Manage categories';
$active_nav = 'categories';
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4"><h1 style="font-size:1.7rem">Issue categories</h1><p class="text-muted">Each category carries a default priority and response target.</p></div>

<div class="card mb-4">
    <div class="card-header d-flex justify-between align-center"><h2 class="card-title"><i class="fas fa-<?= $edit ? 'pen' : 'layer-group' ?> mr-2"></i><?= $edit ? 'Edit category' : 'Add category' ?></h2>
        <?php if ($edit): ?><a class="btn btn-outline btn-sm" href="<?= e(url('admin/categories.php')) ?>">Cancel edit</a><?php endif; ?></div>
    <div class="card-body">
        <form method="post" action="<?= e(url('actions/category-save.php')) ?>" data-validate novalidate>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Name <span class="required">*</span></label><input class="form-control" type="text" name="name" required data-min="2" data-max="100" data-label="Name" value="<?= e($form['name']) ?>"><div class="invalid-feedback"></div></div>
                <div class="form-group"><label class="form-label">Slug <span class="required">*</span></label><input class="form-control" type="text" name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" data-label="Slug" value="<?= e($form['slug']) ?>" placeholder="electrical"><div class="invalid-feedback"></div></div>
            </div>
            <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" maxlength="1000"><?= e($form['description']) ?></textarea></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Font Awesome icon</label><input class="form-control" type="text" name="icon" pattern="[a-z0-9-]{1,50}" value="<?= e($form['icon']) ?>" placeholder="wrench"><div class="invalid-feedback"></div><div class="form-hint">Icon name without the <code>fa-</code> prefix.</div></div>
                <div class="form-group"><label class="form-label">Default priority</label><select class="form-control" name="default_priority"><?php foreach (PRIORITIES as $key => $p): ?><option value="<?= e($key) ?>" <?= $form['default_priority'] === $key ? 'selected' : '' ?>><?= e($p['label']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Default SLA (hours)</label><input class="form-control" type="number" name="default_sla_hours" min="1" max="720" value="<?= (int)$form['default_sla_hours'] ?>"></div>
            </div>
            <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:1rem">
                <label style="display:flex;align-items:center;gap:.5rem"><input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>> Active / available to students</label>
                <button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk mr-2"></i><?= $edit ? 'Save changes' : 'Add category' ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table">
    <thead><tr><th>Category</th><th>Slug</th><th>Default priority</th><th>SLA</th><th>Reports</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
        <tr>
            <td><div class="d-flex align-center gap-2"><span class="stat-icon" style="width:34px;height:34px;font-size:.95rem"><i class="fas fa-<?= e($c['icon']) ?>"></i></span><strong><?= e($c['name']) ?></strong></div><?php if ($c['description']): ?><div class="x-small text-muted"><?= e(mb_substr($c['description'], 0, 70)) ?></div><?php endif; ?></td>
            <td><span class="badge badge-soft"><?= e($c['slug']) ?></span></td>
            <td><?= format_priority_badge($c['default_priority']) ?></td>
            <td class="small"><?= (int)$c['default_sla_hours'] ?>h</td>
            <td class="small"><?= (int)$c['report_count'] ?></td>
            <td><?= $c['is_active'] ? '<span class="badge badge-status-closed">Active</span>' : '<span class="badge badge-status-rejected">Inactive</span>' ?></td>
            <td><a class="btn btn-outline btn-sm" href="<?= e(url('admin/categories.php?edit=' . (int)$c['id'])) ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div></div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
