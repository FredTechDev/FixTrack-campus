<?php
/**
 * Role-aware dashboard router.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

switch ($_SESSION['user_role']) {
    case ROLE_ADMIN:      redirect('admin/dashboard.php');
    case ROLE_TECHNICIAN: redirect('technician/dashboard.php');
    default:              redirect('student/dashboard.php');
}
