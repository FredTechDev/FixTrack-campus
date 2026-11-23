<?php
/**
 * FixTrack Campus - Session termination.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logout_user();
set_flash('success', 'You have been signed out securely.');
redirect('login.php');
