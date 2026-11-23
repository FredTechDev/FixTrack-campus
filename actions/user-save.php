<?php
/**
 * Administrator: create or update a student / technician account.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(ROLE_ADMIN);
require_post();

$id             = (int)($_POST['id'] ?? 0);
$full_name      = trim($_POST['full_name'] ?? '');
$email          = trim($_POST['email'] ?? '');
$password       = $_POST['password'] ?? '';
$role           = $_POST['role'] ?? '';
$phone          = trim($_POST['phone'] ?? '');
$identifier     = trim($_POST['identifier'] ?? '');
$specialization = trim($_POST['specialization'] ?? '');
$is_active      = isset($_POST['is_active']) ? 1 : 0;
$current_id     = (int)$_SESSION['user_id'];

$errors = [];
if (!in_array($role, [ROLE_STUDENT, ROLE_TECHNICIAN], true)) $errors[] = 'Invalid role.';
if (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 100) $errors[] = 'Full name must be 2-100 characters.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) $errors[] = 'Valid email required.';
if ($id === 0 && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
    $errors[] = 'New accounts need a password of 8+ characters with mixed case and a number.';
}
if ($password !== '' && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
    $errors[] = 'Password must be 8+ characters with mixed case and a number.';
}
if ($id === $current_id && !$is_active) $errors[] = 'You cannot deactivate your own account.';

$dup = $pdo->prepare("SELECT id FROM users WHERE email = ?" . ($id > 0 ? " AND id <> ?" : "") . " LIMIT 1");
$dup->execute($id > 0 ? [$email, $id] : [$email]);
if ($dup->fetch()) $errors[] = 'That email address is already in use.';

if ($errors) {
    set_flash('error', implode(' ', $errors));
    redirect('admin/users.php' . ($id > 0 ? '?edit=' . $id : ''));
}

try {
    if ($id > 0) {
        $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, phone = ?, identifier = ?,
                specialization = ?, is_active = ?";
        $params = [$full_name, $email, $role, $phone ?: null, $identifier ?: null,
                   $role === ROLE_TECHNICIAN ? ($specialization ?: null) : null, $is_active];
        if ($password !== '') {
            $sql .= ", password_hash = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        set_flash('success', 'Account updated.');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, email, password_hash, role, phone, identifier, specialization, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $full_name, $email, password_hash($password, PASSWORD_DEFAULT), $role,
            $phone ?: null, $identifier ?: null,
            $role === ROLE_TECHNICIAN ? ($specialization ?: null) : null, $is_active
        ]);
        set_flash('success', 'Account created for ' . $full_name . '.');
    }
} catch (Throwable $ex) {
    set_flash('error', 'Could not save the account. Please check the details and try again.');
}
redirect('admin/users.php');
