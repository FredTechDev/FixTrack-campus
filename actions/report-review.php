<?php
/**
 * Administrator: move a report through review, approval or rejection.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(ROLE_ADMIN);
require_post();

$report_id = (int)($_POST['report_id'] ?? 0);
$decision  = $_POST['decision'] ?? '';
$notes     = trim($_POST['notes'] ?? '');
$admin_id  = (int)$_SESSION['user_id'];

$report = get_report($pdo, $report_id);
if (!$report) {
    set_flash('error', 'Report not found.');
    redirect('admin/reports.php');
}

$target = [
    'review'  => STATUS_UNDER_REVIEW,
    'approve' => STATUS_APPROVED,
    'reject'  => STATUS_REJECTED,
][$decision] ?? '';

if ($target === '') {
    set_flash('error', 'Invalid review decision.');
    redirect('admin/report-view.php?id=' . $report_id);
}
if (!is_valid_status_transition($report['status'], $target)) {
    set_flash('error', 'That status change is not allowed from ' . $report['status'] . '.');
    redirect('admin/report-view.php?id=' . $report_id);
}
if ($decision === 'reject' && mb_strlen($notes) < 5) {
    set_flash('error', 'A rejection explanation of at least 5 characters is required.');
    redirect('admin/report-view.php?id=' . $report_id);
}

try {
    $pdo->beginTransaction();

    $extra = '';
    $params = [$target, $report_id];
    if ($decision === 'approve') {
        $hours = PRIORITIES[$report['priority']]['default_hours'] ?? 48;
        $extra = ', deadline = DATE_ADD(NOW(), INTERVAL ' . (int)$hours . ' HOUR), rejection_reason = NULL';
    }
    if ($decision === 'reject') {
        $extra = ', rejection_reason = ?, deadline = NULL';
        $params = [$target, $notes, $report_id];
    }
    $sql = "UPDATE reports SET status = ?" . $extra . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $historyNote = $notes !== '' ? $notes : (
        $decision === 'approve' ? 'Report approved and queued for assignment.' : 'Report moved under review.'
    );
    record_status_change($pdo, $report_id, $admin_id, $report['status'], $target, $historyNote);

    if (!empty($report['student_id'])) {
        if ($decision === 'reject') {
            create_notification(
                $pdo, (int)$report['student_id'], $report_id,
                'Report rejected: ' . $report['tracking_code'],
                'Your report was reviewed and rejected. Reason: ' . $notes,
                'rejection'
            );
        } else {
            create_notification(
                $pdo, (int)$report['student_id'], $report_id,
                'Report update: ' . $report['tracking_code'],
                'Your report status changed to ' . $target . '.',
                'status_change'
            );
        }
    }

    $pdo->commit();
    set_flash('success', 'Report ' . $report['tracking_code'] . ' is now ' . $target . '.');
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Could not update the report. Please try again.');
}
redirect('admin/report-view.php?id=' . $report_id);
