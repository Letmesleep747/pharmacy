<?php
require dirname(__DIR__) . '/includes/init.php';
if (is_admin()) {
    flash_set('info', 'In-store POS is on the staff site.');
    redirect('admin/index.php');
}
redirect('staff/login.php');
