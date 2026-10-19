<?php
/**
 * Shared notification controller.
 * Handles access, syncing, mark-as-read actions, and fetches data.
 * Does NOT output any HTML.
 */
require_once dirname(__DIR__) . '/includes/notifications.php';

$db = db();
$userId = (int) current_user()['id'];

// Sync any pending prescriptions into notifications
sync_prescription_notifications($db, current_user());

// Determine the correct redirect URL based on the current folder path
$redirectUrl = url('pages/notifications.php'); // Default to customer
if (str_contains($_SERVER['PHP_SELF'], '/admin/')) {
    $redirectUrl = url('admin/notifications.php');
} elseif (str_contains($_SERVER['PHP_SELF'], '/staff/')) {
    $redirectUrl = url('staff/notifications.php');
}

// Handle Mark All As Read
if (is_post() && ($_POST['action'] ?? '') === 'mark_all_read') {
    db_exec($db, 'UPDATE notifications SET is_read = 1 WHERE user_id = ?', 'i', [$userId])->close();
    flash_set('success', 'All notifications marked as read.');
    redirect($redirectUrl);
}

// Handle Mark Single As Read (POST)
if (is_post() && ($_POST['action'] ?? '') === 'mark_read') {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    if ($notificationId > 0) {
        db_exec($db, 'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', 'ii', [$notificationId, $userId])->close();
    }
    redirect($redirectUrl);
}

// Handle Mark Single As Read (GET - legacy link support)
if (isset($_GET['read'])) {
    $nid = (int) $_GET['read'];
    db_exec($db, 'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', 'ii', [$nid, $userId])->close();
    redirect($redirectUrl);
}

// Fetch notifications for the views
$notifications = db_all($db, 'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100', 'i', [$userId]);

// Count unread notifications
$unreadRow = db_one($db, 'SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [$userId]);
$unread = (int) $unreadRow['n'];