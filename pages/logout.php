<?php
/**
 * Customer sign-out. Staff/admin use /staff/logout.php and /admin/logout.php
 * so the matching session cookie is cleared. Old ?to= links still work.
 */
require dirname(__DIR__) . '/includes/init.php';

$to = (string) ($_GET['to'] ?? '');
if ($to === 'staff') {
    redirect('staff/logout.php');
}
if ($to === 'admin') {
    redirect('admin/logout.php');
}

logout_user();
redirect('index.php');
