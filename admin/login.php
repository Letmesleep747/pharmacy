<?php
/**
 * Administrator sign-in — only accounts with role = admin.
 */
require dirname(__DIR__) . '/includes/init.php';
$error = '';
if (is_post()) {
    $error = attempt_portal_login('admin');
}

$title = 'Admin sign in';
$heading = 'Administrator sign in';
$intro = 'For administrators only. Use an admin account such as admin@pharmacy.local.';
$registerHref = null;
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/login_form.php';
require dirname(__DIR__) . '/includes/footer.php';
