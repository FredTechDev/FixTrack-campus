<?php
/**
 * Student: submit a new maintenance report with optional photo evidence.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(ROLE_STUDENT);
require_post();

$category_id = (int)($_POST['category_id'] ?? 0);
$facility_id = (int)($_POST['facility_id'] ?? 0);
$room_number = trim($_POST['room_number'] ?? '');
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$priority    = strtolower($_POST['priority'] ?? 'medium');
$student_id  = (int)$_SESSION['user_id'];

$errors = [];

$catCheck = $pdo->prepare("SELECT 1 FROM categories WHERE id = ? AND is_active = 1");
$catCheck->execute([$category_id]);
if ($category_id <= 0 || !$catCheck->fetchColumn()) {
    $errors[] = 'Select a valid issue category.';
}
$facCheck = $pdo->prepare("SELECT 1 FROM facilities WHERE id = ? AND is_active = 1");
$facCheck->execute([$facility_id]);
if ($facility_id <= 0 || !$facCheck->fetchColumn()) {
    $errors[] = 'Select a valid building or facility.';
}
if ($room_number === '' || mb_strlen($room_number) > 100) {
    $errors[] = 'Room / specific location is required (max 100 characters).';
}
if (mb_strlen($title) < 5 || mb_strlen($title) > 200) {
    $errors[] = 'Issue title must be between 5 and 200 characters.';
}
if (mb_strlen($description) < 10 || mb_strlen($description) > 5000) {
    $errors[] = 'Description must be between 10 and 5000 characters.';
}
if (!array_key_exists($priority, PRIORITIES)) {
    $errors[] = 'Select a valid priority level.';
}
if (isset($_FILES['evidence']) && is_array($_FILES['evidence']['name'] ?? null) && count($_FILES['evidence']['name']) > 5) {
    $errors[] = 'You may attach a maximum of 5 photographs.';
}

if ($errors) {
    set_flash('error', implode(' ', $errors));
    redirect('student/report-new.php');
}

$uploads = ['paths' => [], 'errors' => []];
if (isset($_FILES['evidence'])) {
    $uploads = handle_multiple_image_uploads($_FILES['evidence'], 'evidence');
}
if ($uploads['errors']) {
    set_flash('warning', 'Report submitted, but some images were rejected: ' . implode(' ', $uploads['errors']));
} else {
    set_flash('success', 'Your report has been submitted.');
}

try {
    $pdo->beginTransaction();

    $tracking = generate_tracking_code($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO reports (tracking_code, student_id, category_id, facility_id, room_number, title, description, priority, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'REPORTED')
    ");
    $stmt->execute([$tracking, $student_id, $category_id, $facility_id, $room_number, $title, $description, $priority]);
    $report_id = (int)$pdo->lastInsertId();

    foreach ($uploads['paths'] as $image) {
        $img = $pdo->prepare("
            INSERT INTO report_images (report_id, uploaded_by, image_type, file_path, original_filename, file_size)
            VALUES (?, ?, 'evidence', ?, ?, ?)
        ");
        $img->execute([$report_id, $student_id, $image['relative_path'], $image['original_filename'], $image['file_size']]);
    }

    record_status_change($pdo, $report_id, $student_id, null, STATUS_REPORTED, 'Issue reported via FixTrack Campus.');

    // Alert every administrator about the new report.
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
    foreach ($admins as $admin) {
        create_notification(
            $pdo,
            (int)$admin['id'],
            $report_id,
            'New report: ' . $tracking,
            'A new ' . $priority . ' priority issue "' . $title . '" was submitted.',
            'new_report'
        );
    }

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'The report could not be saved. Please try again.');
    redirect('student/report-new.php');
}

// Preserve the tracking code in the success message.
$_SESSION['flash_success'] = 'Report submitted successfully. Your tracking code is ' . $tracking . '.';
redirect('student/report-view.php?id=' . $report_id);
