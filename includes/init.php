<?php
/**
 * Bootstrap for every page and API endpoint.
 * Loads config + helpers, starts a secure session, then verifies CSRF on POST.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/portal.php';
require_once __DIR__ . '/promotions.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/sales.php';
require_once __DIR__ . '/reports.php';

// Session cookie: HttpOnly + SameSite reduces XSS / CSRF risk; Secure when HTTPS is on.
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => app_base() === '' ? '/' : app_base() . '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

csrf_verify();
