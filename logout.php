<?php
require_once __DIR__ . '/includes/functions.php';

// Wipe all session data, expire the cookie, destroy the session, then start a fresh one for the flash message.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
}
session_destroy();

session_start();
session_regenerate_id(true);
set_flash('success', 'You have been logged out.');
redirect('index.php');
