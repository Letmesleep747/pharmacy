<?php
/**
 * Secure download of a prescription file (staff/admin or owning customer only).
 * Files are not served as static public URLs.
 */
require dirname(__DIR__) . '/includes/init.php';

$db = db();
$id = get_int('id');
$order = $id ? db_one($db, 'SELECT id, user_id, prescription_path, status FROM orders WHERE id = ?', 'i', [$id]) : null;

if (!$order || empty($order['prescription_path'])) {
    http_response_code(404);
    echo 'Prescription not found.';
    exit;
}

$user = current_user();
if (!$user) {
    flash_set('error', 'Please sign in.');
    redirect('staff/login.php');
}

$role = $user['role'] ?? '';
$isStaff = $role === 'staff' || $role === 'admin';
$isOwner = (int) ($order['user_id'] ?? 0) === (int) $user['id'];
if (!$isStaff && !$isOwner) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$relative = str_replace('\\', '/', (string) $order['prescription_path']);
if (!preg_match('#^uploads/prescriptions/[A-Za-z0-9._-]+$#', $relative)) {
    http_response_code(400);
    echo 'Invalid file path.';
    exit;
}

$full = dirname(__DIR__) . '/' . $relative;
if (!is_file($full)) {
    http_response_code(404);
    echo 'File missing on server.';
    exit;
}

$mime = mime_content_type($full) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($full));
header('Content-Disposition: inline; filename="' . basename($full) . '"');
header('X-Content-Type-Options: nosniff');
readfile($full);
exit;
