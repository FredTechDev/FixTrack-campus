<?php
/**
 * Minimal bootstrap for form-action endpoints (no HTML output).
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$pdo = get_db();
