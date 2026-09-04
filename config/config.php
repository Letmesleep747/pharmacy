<?php
/**
 * Application configuration.
 * For deployment, copy values to match the host MySQL account.
 * Prefer a limited-privilege user (see sql/schema.sql) instead of root.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'pharmacy');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Wellspring Pharmacy');
define('SESSION_NAME', 'pharmacy_session');
