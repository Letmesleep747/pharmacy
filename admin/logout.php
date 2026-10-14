<?php
require dirname(__DIR__) . '/includes/init.php';
logout_user();
redirect('admin/login.php');
