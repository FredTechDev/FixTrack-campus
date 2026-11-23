<?php
/**
 * Administrator: create or update an issue category.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(ROLE_ADMIN);
require_post();

$id               = (int)($_POST['id'] ?? 0);
$name             = trim($_POST['name'] ?? '');
$slug             = strtolower(trim($_POST['slug'] ?? ''));
$description      = trim($_POST['description'] ?? '');
$icon             = trim($_POST['icon'] ?? 'wrench');
$default_priority = strtolower($_POST['default_priority'] ?? 'medium');
$sla_hours        = (int)($_POST['default_sla_hours'] ?? 48);
$is_active        = isset($_POST['is_active']) ? 1 : 0;

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Category name must be 2-100 characters.';
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) $errors[] = 'Slug must contain only lowercase letters, numbers and hyphens.';
if (!array_key_exists($default_priority, PRIORITIES)) $errors[] = 'Invalid default priority.';
if ($sla_hours < 1 || $sla_hours > 720) $errors[] = 'Default SLA must be between 1 and 720 hours.';
if (!preg_match('/^[a-z0-9-]{1,50}$/', $icon)) $errors[] = 'Invalid icon name.';

$dup = $pdo->prepare("SELECT id FROM categories WHERE slug = ?" . ($id > 0 ? " AND id <> ?" : "") . " LIMIT 1");
$dup->execute($id > 0 ? [$slug, $id] : [$slug]);
if ($dup->fetch()) $errors[] = 'That slug is already in use.';

if ($errors) {
    set_flash('error', implode(' ', $errors));
    redirect('admin/categories.php' . ($id > 0 ? '?edit=' . $id : ''));
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, icon = ?, default_priority = ?, default_sla_hours = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $description ?: null, $icon, $default_priority, $sla_hours, $is_active, $id]);
        set_flash('success', 'Category updated.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, icon, default_priority, default_sla_hours, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $description ?: null, $icon, $default_priority, $sla_hours, $is_active]);
        set_flash('success', 'Category added.');
    }
} catch (Throwable $ex) {
    set_flash('error', 'Could not save the category.');
}
redirect('admin/categories.php');
