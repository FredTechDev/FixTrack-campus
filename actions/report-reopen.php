<?php
/**
 * Student: reopen a closed or verified issue that is not actually fixed.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(ROLE_STUDENT);
require_post();

$report_id  = (int)($_POST['report_id'] ?? 0);
$reason     = trim($_POST['reason'] ?? '');
$student_id = (int)$_SESSION['user_id'];

$report = get_report($pdo, $report_id);
if (!$report || (int)$report['student_id'] !== $student_id) {
    set_flash('error', 'Report not found.');
    redirect('student/reports.php');
}
if (!in_array($report['status'], [STATUS_CLOSED, STATUS_VERIFIED], true)) {
    set_flash('error', 'Only closed or verified reports can be reopened.');
    redirect('student/report-view.php?id=' . $report_id);
}
if (mb_strlen($reason) < 10) {
    set_flash('error', 'Please explain why you are reopening this issue (at least 10 characters).');
    redirect('student/report-view.php?id=' . $report_id);
}

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE reports SET status = ?, resolved_at = NULL, closed_at = NULL, verified_at = NULL WHERE id = ?")
        ->execute([STATUS_IN_PROGRESS, $report_id]);
    $pdo->prepare("UPDATE assignments SET status = 'in_progress', completed_at = NULL WHERE report_id = ? AND status = 'completed'")
        ->execute([$report_id]);
    record_status_change($pdo, $report_id, $student_id, $report['status'], STATUS_IN_PROGRESS, 'Issue reopened by student: ' . $reason);

    $notify = [];
    $assignment = get_report_assignment($pdo, $report_id);
    if ($assignment) $notify[] = (int)$assignment['technician_id'];
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
    foreach ($admins as $admin) $notify[] = (int)$admin['id'];
    foreach (array_unique($notify) as $uid) {
        create_notification($pdo, $uid, $report_id, 'Issue reopened: ' . $report['tracking_code'], $reason, 'reopen');
    }
    $pdo->commit();
    set_flash('success', 'Issue reopened. The maintenance team has been notified.');
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Could not reopen this issue. Please try again.');
}
redirect('student/report-view.php?id=' . $report_id);
