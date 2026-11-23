<?php
/** Guest: reopen a verified/closed report via the private tracking link. */
require_once __DIR__ . '/../includes/bootstrap.php';

require_post();

$code = $_POST['code'] ?? '';
$token = $_POST['token'] ?? '';
$reason = trim($_POST['reason'] ?? '');
$report = get_guest_report($pdo, $code, $token);
$back = 'track.php?code=' . urlencode($code) . '&token=' . urlencode($token);

if (!$report) { set_flash('error', 'Invalid tracking link.'); redirect($back); }
if (!in_array($report['status'], [STATUS_VERIFIED, STATUS_CLOSED], true)) { set_flash('error', 'This report cannot be reopened.'); redirect($back); }
if (mb_strlen($reason) < 10) { set_flash('error', 'Please explain what is still wrong (at least 10 characters).'); redirect($back); }

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE reports SET status = ?, resolved_at = NULL, verified_at = NULL, closed_at = NULL WHERE id = ?")->execute([STATUS_IN_PROGRESS, (int)$report['id']]);
    $pdo->prepare("UPDATE assignments SET status = 'in_progress', completed_at = NULL WHERE report_id = ? AND status = 'completed'")->execute([(int)$report['id']]);
    record_status_change($pdo, (int)$report['id'], 1, $report['status'], STATUS_IN_PROGRESS, 'Guest reopened the issue: ' . $reason);
    $notify = [];
    $assignment = get_report_assignment($pdo, (int)$report['id']);
    if ($assignment) $notify[] = (int)$assignment['technician_id'];
    foreach ($pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll() as $admin) $notify[] = (int)$admin['id'];
    foreach (array_unique($notify) as $uid) {
        create_notification($pdo, $uid, (int)$report['id'], 'Guest issue reopened: ' . $report['tracking_code'], $reason, 'reopen');
    }
    $pdo->commit();
    set_flash('success', 'Issue reopened. The maintenance team has been notified.');
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Could not reopen the issue. Please try again.');
}
redirect($back);
