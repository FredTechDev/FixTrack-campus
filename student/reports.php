<?php
/**
 * Student: list, search and filter their submitted reports.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_STUDENT);
$pdo = get_db();
$uid = (int)$_SESSION['user_id'];

$status   = $_GET['status'] ?? '';
$category = (int)($_GET['category'] ?? 0);
$priority = $_GET['priority'] ?? '';
$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 10;

$where = ['r.student_id = ?'];
$params = [$uid];
if (array_key_exists($status, WORKFLOW_STEPS)) { $where[] = 'r.status = ?'; $params[] = $status; }
if ($category > 0) { $where[] = 'r.category_id = ?'; $params[] = $category; }
if (array_key_exists($priority, PRIORITIES)) { $where[] = 'r.priority = ?'; $params[] = $priority; }
if ($q !== '') {
    $where[] = '(r.title LIKE ? OR r.tracking_code LIKE ? OR r.description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM reports r WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sort = $_GET['sort'] ?? 'updated';
$orderBy = [
    'updated'  => 'r.updated_at DESC',
    'newest'   => 'r.created_at DESC',
    'oldest'   => 'r.created_at ASC',
    'priority' => "FIELD(r.priority,'critical','high','medium','low') ASC",
][$sort] ?? 'r.updated_at DESC';

$stmt = $pdo->prepare("
    SELECT r.*, c.name AS category_name, f.name AS facility_name,
           a.full_name AS technician_name
    FROM reports r
    JOIN categories c ON c.id = r.category_id
    JOIN facilities f ON f.id = r.facility_id
    LEFT JOIN assignments asg ON asg.report_id = r.id AND asg.status <> 'reassigned'
    LEFT JOIN users a ON a.id = asg.technician_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$reports = $stmt->fetchAll();
$categories = get_categories($pdo);

$page_title = 'My reports';
$active_nav = 'my-reports';
include __DIR__ . '/../includes/header.php';

function qs(array $extra = []): string {
    return http_build_query(array_merge([
        'status' => $_GET['status'] ?? '', 'category' => $_GET['category'] ?? '',
        'priority' => $_GET['priority'] ?? '', 'q' => $_GET['q'] ?? '',
        'sort' => $_GET['sort'] ?? '',
    ], $extra));
}
?>

<div class="d-flex justify-between align-center mb-4" style="flex-wrap:wrap;gap:1rem">
    <div><h1 style="font-size:1.7rem">My reports</h1><p class="text-muted"><?= (int)$totalRows ?> report<?= $totalRows === 1 ? '' : 's' ?> found</p></div>
    <a href="<?= e(url('student/report-new.php')) ?>" class="btn btn-primary"><i class="fas fa-circle-plus mr-2"></i>New report</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('student/reports.php')) ?>" class="filter-toolbar">
            <div class="filter-grid" style="flex:1">
                <div class="form-group mb-0">
                    <label class="form-label x-small">Search</label>
                    <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Code, title or keyword">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label x-small">Status</label>
                    <select class="form-control" name="status">
                        <option value="">All statuses</option>
                        <?php foreach (WORKFLOW_STEPS as $key => $step): if ($key === STATUS_REJECTED) continue; ?>
                            <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($step['label']) ?></option>
                        <?php endforeach; ?>
                        <option value="REJECTED" <?= $status === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label x-small">Category</label>
                    <select class="form-control" name="category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $category === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label x-small">Priority</label>
                    <select class="form-control" name="priority">
                        <option value="">All priorities</option>
                        <?php foreach (PRIORITIES as $key => $p): ?><option value="<?= e($key) ?>" <?= $priority === $key ? 'selected' : '' ?>><?= e($p['label']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="toolbar-actions">
                <div class="form-group mb-0"><label class="form-label x-small">Sort</label>
                    <select class="form-control" name="sort">
                        <option value="updated" <?= $sort === 'updated' ? 'selected' : '' ?>>Recently updated</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                        <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit"><i class="fas fa-magnifying-glass mr-1"></i>Filter</button>
                <a class="btn btn-outline" href="<?= e(url('student/reports.php')) ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (!$reports): ?>
            <div class="empty-state"><i class="fas fa-magnifying-glass"></i><h3>No reports match your filters</h3><p>Try changing your search or submit a new report.</p></div>
        <?php else: ?>
        <div class="table-responsive"><table class="table" id="reportsTable">
            <thead><tr><th>Code</th><th>Issue</th><th>Category</th><th>Priority</th><th>Status</th><th>Assigned to</th><th>Updated</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($reports as $r): ?>
                <tr>
                    <td class="tracking-code"><?= e($r['tracking_code']) ?></td>
                    <td><strong><?= e(mb_substr($r['title'], 0, 60)) ?></strong><div class="x-small text-muted"><?= e($r['facility_name']) ?> &middot; <?= e($r['room_number']) ?></div></td>
                    <td class="small"><?= e($r['category_name']) ?></td>
                    <td><?= format_priority_badge($r['priority']) ?></td>
                    <td><?= format_status_badge($r['status']) ?><?php if (is_overdue($r['deadline'], $r['status'])): ?><div class="mt-1"><span class="badge badge-overdue">Overdue</span></div><?php endif; ?></td>
                    <td class="small"><?= $r['technician_name'] ? e($r['technician_name']) : '<span class="text-muted">&mdash;</span>' ?></td>
                    <td class="small text-muted nowrap"><?= e(time_ago($r['updated_at'])) ?></td>
                    <td><a href="<?= e(url('student/report-view.php?id=' . (int)$r['id'])) ?>" class="btn btn-outline btn-sm">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav class="pagination" aria-label="Pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="current" aria-current="page"><?= $i ?></span>
        <?php else: ?><a href="<?= e(url('student/reports.php?' . qs(['page' => $i]))) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
