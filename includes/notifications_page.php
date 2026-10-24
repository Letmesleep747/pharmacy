<?php
/**
 * Shared notification controller.
 * Handles syncing, mark-as-read actions, and fetches data for portal views.
 * Does NOT output HTML.
 */
require_once __DIR__ . '/notifications.php';

$db = db();
$userId = (int) current_user()['id'];

sync_prescription_notifications($db, current_user());

// Relative paths only — redirect() already wraps url().
$redirectPath = match (current_portal()) {
    'admin' => 'admin/notifications.php',
    'staff' => 'staff/notifications.php',
    default => 'pages/notifications.php',
};

if (is_post() && ($_POST['action'] ?? '') === 'mark_all_read') {
    db_exec($db, 'UPDATE notifications SET is_read = 1 WHERE user_id = ?', 'i', [$userId])->close();
    flash_set('success', 'All notifications marked as read.');
    redirect($redirectPath);
}

if (is_post() && ($_POST['action'] ?? '') === 'mark_read') {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    if ($notificationId > 0) {
        db_exec(
            $db,
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
            'ii',
            [$notificationId, $userId]
        )->close();
        flash_set('success', 'Notification marked as read.');
    }
    redirect($redirectPath);
}

if (isset($_GET['read'])) {
    $nid = (int) $_GET['read'];
    if ($nid > 0) {
        db_exec(
            $db,
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
            'ii',
            [$nid, $userId]
        )->close();
        flash_set('success', 'Notification marked as read.');
    }
    redirect($redirectPath);
}

$notifications = db_all(
    $db,
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100',
    'i',
    [$userId]
);

$unread = unread_notification_count($db, $userId);
