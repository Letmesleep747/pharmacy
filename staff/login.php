<?php
/**
 * Staff counter sign-in — only accounts with role = staff.
 */
require dirname(__DIR__) . '/includes/init.php';
redirect_signed_in_user();

$error = '';
if (is_post()) {
    $error = attempt_portal_login('staff');
}

$title = 'Staff sign in';
$heading = 'Staff counter sign in';
$intro = 'For branch staff only. Use a staff account such as staff@pharmacy.local.';
$registerHref = null;
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/login_form.php';
require dirname(__DIR__) . '/includes/footer.php';
