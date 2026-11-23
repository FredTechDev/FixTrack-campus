<?php
/**
 * Administrator: all reports with powerful filtering and CSV export link.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_ADMIN);
$pdo = get_db();

$status    = $_GET['status'] ?? '';
$category  = (int)($_GET['category'] ?? 0);
$facility  = (int)($_GET['facility'] ?? 0);
$priority  = $_GET['priority'] ?? '';
$tech      = (int)($_GET['technician'] ?? 0);
$overdue   = isset($_GET['overdue']) ? 1 : 0;
$q         = trim($_GET['q'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 15;

$where = ['1=1'];
$params = [];
if (array_key_exists($status, WORKFLOW_STEPS)) { $where[] = 'r.status = ?'; $params[] = $status; }
if ($category > 0) { $where[] = 'r.category_id = ?'; $params[] = $category; }
if ($facility > 0) { $where[] = 'r.facility_id = ?'; $params[] = $facility; }
if (array_key_exists($priority, PRIORITIES)) { $where[] = 'r.priority = ?'; $params[] = $priority; }
if ($tech > 0) { $where[] = 'EXISTS (SELECT 1 FROM assignments ax WHERE ax.report_id = r.id AND ax.technician_id = ? AND ax.status <> \'reassigned\')'; $params[] = $tech; }
if ($overdue) { $where[] = "r.deadline < NOW() AND r.status NOT IN ('RESOLVED','VERIFIED','CLOSED','REJECTED')"; }
if ($q !== '') {
    $where[] = '(r.title LIKE ? OR r.tracking_code LIKE ? OR r.description LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM reports r
    JOIN users u ON u.id = r.student_id WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT r.*, c.name AS category_name, f.name AS facility_name, u.full_name AS student_name,
           (SELECT t.full_name FROM assignments a JOIN users t ON t.id = a.technician_id
            WHERE a.report_id = r.id AND a.status <> 'reassigned'
            ORDER BY a.assigned_at DESC LIMIT 1) AS technician_name
    FROM reports r
    JOIN categories c ON c.id = r.category_id
    JOIN facilities f ON f.id = r.facility_id
    JOIN users u ON u.id = r.student_id
    WHERE $whereSql
    ORDER BY FIELD(r.priority,'critical','high','medium','low') ASC, r.updated_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$reports = $stmt->fetchAll();

$categories = get_categories($pdo);
$facilities = get_facilities($pdo);
$technicians = $pdo->query("SELECT id, full_name FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY full_name")->fetchAll();

$page_title = 'All reports';
$active_nav = 'reports';
include __DIR__ . '/../includes/header.php';

function aq(array $extra = []): string {
    $keys = ['status','category','facility','priority','technician','overdue','q'];
    $vals = [];
    foreach ($keys as $k) if (isset($_GET[$k]) && $_GET[$k] !== '') $vals[$k] = $_GET[$k];
    return http_build_query(array_merge($vals, $extra));
}
$exportQuery = aq();
?>

<div class="d-flex justify-between align-center mb-4" style="flex-wrap:wrap;gap:1rem">
    <div><h1 style="font-size:1.7rem">All reports</h1><p class="text-muted"><?= (int)$totalRows ?> matching report<?= $totalRows === 1 ? '' : 's' ?></p></div>
    <a class="btn btn-outline" href="<?= e(url('export.php?' . $exportQuery)) ?>"><i class="fas fa-file-csv mr-2"></i>Export CSV</a>
</div>

<div class="card mb-4"><div class="card-body">
    <form method="get" action="<?= e(url('admin/reports.php')) ?>" class="filter-toolbar">
        <div class="filter-grid" style="flex:1">
            <div class="form-group mb-0"><label class="form-label x-small">Search</label><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Code, title, description, reporter"></div>
            <div class="form-group mb-0"><label class="form-label x-small">Status</label><select class="form-control" name="status"><option value="">All</option>
                <?php foreach (WORKFLOW_STEPS as $key => $s): if ($key === STATUS_REJECTED) continue; ?><option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($s['label']) ?></option><?php endforeach; ?>
                <option value="REJECTED" <?= $status === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
            </select></div>
            <div class="form-group mb-0"><label class="form-label x-small">Category</label><select class="form-control" name="category"><option value="">All</option>
                <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $category === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group mb-0"><label class="form-label x-small">Facility</label><select class="form-control" name="facility"><option value="">All</option>
                <?php foreach ($facilities as $f): ?><option value="<?= (int)$f['id'] ?>" <?= $facility === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group mb-0"><label class="form-label x-small">Priority</label><select class="form-control" name="priority"><option value="">All</option>
                <?php foreach (PRIORITIES as $key => $p): ?><option value="<?= e($key) ?>" <?= $priority === $key ? 'selected' : '' ?>><?= e($p['label']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group mb-0"><label class="form-label x-small">Technician</label><select class="form-control" name="technician"><option value="">All</option>
                <?php foreach ($technicians as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $tech === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="toolbar-actions">
            <label class="small" style="display:flex;align-items:center;gap:.4rem"><input type="checkbox" name="overdue" value="1" <?= $overdue ? 'checked' : '' ?>> Overdue only</label>
            <button class="btn btn-primary" type="submit"><i class="fas fa-magnifying-glass mr-1"></i>Filter</button>
            <a class="btn btn-outline" href="<?= e(url('admin/reports.php')) ?>">Reset</a>
        </div>
    </form>
</div></div>

<div class="card"><div class="card-body p-0">
    <?php if (!$reports): ?><div class="empty-state"><i class="fas fa-magnifying-glass"></i><h3>No reports found</h3><p>Try a different filter combination.</p></div>
    <?php else: ?>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Code</th><th>Issue</th><th>Reporter</th><th>Category</th><th>Priority</th><th>Status</th><th>Technician</th><th>Updated</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($reports as $r): ?>
            <tr>
                <td class="tracking-code"><?= e($r['tracking_code']) ?></td>
                <td><strong><?= e(mb_substr($r['title'], 0, 48)) ?></strong><div class="x-small text-muted"><?= e($r['facility_name']) ?> &middot; <?= e($r['room_number']) ?></div></td>
                <td class="small"><?= e($r['student_name']) ?></td>
                <td class="small"><?= e($r['category_name']) ?></td>
                <td><?= format_priority_badge($r['priority']) ?></td>
                <td><?= format_status_badge($r['status']) ?><?php if (is_overdue($r['deadline'], $r['status'])): ?><div class="mt-1"><span class="badge badge-overdue">Overdue</span></div><?php endif; ?></td>
                <td class="small"><?= $r['technician_name'] ? e($r['technician_name']) : '<span class="badge badge-soft">Unassigned</span>' ?></td>
                <td class="small text-muted nowrap"><?= e(time_ago($r['updated_at'])) ?></td>
                <td><a class="btn btn-outline btn-sm" href="<?= e(url('admin/report-view.php?id=' . (int)$r['id'])) ?>">Manage</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div></div>

<?php if ($pages > 1): ?>
<nav class="pagination" aria-label="Pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="current" aria-current="page"><?= $i ?></span>
        <?php else: ?><a href="<?= e(url('admin/reports.php?' . aq(['page' => $i]))) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
