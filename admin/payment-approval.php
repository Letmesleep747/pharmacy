<?php
/** Alias — nav and old links use payment-approvals.php */
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();
redirect('admin/payment-approvals.php');
