<?php
/**
 * Guest: submit a report without an account.
 * The reporter receives a private token link to track and verify the issue.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_post();

$name        = trim($_POST['guest_name'] ?? '');
$email       = trim($_POST['guest_email'] ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);
$facility_id = (int)($_POST['facility_id'] ?? 0);
$room_number = trim($_POST['room_number'] ?? '');
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$priority    = strtolower($_POST['priority'] ?? 'medium');

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Your name must be 2-100 characters.';
if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) $errors[] = 'Enter a valid email address.';
if (mb_strlen($title) < 5 || mb_strlen($title) > 200) $errors[] = 'Issue title must be 5-200 characters.';
if (mb_strlen($description) < 10 || mb_strlen($description) > 5000) $errors[] = 'Description must be 10-5000 characters.';
if ($room_number === '' || mb_strlen($room_number) > 100) $errors[] = 'Room / specific location is required.';
if (!array_key_exists($priority, PRIORITIES)) $errors[] = 'Select a valid priority level.';

$cat = $pdo->prepare("SELECT 1 FROM categories WHERE id = ? AND is_active = 1");
$cat->execute([$category_id]);
if (!$cat->fetchColumn()) $errors[] = 'Select a valid issue category.';
$fac = $pdo->prepare("SELECT 1 FROM facilities WHERE id = ? AND is_active = 1");
$fac->execute([$facility_id]);
if (!$fac->fetchColumn()) $errors[] = 'Select a valid building or facility.';
if (isset($_FILES['evidence']) && is_array($_FILES['evidence']['name'] ?? null) && count($_FILES['evidence']['name']) > 5) {
    $errors[] = 'You may attach a maximum of 5 photographs.';
}

if ($errors) {
    set_flash('error', implode(' ', $errors));
    redirect('guest-report.php');
}

$uploads = isset($_FILES['evidence'])
    ? handle_multiple_image_uploads($_FILES['evidence'], 'evidence')
    : ['paths' => [], 'errors' => []];

try {
    $pdo->beginTransaction();

    $tracking = generate_tracking_code($pdo);
    $token = bin2hex(random_bytes(24)); // 48 hex chars, unguessable private link

    $stmt = $pdo->prepare("
        INSERT INTO reports (tracking_code, student_id, guest_name, guest_email, guest_token, is_guest,
                             category_id, facility_id, room_number, title, description, priority, status)
        VALUES (?, NULL, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, 'REPORTED')
    ");
    $stmt->execute([$tracking, $name, $email !== '' ? $email : null, $token,
                    $category_id, $facility_id, $room_number, $title, $description, $priority]);
    $report_id = (int)$pdo->lastInsertId();

    // report_images.uploaded_by has an FK to users, so attribute guest uploads to the system admin.
    $systemUser = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();

    foreach ($uploads['paths'] as $image) {
        $img = $pdo->prepare("INSERT INTO report_images (report_id, uploaded_by, image_type, file_path, original_filename, file_size) VALUES (?, ?, 'evidence', ?, ?, ?)");
        $img->execute([$report_id, $systemUser, $image['relative_path'], $image['original_filename'], $image['file_size']]);
    }

    record_status_change($pdo, $report_id, $systemUser ?: 1, null, STATUS_REPORTED, 'Issue reported by guest: ' . $name . '.');

    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
    foreach ($admins as $admin) {
        create_notification($pdo, (int)$admin['id'], $report_id, 'New guest report: ' . $tracking,
            'A guest reported "' . $title . '".', 'new_report');
    }

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'The report could not be saved. Please try again.');
    redirect('guest-report.php');
}

if ($uploads['errors']) {
    set_flash('warning', 'Some photos were rejected: ' . implode(' ', $uploads['errors']));
}
redirect('guest-receipt.php?code=' . urlencode($tracking) . '&token=' . urlencode($token));
