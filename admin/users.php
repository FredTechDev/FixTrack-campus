<?php
/**
 * Administrator: manage student and technician accounts.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_ADMIN);
$pdo = get_db();
$editId = (int)($_GET['edit'] ?? 0);
$roleFilter = $_GET['role'] ?? '';
$q = trim($_GET['q'] ?? '');

$where = ["role IN ('student','technician')"];
$params = [];
if (in_array($roleFilter, [ROLE_STUDENT, ROLE_TECHNICIAN], true)) { $where[] = 'role = ?'; $params[] = $roleFilter; }
if ($q !== '') { $where[] = '(full_name LIKE ? OR email LIKE ? OR identifier LIKE ?)'; $like = "%$q%"; array_push($params, $like, $like, $like); }
$stmt = $pdo->prepare("SELECT * FROM users WHERE " . implode(' AND ', $where) . " ORDER BY role, full_name");
$stmt->execute($params);
$users = $stmt->fetchAll();

$edit = null;
if ($editId > 0) {
    $es = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('student','technician')");
    $es->execute([$editId]);
    $edit = $es->fetch() ?: null;
}

$form = [
    'id' => $edit['id'] ?? 0,
    'full_name' => $edit['full_name'] ?? '',
    'email' => $edit['email'] ?? '',
    'role' => $edit['role'] ?? ROLE_STUDENT,
    'phone' => $edit['phone'] ?? '',
    'identifier' => $edit['identifier'] ?? '',
    'specialization' => $edit['specialization'] ?? '',
    'is_active' => $edit ? (int)$edit['is_active'] : 1,
];

$page_title = 'Manage people';
$active_nav = 'users';
include __DIR__ . '/../includes/header.php';
?>

<div class="mb-4"><h1 style="font-size:1.7rem">Students &amp; technicians</h1><p class="text-muted">Create and maintain campus accounts.</p></div>

<div class="card mb-4">
    <div class="card-header d-flex justify-between align-center"><h2 class="card-title"><i class="fas fa-<?= $edit ? 'pen' : 'user-plus' ?> mr-2"></i><?= $edit ? 'Edit account' : 'Add new account' ?></h2>
        <?php if ($edit): ?><a href="<?= e(url('admin/users.php')) ?>" class="btn btn-outline btn-sm">Cancel edit</a><?php endif; ?>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('actions/user-save.php')) ?>" data-validate novalidate>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Full name <span class="required">*</span></label>
                    <input class="form-control" type="text" name="full_name" required data-min="2" data-max="100" data-label="Full name" value="<?= e($form['full_name']) ?>"><div class="invalid-feedback"></div></div>
                <div class="form-group"><label class="form-label">Email <span class="required">*</span></label>
                    <input class="form-control" type="email" name="email" required data-type="email" data-label="Email" value="<?= e($form['email']) ?>"><div class="invalid-feedback"></div></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Role <span class="required">*</span></label>
                    <select class="form-control" name="role" required data-label="Role">
                        <option value="student" <?= $form['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="technician" <?= $form['role'] === 'technician' ? 'selected' : '' ?>>Technician</option>
                    </select></div>
                <div class="form-group"><label class="form-label">Password <?= $edit ? '<span class="text-muted small">(leave blank to keep)</span>' : '<span class="required">*</span>' ?></label>
                    <input class="form-control" type="password" name="password" <?= $edit ? '' : 'required data-rule="password"' ?> data-label="Password" autocomplete="new-password"><div class="invalid-feedback"></div></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Phone</label><input class="form-control" type="text" name="phone" maxlength="30" value="<?= e($form['phone']) ?>"></div>
                <div class="form-group"><label class="form-label">Student / Staff ID</label><input class="form-control" type="text" name="identifier" maxlength="50" value="<?= e($form['identifier']) ?>"></div>
                <div class="form-group"><label class="form-label">Specialization <span class="text-muted small">(technicians)</span></label><input class="form-control" type="text" name="specialization" maxlength="100" value="<?= e($form['specialization']) ?>" placeholder="e.g. Electrical & Power Systems"></div>
            </div>
            <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:1rem">
                <label style="display:flex;align-items:center;gap:.5rem"><input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>> Active account</label>
                <button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk mr-2"></i><?= $edit ? 'Save changes' : 'Create account' ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4"><div class="card-body">
    <form method="get" action="<?= e(url('admin/users.php')) ?>" class="filter-toolbar">
        <div class="form-group mb-0" style="min-width:220px"><label class="form-label x-small">Search</label><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email or ID"></div>
        <div class="form-group mb-0"><label class="form-label x-small">Role</label><select class="form-control" name="role"><option value="">All</option><option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Students</option><option value="technician" <?= $roleFilter === 'technician' ? 'selected' : '' ?>>Technicians</option></select></div>
        <div class="toolbar-actions"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-outline" href="<?= e(url('admin/users.php')) ?>">Reset</a></div>
    </form>
</div></div>

<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table">
    <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>ID</th><th>Specialization</th><th>Status</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php if (!$users): ?><tr><td colspan="8" class="table-empty">No accounts found.</td></tr><?php endif; ?>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><div class="d-flex align-center gap-2"><span class="user-avatar"><?= e(strtoupper(mb_substr($u['full_name'], 0, 2))) ?></span><strong><?= e($u['full_name']) ?></strong></div></td>
            <td><span class="badge badge-soft"><?= e(ucfirst($u['role'])) ?></span></td>
            <td class="small text-break"><?= e($u['email']) ?></td>
            <td class="small"><?= e($u['identifier'] ?? '&mdash;') ?></td>
            <td class="small"><?= e($u['specialization'] ?? '&mdash;') ?></td>
            <td><?= $u['is_active'] ? '<span class="badge badge-status-closed">Active</span>' : '<span class="badge badge-status-rejected">Inactive</span>' ?></td>
            <td class="small text-muted nowrap"><?= e(format_date($u['created_at'], 'M j, Y')) ?></td>
            <td><a class="btn btn-outline btn-sm" href="<?= e(url('admin/users.php?edit=' . (int)$u['id'])) ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div></div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
