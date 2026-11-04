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
require_once __DIR__ . '/notifications.php';

// One session cookie per site (customer / staff / admin) so they can stay signed in independently.
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name(portal_session_name());
session_set_cookie_params([
    'lifetime' => 0,
    'path' => portal_session_path(),
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

csrf_verify();

try {
    expire_overdue_orders(db());
} catch (Throwable $e) {
    // Ignore when an older database is missing reservation columns.
}
