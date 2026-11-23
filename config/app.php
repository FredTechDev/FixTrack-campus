<?php
/**
 * FixTrack Campus - Global Application Configuration
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Application Information
define('APP_NAME', 'FixTrack Campus');
define('APP_TAGLINE', 'Campus Facilities & Maintenance Management System');
define('APP_VERSION', '1.0.0');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('EVIDENCE_UPLOAD_PATH', UPLOADS_PATH . '/evidence');
define('COMPLETION_UPLOAD_PATH', UPLOADS_PATH . '/completion');

// Base URL calculation (supports local dev, docker port 8085, or standard)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8085';
define('BASE_URL', $protocol . $host);

// User Roles
define('ROLE_STUDENT', 'student');
define('ROLE_ADMIN', 'admin');
define('ROLE_TECHNICIAN', 'technician');

// Issue Workflow Statuses
define('STATUS_REPORTED', 'REPORTED');
define('STATUS_UNDER_REVIEW', 'UNDER REVIEW');
define('STATUS_APPROVED', 'APPROVED');
define('STATUS_ASSIGNED', 'ASSIGNED');
define('STATUS_IN_PROGRESS', 'IN PROGRESS');
define('STATUS_RESOLVED', 'RESOLVED');
define('STATUS_VERIFIED', 'VERIFIED');
define('STATUS_CLOSED', 'CLOSED');
define('STATUS_REJECTED', 'REJECTED');

// All valid workflow transitions map
const WORKFLOW_STEPS = [
    STATUS_REPORTED     => ['order' => 1, 'label' => 'Reported', 'color' => 'secondary'],
    STATUS_UNDER_REVIEW => ['order' => 2, 'label' => 'Under Review', 'color' => 'info'],
    STATUS_APPROVED     => ['order' => 3, 'label' => 'Approved', 'color' => 'primary'],
    STATUS_ASSIGNED     => ['order' => 4, 'label' => 'Assigned', 'color' => 'warning'],
    STATUS_IN_PROGRESS  => ['order' => 5, 'label' => 'In Progress', 'color' => 'indigo'],
    STATUS_RESOLVED     => ['order' => 6, 'label' => 'Resolved', 'color' => 'teal'],
    STATUS_VERIFIED     => ['order' => 7, 'label' => 'Verified', 'color' => 'cyan'],
    STATUS_CLOSED       => ['order' => 8, 'label' => 'Closed', 'color' => 'success'],
    STATUS_REJECTED     => ['order' => 0, 'label' => 'Rejected', 'color' => 'danger']
];

// Issue Priorities
const PRIORITIES = [
    'low' => ['label' => 'Low', 'color' => 'secondary', 'default_hours' => 96],
    'medium' => ['label' => 'Medium', 'color' => 'info', 'default_hours' => 48],
    'high' => ['label' => 'High', 'color' => 'warning', 'default_hours' => 24],
    'critical' => ['label' => 'Critical', 'color' => 'danger', 'default_hours' => 12]
];

// File Upload Constraints
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB
const ALLOWED_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];
