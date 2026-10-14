<?php
/**
 * Staff queue: online orders waiting for prescription approval.
 */
require dirname(__DIR__) . '/includes/init.php';
require_pharmacy_ops();
require dirname(__DIR__) . '/includes/ops/approvals.php';
