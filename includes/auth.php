<?php
/**
 * FixTrack Campus - Authentication & Authorization System
 * Implements session security, Role-Based Access Control (RBAC), and CSRF protection.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Check if the user is authenticated
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Retrieve the current logged-in user details
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id'             => $_SESSION['user_id'],
        'name'           => $_SESSION['user_name'] ?? '',
        'email'          => $_SESSION['user_email'] ?? '',
        'role'           => $_SESSION['user_role'] ?? '',
        'identifier'     => $_SESSION['user_identifier'] ?? '',
        'phone'          => $_SESSION['user_phone'] ?? '',
        'specialization' => $_SESSION['user_specialization'] ?? ''
    ];
}

/**
 * Enforce authentication requirement
 */
function require_login(string $redirect = '/login.php'): void {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Please sign in to access this page.';
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Enforce role-based access control
 * @param string|array $allowed_roles
 */
function require_role($allowed_roles): void {
    require_login();
    $roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
    $currentRole = $_SESSION['user_role'] ?? '';

    if (!in_array($currentRole, $roles, true)) {
        http_response_code(403);
        include_once ROOT_PATH . '/includes/header.php';
        echo '<div class="container my-5"><div class="card p-5 text-center shadow-sm border-danger">
            <h2 class="text-danger mb-3"><i class="fas fa-shield-alt"></i> Access Denied</h2>
            <p class="text-muted">You do not have permission to view or perform actions on this page.</p>
            <div><a href="/' . urlencode($currentRole) . '/dashboard.php" class="btn btn-primary mt-3">Return to Dashboard</a></div>
        </div></div>';
        include_once ROOT_PATH . '/includes/footer.php';
        exit;
    }
}

/**
 * Log in a user and set up session
 */
function login_user(array $user): void {
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    $_SESSION['user_id']             = (int)$user['id'];
    $_SESSION['user_name']           = $user['full_name'];
    $_SESSION['user_email']          = $user['email'];
    $_SESSION['user_role']           = $user['role'];
    $_SESSION['user_identifier']     = $user['identifier'] ?? '';
    $_SESSION['user_phone']          = $user['phone'] ?? '';
    $_SESSION['user_specialization'] = $user['specialization'] ?? '';
    $_SESSION['logged_in_time']      = time();
}

/**
 * Log out user and destroy session
 */
function logout_user(): void {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Generate or get CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output HTML hidden field with CSRF token
 */
function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validate CSRF token from POST request
 */
function validate_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('Invalid or missing security token (CSRF). Please refresh the page and try again.');
        }
    }
}
