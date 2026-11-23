<?php
/**
 * Mark one notification (or all of the current user's) as read.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_post();

$user_id = (int)$_SESSION['user_id'];
$id      = (int)($_POST['id'] ?? 0);
$all     = isset($_POST['all']);

if ($all) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
    set_flash('success', 'All notifications marked as read.');
} elseif ($id > 0) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
}

redirect_back('notifications.php');
