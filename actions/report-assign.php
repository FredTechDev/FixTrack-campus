<?php
/**
 * Administrator: assign (or reassign) a technician to an approved report.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(ROLE_ADMIN);
require_post();

$report_id     = (int)($_POST['report_id'] ?? 0);
$technician_id = (int)($_POST['technician_id'] ?? 0);
$notes         = trim($_POST['notes'] ?? '');
$deadlineHours = (int)($_POST['deadline_hours'] ?? 0);
$admin_id      = (int)$_SESSION['user_id'];

$report = get_report($pdo, $report_id);
if (!$report) {
    set_flash('error', 'Report not found.');
    redirect('admin/reports.php');
}
if (!in_array($report['status'], [STATUS_APPROVED, STATUS_ASSIGNED], true)) {
    set_flash('error', 'Only approved or already-assigned reports can be assigned.');
    redirect('admin/report-view.php?id=' . $report_id);
}

$techCheck = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ? AND role = 'technician' AND is_active = 1");
$techCheck->execute([$technician_id]);
$tech = $techCheck->fetch();
if (!$tech) {
    set_flash('error', 'Select a valid, active technician.');
    redirect('admin/report-view.php?id=' . $report_id);
}
if ($deadlineHours < 0 || $deadlineHours > 720) {
    set_flash('error', 'Deadline must be between 1 hour and 30 days.');
    redirect('admin/report-view.php?id=' . $report_id);
}
if ($deadlineHours === 0) {
    $deadlineHours = PRIORITIES[$report['priority']]['default_hours'] ?? 48;
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE assignments SET status = 'reassigned' WHERE report_id = ? AND status <> 'completed'")
        ->execute([$report_id]);

    $stmt = $pdo->prepare("
        INSERT INTO assignments (report_id, technician_id, assigned_by, assignment_notes, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$report_id, $technician_id, $admin_id, $notes !== '' ? $notes : null]);

    $stmt = $pdo->prepare("
        UPDATE reports
        SET status = ?, deadline = DATE_ADD(NOW(), INTERVAL ? HOUR), rejection_reason = NULL
        WHERE id = ?
    ");
    $stmt->execute([STATUS_ASSIGNED, $deadlineHours, $report_id]);

    $historyNote = 'Assigned to ' . $tech['full_name'] . ' with a ' . $deadlineHours . '-hour response deadline.'
        . ($notes !== '' ? ' Notes: ' . $notes : '');
    record_status_change($pdo, $report_id, $admin_id, $report['status'], STATUS_ASSIGNED, $historyNote);

    create_notification(
        $pdo, $technician_id, $report_id,
        'New job assigned: ' . $report['tracking_code'],
        'You have been assigned "' . $report['title'] . '" at ' . $report['facility_name'] . ' (' . $report['room_number'] . ').',
        'assignment'
    );
    create_notification(
        $pdo, (int)$report['student_id'], $report_id,
        'Technician assigned: ' . $report['tracking_code'],
        'Your issue has been assigned to ' . $tech['full_name'] . '. Target completion: ' . $deadlineHours . ' hours.',
        'assignment'
    );

    $pdo->commit();
    set_flash('success', 'Job assigned to ' . $tech['full_name'] . '.');
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Assignment failed. Please try again.');
}
redirect('admin/report-view.php?id=' . $report_id);
