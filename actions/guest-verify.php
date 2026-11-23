<?php
/** Guest: verify (or reject) a completed repair via the private tracking link. */
require_once __DIR__ . '/../includes/bootstrap.php';

require_post();

$code = $_POST['code'] ?? '';
$token = $_POST['token'] ?? '';
$report = get_guest_report($pdo, $code, $token);
$back = 'track.php?code=' . urlencode($code) . '&token=' . urlencode($token);

if (!$report) {
    set_flash('error', 'Invalid tracking link.');
    redirect($back);
}
if ($report['status'] !== STATUS_RESOLVED) {
    set_flash('error', 'This report is not awaiting verification.');
    redirect($back);
}

$satisfied = (int)($_POST['satisfied'] ?? 0);
$rating = (int)($_POST['rating'] ?? 5);
$feedback = trim($_POST['feedback'] ?? '');
if ($rating < 1 || $rating > 5) { set_flash('error', 'Choose a rating from 1 to 5.'); redirect($back); }
if (!$satisfied && mb_strlen($feedback) < 10) { set_flash('error', 'Tell us what is still wrong (at least 10 characters).'); redirect($back); }

try {
    $pdo->beginTransaction();
    if ($satisfied) {
        $pdo->prepare("UPDATE reports SET status = ?, verified_at = NOW() WHERE id = ?")->execute([STATUS_VERIFIED, (int)$report['id']]);
        $note = 'Guest verified the repair. Rating: ' . $rating . '/5.' . ($feedback ? ' ' . $feedback : '');
        $message = 'Thank you. The repair has been verified.';
    } else {
        $pdo->prepare("UPDATE reports SET status = ?, resolved_at = NULL WHERE id = ?")->execute([STATUS_IN_PROGRESS, (int)$report['id']]);
        $pdo->prepare("UPDATE assignments SET status = 'in_progress', completed_at = NULL WHERE report_id = ? AND status = 'completed'")->execute([(int)$report['id']]);
        $note = 'Guest rejected the repair: ' . $feedback;
        $message = 'The issue has been reopened for further work.';
    }
    record_status_change($pdo, (int)$report['id'], 1, $report['status'], $satisfied ? STATUS_VERIFIED : STATUS_IN_PROGRESS, $note);

    $notify = [];
    $assignment = get_report_assignment($pdo, (int)$report['id']);
    if ($assignment) $notify[] = (int)$assignment['technician_id'];
    foreach ($pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll() as $admin) $notify[] = (int)$admin['id'];
    foreach (array_unique($notify) as $uid) {
        create_notification($pdo, $uid, (int)$report['id'], 'Guest report update: ' . $report['tracking_code'], $note, 'status_change');
    }
    $pdo->commit();
    set_flash('success', $message);
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Could not record your feedback. Please try again.');
}
redirect($back);
