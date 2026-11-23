<?php
/**
 * CSV report export. Scoped by role: students get only their own reports,
 * admins get everything matching the supplied filters.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$pdo = get_db();

$where = ['1=1'];
$params = [];
if ($_SESSION['user_role'] === ROLE_STUDENT) {
    $where[] = 'r.student_id = ?';
    $params[] = (int)$_SESSION['user_id'];
} elseif ($_SESSION['user_role'] !== ROLE_ADMIN) {
    set_flash('error', 'Your role cannot export reports.');
    redirect('dashboard.php');
}

if (array_key_exists('status', $_GET) && array_key_exists($_GET['status'], WORKFLOW_STEPS)) { $where[] = 'r.status = ?'; $params[] = $_GET['status']; }
if (array_key_exists('priority', $_GET) && array_key_exists($_GET['priority'], PRIORITIES)) { $where[] = 'r.priority = ?'; $params[] = $_GET['priority']; }
if (!empty($_GET['category']) && (int)$_GET['category'] > 0) { $where[] = 'r.category_id = ?'; $params[] = (int)$_GET['category']; }
if (!empty($_GET['facility']) && (int)$_GET['facility'] > 0) { $where[] = 'r.facility_id = ?'; $params[] = (int)$_GET['facility']; }
if (!empty($_GET['q'])) {
    $where[] = '(r.title LIKE ? OR r.tracking_code LIKE ? OR r.description LIKE ?)';
    $like = '%' . trim($_GET['q']) . '%';
    array_push($params, $like, $like, $like);
}

$stmt = $pdo->prepare("
    SELECT r.tracking_code, r.title, r.description, r.priority, r.status, r.room_number,
           r.deadline, r.created_at, r.updated_at, r.resolved_at, r.closed_at,
           c.name AS category, f.name AS facility, f.building_code,
           u.full_name AS reporter,
           (SELECT t.full_name FROM assignments a JOIN users t ON t.id = a.technician_id
            WHERE a.report_id = r.id AND a.status <> 'reassigned'
            ORDER BY a.assigned_at DESC LIMIT 1) AS technician
    FROM reports r
    JOIN categories c ON c.id = r.category_id
    JOIN facilities f ON f.id = r.facility_id
    JOIN users u ON u.id = r.student_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

function csv_cell($value): string {
    $value = (string)($value ?? '');
    // Neutralise spreadsheet formula injection. fputcsv handles quoting/escaping.
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) $value = "'" . $value;
    return $value;
}

$filename = 'fixtrack_reports_' . date('Y-md_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
fputcsv($out, [
    'Tracking Code', 'Title', 'Description', 'Category', 'Facility', 'Building Code',
    'Room / Location', 'Priority', 'Status', 'Reporter', 'Technician',
    'Created', 'Deadline', 'Resolved', 'Closed', 'Last Updated'
]);
foreach ($rows as $r) {
    fputcsv($out, array_map('csv_cell', [
        $r['tracking_code'], $r['title'], $r['description'], $r['category'],
        $r['facility'], $r['building_code'], $r['room_number'], $r['priority'],
        $r['status'], $r['reporter'], $r['technician'], $r['created_at'],
        $r['deadline'], $r['resolved_at'], $r['closed_at'], $r['updated_at'],
    ]));
}
fclose($out);
exit;
