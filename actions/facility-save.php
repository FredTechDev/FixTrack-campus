<?php
/**
 * Administrator: create or update a campus facility.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(ROLE_ADMIN);
require_post();

$id            = (int)($_POST['id'] ?? 0);
$name          = trim($_POST['name'] ?? '');
$building_code = strtoupper(trim($_POST['building_code'] ?? ''));
$campus_zone   = trim($_POST['campus_zone'] ?? '');
$description   = trim($_POST['description'] ?? '');
$total_floors  = (int)($_POST['total_floors'] ?? 1);
$is_active     = isset($_POST['is_active']) ? 1 : 0;

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 150) $errors[] = 'Facility name must be 2-150 characters.';
if (!preg_match('/^[A-Z0-9\-]{2,30}$/', $building_code)) $errors[] = 'Building code must be 2-30 letters, numbers or hyphens.';
if (mb_strlen($campus_zone) < 2 || mb_strlen($campus_zone) > 100) $errors[] = 'Campus zone is required (max 100 characters).';
if ($total_floors < 1 || $total_floors > 200) $errors[] = 'Number of floors must be between 1 and 200.';

$dup = $pdo->prepare("SELECT id FROM facilities WHERE building_code = ?" . ($id > 0 ? " AND id <> ?" : "") . " LIMIT 1");
$dup->execute($id > 0 ? [$building_code, $id] : [$building_code]);
if ($dup->fetch()) $errors[] = 'That building code is already in use.';

if ($errors) {
    set_flash('error', implode(' ', $errors));
    redirect('admin/facilities.php' . ($id > 0 ? '?edit=' . $id : ''));
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE facilities SET name = ?, building_code = ?, campus_zone = ?, description = ?, total_floors = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $building_code, $campus_zone, $description ?: null, $total_floors, $is_active, $id]);
        set_flash('success', 'Facility updated.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO facilities (name, building_code, campus_zone, description, total_floors, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $building_code, $campus_zone, $description ?: null, $total_floors, $is_active]);
        set_flash('success', 'Facility added.');
    }
} catch (Throwable $ex) {
    set_flash('error', 'Could not save the facility.');
}
redirect('admin/facilities.php');
