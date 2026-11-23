<?php
/**
 * Technician: list and filter assigned jobs.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_TECHNICIAN);
$pdo = get_db();
$uid = (int)$_SESSION['user_id'];

$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$where = ['a.technician_id = ?', "a.status <> 'reassigned'"];
$params = [$uid];
if (in_array($status, ['pending','in_progress','completed'], true)) { $where[] = 'a.status = ?'; $params[] = $status; }
if (array_key_exists($priority, PRIORITIES)) { $where[] = 'r.priority = ?'; $params[] = $priority; }
if ($q !== '') { $where[] = '(r.title LIKE ? OR r.tracking_code LIKE ? OR f.name LIKE ?)'; $like = "%$q%"; array_push($params, $like, $like, $like); }
$whereSql = implode(' AND ', $where);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM assignments a JOIN reports r ON r.id = a.report_id JOIN facilities f ON f.id = r.facility_id WHERE $whereSql");
$cnt->execute($params);
$totalRows = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT r.*, c.name AS category_name, f.name AS facility_name, a.status AS assignment_status, a.assigned_at
    FROM assignments a
    JOIN reports r ON r.id = a.report_id
    JOIN categories c ON c.id = r.category_id
    JOIN facilities f ON f.id = r.facility_id
    WHERE $whereSql
    ORDER BY FIELD(r.priority,'critical','high','medium','low') ASC, r.deadline ASC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$page_title = 'My jobs';
$active_nav = 'jobs';
include __DIR__ . '/../includes/header.php';
function jq(array $extra = []): string {
    $v = [];
    foreach (['status','priority','q'] as $k) if (isset($_GET[$k]) && $_GET[$k] !== '') $v[$k] = $_GET[$k];
    return http_build_query(array_merge($v, $extra));
}
?>

<div class="mb-4"><h1 style="font-size:1.7rem">Assigned jobs</h1><p class="text-muted"><?= (int)$totalRows ?> job<?= $totalRows === 1 ? '' : 's' ?> found</p></div>

<div class="card mb-4"><div class="card-body">
    <form method="get" action="<?= e(url('technician/jobs.php')) ?>" class="filter-toolbar">
        <div class="filter-grid" style="flex:1">
            <div class="form-group mb-0"><label class="form-label x-small">Search</label><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Code, title or building"></div>
            <div class="form-group mb-0"><label class="form-label x-small">Job status</label><select class="form-control" name="status"><option value="">All</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In progress</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select></div>
            <div class="form-group mb-0"><label class="form-label x-small">Priority</label><select class="form-control" name="priority"><option value="">All</option>
                <?php foreach (PRIORITIES as $key => $p): ?><option value="<?= e($key) ?>" <?= $priority === $key ? 'selected' : '' ?>><?= e($p['label']) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="toolbar-actions"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-outline" href="<?= e(url('technician/jobs.php')) ?>">Reset</a></div>
    </form>
</div></div>

<div class="card"><div class="card-body p-0">
    <?php if (!$jobs): ?><div class="empty-state"><i class="fas fa-broom"></i><h3>No jobs found</h3><p>Try adjusting your filters.</p></div>
    <?php else: ?><div class="table-responsive"><table class="table">
        <thead><tr><th>Code</th><th>Issue</th><th>Category</th><th>Priority</th><th>Job status</th><th>Deadline</th><th></th></tr></thead><tbody>
        <?php foreach ($jobs as $j): ?>
            <tr>
                <td class="tracking-code"><?= e($j['tracking_code']) ?></td>
                <td><strong><?= e(mb_substr($j['title'], 0, 50)) ?></strong><div class="x-small text-muted"><?= e($j['facility_name']) ?> &middot; <?= e($j['room_number']) ?></div></td>
                <td class="small"><?= e($j['category_name']) ?></td>
                <td><?= format_priority_badge($j['priority']) ?></td>
                <td><span class="badge badge-soft"><?= e(humanize($j['assignment_status'])) ?></span></td>
                <td class="small nowrap"><?= $j['deadline'] ? e(format_date($j['deadline'], 'M j, g:i A')) : '&mdash;' ?><?php if (is_overdue($j['deadline'], $j['status'])): ?><div><span class="badge badge-overdue">Overdue</span></div><?php endif; ?></td>
                <td><a class="btn btn-outline btn-sm" href="<?= e(url('technician/job-view.php?id=' . (int)$j['id'])) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div></div>

<?php if ($pages > 1): ?><nav class="pagination" aria-label="Pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="current"><?= $i ?></span><?php else: ?><a href="<?= e(url('technician/jobs.php?' . jq(['page' => $i]))) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
</nav><?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
