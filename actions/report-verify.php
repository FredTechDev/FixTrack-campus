<?php
/**
 * Student: verify a completed repair or reopen it when unsatisfied.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(ROLE_STUDENT);
require_post();

$report_id  = (int)($_POST['report_id'] ?? 0);
$satisfied  = (int)($_POST['satisfied'] ?? 0);
$rating     = (int)($_POST['rating'] ?? 5);
$feedback   = trim($_POST['feedback'] ?? '');
$student_id = (int)$_SESSION['user_id'];

$report = get_report($pdo, $report_id);
if (!$report || (int)$report['student_id'] !== $student_id) {
    set_flash('error', 'Report not found.');
    redirect('student/reports.php');
}
if ($report['status'] !== STATUS_RESOLVED) {
    set_flash('error', 'This report is not awaiting verification.');
    redirect('student/report-view.php?id=' . $report_id);
}
if ($rating < 1 || $rating > 5) {
    set_flash('error', 'Please choose a rating between 1 and 5.');
    redirect('student/report-view.php?id=' . $report_id);
}
if ($satisfied === 0 && mb_strlen($feedback) < 10) {
    set_flash('error', 'Tell us what is still wrong (at least 10 characters) so the team can follow up.');
    redirect('student/report-view.php?id=' . $report_id);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO repair_verifications (report_id, student_id, is_satisfied, rating, feedback_notes)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE is_satisfied = VALUES(is_satisfied), rating = VALUES(rating),
                                feedback_notes = VALUES(feedback_notes), verified_at = NOW()
    ");
    $stmt->execute([$report_id, $student_id, $satisfied === 1 ? 1 : 0, $rating, $feedback !== '' ? $feedback : null]);

    if ($satisfied === 1) {
        $stmt = $pdo->prepare("UPDATE reports SET status = ?, verified_at = NOW() WHERE id = ?");
        $stmt->execute([STATUS_VERIFIED, $report_id]);
        record_status_change($pdo, $report_id, $student_id, $report['status'], STATUS_VERIFIED, 'Student verified the repair. Rating: ' . $rating . '/5.' . ($feedback ? ' ' . $feedback : ''));
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
        foreach ($admins as $admin) {
            create_notification($pdo, (int)$admin['id'], $report_id, 'Repair verified: ' . $report['tracking_code'], 'Student confirmed the fix. This report is ready to close.', 'verification');
        }
        $message = 'Thank you. The repair has been verified and the report is ready to close.';
    } else {
        $stmt = $pdo->prepare("UPDATE reports SET status = ?, resolved_at = NULL WHERE id = ?");
        $stmt->execute([STATUS_IN_PROGRESS, $report_id]);
        $pdo->prepare("UPDATE assignments SET status = 'in_progress', completed_at = NULL WHERE report_id = ? AND status = 'completed'")
            ->execute([$report_id]);
        record_status_change($pdo, $report_id, $student_id, $report['status'], STATUS_IN_PROGRESS, 'Repair rejected by student: ' . $feedback);

        $assignment = get_report_assignment($pdo, $report_id);
        $notify = [];
        if ($assignment) $notify[] = (int)$assignment['technician_id'];
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
        foreach ($admins as $admin) $notify[] = (int)$admin['id'];
        foreach (array_unique($notify) as $uid) {
            create_notification($pdo, $uid, $report_id, 'Repair reopened: ' . $report['tracking_code'], 'The student reported the issue is not fixed: ' . $feedback, 'reopen');
        }
        $message = 'Your feedback has been recorded and the issue has been reopened for further work.';
    }

    $pdo->commit();
    set_flash('success', $message);
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Could not record your verification. Please try again.');
}
redirect('student/report-view.php?id=' . $report_id);
