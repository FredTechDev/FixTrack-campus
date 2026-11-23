<?php
/**
 * AJAX endpoint: suggest existing reports that look similar to a typed title.
 * Returns JSON only. Never used for security decisions.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['duplicates' => []]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 8 || mb_strlen($q) > 200) {
    echo json_encode(['duplicates' => []]);
    exit;
}

// Use a few meaningful words from the title for a loose match.
$words = preg_split('/\W+/u', mb_strtolower($q)) ?: [];
$words = array_values(array_filter($words, fn($w) => mb_strlen($w) >= 4));
$words = array_slice($words, 0, 4);
if (!$words) {
    echo json_encode(['duplicates' => []]);
    exit;
}

$conditions = [];
$params = [];
foreach ($words as $word) {
    $conditions[] = "LOWER(r.title) LIKE ?";
    $params[] = '%' . $word . '%';
}
$sql = "SELECT r.id, r.tracking_code, r.title, r.status
        FROM reports r
        WHERE r.status <> 'REJECTED' AND (" . implode(' OR ', $conditions) . ")";
if ($_SESSION['user_role'] === ROLE_STUDENT) {
    $sql .= " AND r.student_id = ?";
    $params[] = (int)$_SESSION['user_id'];
}
$sql .= " ORDER BY r.created_at DESC LIMIT 3";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$duplicates = [];
foreach ($rows as $row) {
    $duplicates[] = [
        'code'  => htmlspecialchars($row['tracking_code'], ENT_QUOTES, 'UTF-8'),
        'title' => htmlspecialchars(mb_substr($row['title'], 0, 70), ENT_QUOTES, 'UTF-8'),
        'url'   => htmlspecialchars(url('student/report-view.php?id=' . (int)$row['id']), ENT_QUOTES, 'UTF-8'),
    ];
}
echo json_encode(['duplicates' => $duplicates]);
