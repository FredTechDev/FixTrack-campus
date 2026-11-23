<?php
/**
 * Administrator: adjust a report's priority and response deadline.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(ROLE_ADMIN);
require_post();

$report_id     = (int)($_POST['report_id'] ?? 0);
$priority      = strtolower($_POST['priority'] ?? '');
$deadlineHours = (int)($_POST['deadline_hours'] ?? 0);
$admin_id      = (int)$_SESSION['user_id'];

$report = get_report($pdo, $report_id);
if (!$report) {
    set_flash('error', 'Report not found.');
    redirect('admin/reports.php');
}
if (!array_key_exists($priority, PRIORITIES)) {
    set_flash('error', 'Invalid priority level.');
    redirect('admin/report-view.php?id=' . $report_id);
}
if ($deadlineHours < 0 || $deadlineHours > 720) {
    set_flash('error', 'Deadline must be between 1 hour and 30 days.');
    redirect('admin/report-view.php?id=' . $report_id);
}

try {
    $pdo->beginTransaction();
    if ($deadlineHours > 0) {
        $pdo->prepare("UPDATE reports SET priority = ?, deadline = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id = ?")
            ->execute([$priority, $deadlineHours, $report_id]);
    } else {
        $pdo->prepare("UPDATE reports SET priority = ? WHERE id = ?")->execute([$priority, $report_id]);
    }
    $note = 'Priority updated to ' . ucfirst($priority) . ($deadlineHours > 0 ? ' with a ' . $deadlineHours . '-hour deadline' : '') . '.';
    record_status_change($pdo, $report_id, $admin_id, $report['status'], $report['status'], $note);
    create_notification($pdo, (int)$report['student_id'], $report_id, 'Priority updated: ' . $report['tracking_code'], $note, 'status_change');
    $pdo->commit();
    set_flash('success', $note);
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Could not update priority.');
}
redirect('admin/report-view.php?id=' . $report_id);
