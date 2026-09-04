<?php
require dirname(__DIR__) . '/includes/init.php';

$to = (string) ($_GET['to'] ?? 'customer');
logout_user();
if ($to === 'staff') {
    header('Location: ' . url('staff/login.php'));
} elseif ($to === 'admin') {
    header('Location: ' . url('admin/login.php'));
} else {
    header('Location: ' . url('index.php'));
}
exit;
