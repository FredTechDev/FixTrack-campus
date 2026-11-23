<?php
/**
 * FixTrack Campus - Utility and Helper Functions
 */

require_once __DIR__ . '/../config/app.php';

/**
 * Build an absolute URL for an in-app path.
 */
function url(string $path = ''): string {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Redirect to an in-app path and stop execution.
 */
function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

/**
 * Escape string for safe HTML output (XSS protection)
 */
function e(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a unique tracking code (e.g. FTC-2026-48291)
 */
function generate_tracking_code(PDO $pdo): string {
    $year = date('Y');
    $attempts = 0;

    do {
        // 5 random digits or alphanumeric
        $num = mt_rand(10000, 99999);
        $code = "FTC-{$year}-{$num}";

        $stmt = $pdo->prepare("SELECT id FROM reports WHERE tracking_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $exists = $stmt->fetch();
        $attempts++;
    } while ($exists && $attempts < 10);

    return $code;
}

/**
 * Record an issue workflow transition in status_history
 */
function record_status_change(PDO $pdo, int $report_id, int $changed_by, ?string $old_status, string $new_status, ?string $notes = null): bool {
    $stmt = $pdo->prepare("
        INSERT INTO status_history (report_id, changed_by, old_status, new_status, notes, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    return $stmt->execute([$report_id, $changed_by, $old_status, $new_status, $notes]);
}

/**
 * Create a persistent notification for a user
 */
function create_notification(PDO $pdo, int $user_id, ?int $report_id, string $title, string $message, string $type = 'status_update'): bool {
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, report_id, title, message, type, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    return $stmt->execute([$user_id, $report_id, $title, $message, $type]);
}

/**
 * Get count of unread notifications for a user
 */
function get_unread_notifications_count(PDO $pdo, int $user_id): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * Format status as an accessible HTML badge
 */
function format_status_badge(string $status): string {
    $colorClass = 'badge-secondary';
    $icon = 'info-circle';

    switch (strtoupper($status)) {
        case STATUS_REPORTED:
            $colorClass = 'badge-status-reported';
            $icon = 'inbox';
            break;
        case STATUS_UNDER_REVIEW:
            $colorClass = 'badge-status-review';
            $icon = 'search';
            break;
        case STATUS_APPROVED:
            $colorClass = 'badge-status-approved';
            $icon = 'check-circle';
            break;
        case STATUS_ASSIGNED:
            $colorClass = 'badge-status-assigned';
            $icon = 'user-check';
            break;
        case STATUS_IN_PROGRESS:
            $colorClass = 'badge-status-inprogress';
            $icon = 'tools';
            break;
        case STATUS_RESOLVED:
            $colorClass = 'badge-status-resolved';
            $icon = 'clipboard-check';
            break;
        case STATUS_VERIFIED:
            $colorClass = 'badge-status-verified';
            $icon = 'star';
            break;
        case STATUS_CLOSED:
            $colorClass = 'badge-status-closed';
            $icon = 'lock';
            break;
        case STATUS_REJECTED:
            $colorClass = 'badge-status-rejected';
            $icon = 'times-circle';
            break;
    }

    return '<span class="badge ' . $colorClass . '"><i class="fas fa-' . $icon . ' mr-1"></i> ' . e($status) . '</span>';
}

/**
 * Format priority as an accessible HTML badge
 */
function format_priority_badge(string $priority): string {
    $p = strtolower($priority);
    $colorClass = 'badge-secondary';
    $icon = 'flag';

    switch ($p) {
        case 'critical':
            $colorClass = 'badge-priority-critical';
            $icon = 'exclamation-triangle';
            break;
        case 'high':
            $colorClass = 'badge-priority-high';
            $icon = 'arrow-up';
            break;
        case 'medium':
            $colorClass = 'badge-priority-medium';
            $icon = 'minus';
            break;
        case 'low':
            $colorClass = 'badge-priority-low';
            $icon = 'arrow-down';
            break;
    }

    return '<span class="badge ' . $colorClass . '"><i class="fas fa-' . $icon . ' mr-1"></i> ' . ucfirst(e($priority)) . '</span>';
}

/**
 * Check if a report is overdue
 */
function is_overdue(?string $deadline, string $status): bool {
    if (empty($deadline)) {
        return false;
    }
    // Terminal or completed statuses are not overdue
    if (in_array(strtoupper($status), [STATUS_RESOLVED, STATUS_VERIFIED, STATUS_CLOSED, STATUS_REJECTED], true)) {
        return false;
    }
    return strtotime($deadline) < time();
}

/**
 * Get human-readable time elapsed string
 */
function time_ago(?string $datetime): string {
    if (!$datetime) return 'N/A';
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) {
        $m = floor($diff / 60);
        return $m . ($m == 1 ? ' min ago' : ' mins ago');
    }
    if ($diff < 86400) {
        $h = floor($diff / 3600);
        return $h . ($h == 1 ? ' hr ago' : ' hrs ago');
    }
    $d = floor($diff / 86400);
    if ($d < 30) {
        return $d . ($d == 1 ? ' day ago' : ' days ago');
    }
    return date('M j, Y', $time);
}

/**
 * Format readable date
 */
function format_date(?string $datetime, string $format = 'M j, Y g:i A'): string {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

/**
 * Set flash message in session
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Get and clear flash message from session
 */
function get_flash(string $type): ?string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

/**
 * Render flash message alerts
 */
function render_flash_messages(): string {
    $html = '';
    $types = ['success' => 'check-circle', 'error' => 'exclamation-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'];

    foreach ($types as $type => $icon) {
        $msg = get_flash($type);
        if ($msg) {
            $alertClass = ($type === 'error') ? 'alert-danger' : 'alert-' . $type;
            $html .= '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">
                <i class="fas fa-' . $icon . ' mr-2"></i> ' . e($msg) . '
                <button type="button" class="close-btn" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
            </div>';
        }
    }
    return $html;
}

/**
 * Secure file upload validator and processor
 */
function handle_image_upload(array $file, string $subfolder = 'evidence'): array {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'error' => 'No file was uploaded.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error code: ' . $file['error']];
    }

    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return ['success' => false, 'error' => 'File size exceeds maximum allowed limit of 5MB.'];
    }

    // Verify MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mime, ALLOWED_MIME_TYPES)) {
        return ['success' => false, 'error' => 'Invalid file format. Only JPEG, PNG, and WebP images are permitted.'];
    }

    $ext = ALLOWED_MIME_TYPES[$mime];
    $uploadDir = UPLOADS_PATH . '/' . trim($subfolder, '/');

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate safe, unguessable filename
    $filename = bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file to disk.'];
    }

    return [
        'success'           => true,
        'relative_path'     => 'uploads/' . trim($subfolder, '/') . '/' . $filename,
        'original_filename' => basename($file['name']),
        'file_size'         => (int)$file['size'],
        'mime_type'         => $mime
    ];
}

/**
 * Process a multi-file upload field (the nested $_FILES array shape).
 */
function handle_multiple_image_uploads(array $files, string $subfolder = 'evidence'): array {
    $paths = [];
    $errors = [];
    if (!isset($files['name'])) return ['paths' => $paths, 'errors' => $errors];

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    foreach ($names as $i => $name) {
        if (is_array($name)) continue;
        $single = [
            'name'     => $name,
            'type'     => is_array($files['type'] ?? null) ? ($files['type'][$i] ?? '') : ($files['type'] ?? ''),
            'tmp_name' => is_array($files['tmp_name'] ?? null) ? ($files['tmp_name'][$i] ?? '') : ($files['tmp_name'] ?? ''),
            'error'    => is_array($files['error'] ?? null) ? ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) : ($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size'     => is_array($files['size'] ?? null) ? ($files['size'][$i] ?? 0) : ($files['size'] ?? 0),
        ];
        if ($single['error'] === UPLOAD_ERR_NO_FILE) continue;
        $result = handle_image_upload($single, $subfolder);
        if ($result['success']) $paths[] = $result;
        else $errors[] = $name . ': ' . $result['error'];
    }
    return ['paths' => $paths, 'errors' => $errors];
}

/* =========================================================================
   Data-access helpers
   ========================================================================= */

/**
 * Fetch a single report joined with its category, facility and reporter.
 */
function get_report(PDO $pdo, int $report_id): ?array {
    $stmt = $pdo->prepare("
        SELECT r.*,
               c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
               f.name AS facility_name, f.building_code, f.campus_zone,
               u.full_name AS student_name, u.email AS student_email, u.phone AS student_phone,
               u.identifier AS student_identifier
        FROM reports r
        JOIN categories c ON c.id = r.category_id
        JOIN facilities f ON f.id = r.facility_id
        JOIN users u ON u.id = r.student_id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->execute([$report_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Fetch all images for a report, optionally filtered by type.
 */
function get_report_images(PDO $pdo, int $report_id, ?string $type = null): array {
    $sql = "SELECT i.*, u.full_name AS uploader_name
            FROM report_images i
            JOIN users u ON u.id = i.uploaded_by
            WHERE i.report_id = ?";
    $params = [$report_id];
    if ($type !== null) {
        $sql .= " AND i.image_type = ?";
        $params[] = $type;
    }
    $sql .= " ORDER BY i.created_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Fetch the complete status history for a report (oldest first).
 */
function get_status_history(PDO $pdo, int $report_id): array {
    $stmt = $pdo->prepare("
        SELECT h.*, u.full_name AS changed_by_name, u.role AS changed_by_role
        FROM status_history h
        JOIN users u ON u.id = h.changed_by
        WHERE h.report_id = ?
        ORDER BY h.created_at ASC, h.id ASC
    ");
    $stmt->execute([$report_id]);
    return $stmt->fetchAll();
}

/**
 * Fetch the active assignment for a report together with technician details.
 */
function get_report_assignment(PDO $pdo, int $report_id): ?array {
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name AS technician_name, u.email AS technician_email,
               u.phone AS technician_phone, u.specialization
        FROM assignments a
        JOIN users u ON u.id = a.technician_id
        WHERE a.report_id = ? AND a.status <> 'reassigned'
        ORDER BY a.assigned_at DESC
        LIMIT 1
    ");
    $stmt->execute([$report_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Return all active categories.
 */
function get_categories(PDO $pdo): array {
    return $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
}

/**
 * Return all active facilities.
 */
function get_facilities(PDO $pdo): array {
    return $pdo->query("SELECT * FROM facilities WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
}

/**
 * Return all active technicians (optionally filtered by specialization).
 */
function get_technicians(PDO $pdo, ?int $category_id = null): array {
    $sql = "SELECT id, full_name, email, identifier, specialization
            FROM users WHERE role = 'technician' AND is_active = 1";
    $params = [];
    if ($category_id !== null) {
        // Prefer technicians whose specialization matches the category name loosely.
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $name = (string)$stmt->fetchColumn();
        $keywords = preg_split('/[^a-z]+/', strtolower($name)) ?: [];
        $keywords = array_filter($keywords, fn($w) => strlen($w) > 3);
        if ($keywords) {
            $clauses = [];
            foreach ($keywords as $i => $word) {
                $clauses[] = "LOWER(COALESCE(specialization,'')) LIKE ?";
                $params[] = '%' . $word . '%';
            }
            $sql .= " ORDER BY CASE WHEN (" . implode(' OR ', $clauses) . ") THEN 0 ELSE 1 END, full_name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        }
    }
    $sql .= " ORDER BY full_name ASC";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Render the workflow progress stepper for a report.
 */
function workflow_stepper(string $status): string {
    $current = WORKFLOW_STEPS[strtoupper($status)]['order'] ?? 1;
    $isRejected = strtoupper($status) === STATUS_REJECTED;
    $html = '<div class="workflow-stepper" role="list" aria-label="Issue progress">';
    foreach (WORKFLOW_STEPS as $key => $step) {
        if ($key === STATUS_REJECTED) continue;
        $classes = ['workflow-step'];
        if ($isRejected) {
            $classes[] = ($step['order'] === 1) ? 'rejected-node' : '';
        } elseif ($step['order'] < $current) {
            $classes[] = 'completed';
        } elseif ($step['order'] === $current) {
            $classes[] = 'active';
        }
        $html .= '<div role="listitem" class="' . trim(implode(' ', $classes)) . '">'
               . '<div class="step-node"><i class="fas fa-' . ($step['order'] <= $current && !$isRejected ? 'check' : 'circle') . '"></i></div>'
               . '<div class="step-title">' . e($step['label']) . '</div>'
               . '</div>';
    }
    return $html . '</div>';
}

/**
 * Human-readable label for an upload status / enum value.
 */
function humanize(string $value): string {
    return ucwords(strtolower(str_replace('_', ' ', $value)));
}

/**
 * Return a safe download/display URL for an uploaded image path stored in DB.
 */
function image_url(?string $path): string {
    if (!$path) return '';
    // Stored paths are relative (uploads/evidence/...). Reject anything else.
    if (!preg_match('#^uploads/[A-Za-z0-9_/\-]+$#', $path)) return '';
    return url($path);
}

/**
 * Write an audit-style activity entry to both status_history and notifications.
 */
function apply_status_change(
    PDO $pdo,
    int $report_id,
    int $changed_by,
    string $new_status,
    ?string $notes = null,
    array $notify_user_ids = [],
    ?string $notification_title = null
): void {
    $report = get_report($pdo, $report_id);
    $old = $report['status'] ?? null;
    $stmt = $pdo->prepare("UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $report_id]);
    record_status_change($pdo, $report_id, $changed_by, $old, $new_status, $notes);

    $title = $notification_title ?? ('Report Update: ' . $new_status);
    $message = 'Report ' . ($report['tracking_code'] ?? ('#' . $report_id)) . ' is now ' . $new_status . '.';
    if ($notes) $message .= ' ' . $notes;
    foreach (array_unique(array_filter($notify_user_ids)) as $uid) {
        create_notification($pdo, (int)$uid, $report_id, $title, $message, 'status_change');
    }
}

/**
 * Require a POST request with a valid CSRF token.
 */
function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method not allowed.');
    }
    validate_csrf();
}

/**
 * Redirect back to the referring page when it is internal, otherwise to a safe default.
 */
function redirect_back(string $default): void {
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '' && strpos($ref, BASE_URL) === 0) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($default);
}

/**
 * Validate that a requested status transition is allowed.
 */
function is_valid_status_transition(string $from, string $to): bool {
    $from = strtoupper($from);
    $to = strtoupper($to);
    $map = [
        STATUS_REPORTED     => [STATUS_UNDER_REVIEW, STATUS_APPROVED, STATUS_REJECTED],
        STATUS_UNDER_REVIEW => [STATUS_APPROVED, STATUS_REJECTED],
        STATUS_APPROVED     => [STATUS_ASSIGNED, STATUS_REJECTED],
        STATUS_ASSIGNED     => [STATUS_IN_PROGRESS, STATUS_REJECTED],
        STATUS_IN_PROGRESS  => [STATUS_RESOLVED],
        STATUS_RESOLVED     => [STATUS_VERIFIED],
        STATUS_VERIFIED     => [STATUS_CLOSED, STATUS_IN_PROGRESS], // allow reopen after verification failure
        STATUS_CLOSED       => [STATUS_IN_PROGRESS],                // reopen unresolved issue
        STATUS_REJECTED     => [],
    ];
    return in_array($to, $map[$from] ?? [], true);
}
