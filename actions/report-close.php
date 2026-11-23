<?php
/**
 * Administrator: close a verified report.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(ROLE_ADMIN);
require_post();

$report_id = (int)($_POST['report_id'] ?? 0);
$admin_id  = (int)$_SESSION['user_id'];
$notes     = trim($_POST['notes'] ?? '');
$report    = get_report($pdo, $report_id);

if (!$report) {
    set_flash('error', 'Report not found.');
    redirect('admin/reports.php');
}
if ($report['status'] !== STATUS_VERIFIED) {
    set_flash('error', 'Only verified reports can be closed.');
    redirect('admin/report-view.php?id=' . $report_id);
}

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE reports SET status = ?, closed_at = NOW() WHERE id = ?")
        ->execute([STATUS_CLOSED, $report_id]);
    record_status_change($pdo, $report_id, $admin_id, $report['status'], STATUS_CLOSED, $notes !== '' ? $notes : 'Student verification received. Ticket closed.');
    if (!empty($report['student_id'])) {
        create_notification(
            $pdo, (int)$report['student_id'], $report_id,
            'Report closed: ' . $report['tracking_code'],
            'Your verified repair has been closed and archived. Thank you for using FixTrack Campus.',
            'status_change'
        );
    }
    $pdo->commit();
    set_flash('success', 'Report closed successfully.');
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Could not close the report.');
}
redirect('admin/report-view.php?id=' . $report_id);
