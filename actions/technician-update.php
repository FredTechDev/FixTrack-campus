<?php
/**
 * Technician: start work or submit a completed repair for verification.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(ROLE_TECHNICIAN);
require_post();

$report_id = (int)($_POST['report_id'] ?? 0);
$action    = $_POST['action'] ?? '';
$notes     = trim($_POST['notes'] ?? '');
$tech_id   = (int)$_SESSION['user_id'];

$report = get_report($pdo, $report_id);
if (!$report) {
    set_flash('error', 'Job not found.');
    redirect('technician/jobs.php');
}

$assignment = get_report_assignment($pdo, $report_id);
if (!$assignment || (int)$assignment['technician_id'] !== $tech_id) {
    set_flash('error', 'This job is not assigned to you.');
    redirect('technician/jobs.php');
}

if ($action === 'start') {
    if ($report['status'] !== STATUS_ASSIGNED) {
        set_flash('error', 'This job cannot be started from its current status.');
        redirect('technician/job-view.php?id=' . $report_id);
    }
    if (mb_strlen($notes) < 3) $notes = 'Technician started work on site.';

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE assignments SET status = 'in_progress' WHERE id = ?")->execute([(int)$assignment['id']]);
    $pdo->prepare("UPDATE reports SET status = ? WHERE id = ?")->execute([STATUS_IN_PROGRESS, $report_id]);
    record_status_change($pdo, $report_id, $tech_id, $report['status'], STATUS_IN_PROGRESS, $notes);
    if (!empty($report['student_id'])) {
        create_notification($pdo, (int)$report['student_id'], $report_id, 'Work started: ' . $report['tracking_code'], $notes, 'status_change');
    }
    $pdo->commit();
    set_flash('success', 'Job marked as in progress.');

} elseif ($action === 'resolve') {
    if (!in_array($report['status'], [STATUS_ASSIGNED, STATUS_IN_PROGRESS], true)) {
        set_flash('error', 'This job cannot be resolved from its current status.');
        redirect('technician/job-view.php?id=' . $report_id);
    }
    if (mb_strlen($notes) < 10) {
        set_flash('error', 'Please add repair notes of at least 10 characters before submitting.');
        redirect('technician/job-view.php?id=' . $report_id);
    }

    $uploads = ['paths' => [], 'errors' => []];
    if (isset($_FILES['completion_photos'])) {
        $uploads = handle_multiple_image_uploads($_FILES['completion_photos'], 'completion');
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE assignments SET status = 'completed', completed_at = NOW() WHERE id = ?")
            ->execute([(int)$assignment['id']]);
        $pdo->prepare("UPDATE reports SET status = ?, resolved_at = NOW() WHERE id = ?")
            ->execute([STATUS_RESOLVED, $report_id]);

        foreach ($uploads['paths'] as $image) {
            $img = $pdo->prepare("
                INSERT INTO report_images (report_id, uploaded_by, image_type, file_path, original_filename, file_size, caption)
                VALUES (?, ?, 'completion', ?, ?, ?, 'Completion photograph')
            ");
            $img->execute([$report_id, $tech_id, $image['relative_path'], $image['original_filename'], $image['file_size']]);
        }

        record_status_change($pdo, $report_id, $tech_id, $report['status'], STATUS_RESOLVED, $notes);
        if (!empty($report['student_id'])) {
            create_notification(
                $pdo, (int)$report['student_id'], $report_id,
                'Repair completed - verification needed',
                'Your issue ' . $report['tracking_code'] . ' has been marked resolved. Please verify the repair. ' . $notes,
                'verification_needed'
            );
        }
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
        foreach ($admins as $admin) {
            create_notification($pdo, (int)$admin['id'], $report_id, 'Job resolved: ' . $report['tracking_code'], $notes, 'status_change');
        }

        $pdo->commit();
        set_flash('success', 'Repair submitted for student verification.');
        if ($uploads['errors']) {
            set_flash('warning', 'Some photos were rejected: ' . implode(' ', $uploads['errors']));
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', 'Could not submit the repair. Please try again.');
    }
} else {
    set_flash('error', 'Invalid update action.');
}

redirect('technician/job-view.php?id=' . $report_id);
