<?php
require dirname(__DIR__) . '/includes/init.php';
logout_user();
redirect('staff/login.php');
