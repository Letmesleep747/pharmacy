<?php
require dirname(__DIR__) . '/includes/init.php';
redirect_signed_in_user();

$error = '';
if (is_post()) {
    $error = attempt_portal_login('customer');
}

$title = 'Customer sign in';
$heading = 'Customer sign in';
$intro = 'This site is for customers only. Staff and administrators have separate sites.';
$registerHref = url('pages/register.php');
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/login_form.php';
require dirname(__DIR__) . '/includes/footer.php';
