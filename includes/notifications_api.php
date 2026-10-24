<?php
/**
 * Shared JSON handler for notification badge + live toasts.
 * Included from portal-scoped endpoints so session cookies are sent.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sign in required.']);
    exit;
}

$db = db();
$userId = (int) $user['id'];
sync_prescription_notifications($db, $user);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'mark_all_read') {
        db_exec($db, 'UPDATE notifications SET is_read = 1 WHERE user_id = ?', 'i', [$userId])->close();
    } elseif ($action === 'mark_read') {
        $id = (int) ($_POST['notification_id'] ?? 0);
        if ($id > 0) {
            db_exec(
                $db,
                'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
                'ii',
                [$id, $userId]
            )->close();
        }
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
        exit;
    }
}

$afterId = (int) ($_GET['after_id'] ?? 0);
$unread = unread_notification_count($db, $userId);
$items = recent_unread_notifications($db, $userId, $afterId > 0 ? 8 : 5, $afterId);

echo json_encode([
    'ok' => true,
    'unread' => $unread,
    'items' => array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'order_id' => (int) ($row['order_id'] ?? 0),
            'type' => (string) $row['type'],
            'title' => (string) $row['title'],
            'message' => (string) $row['message'],
            'created_at' => (string) $row['created_at'],
        ];
    }, $items),
    'list_url' => url(match (current_portal()) {
        'admin' => 'admin/notifications.php',
        'staff' => 'staff/notifications.php',
        default => 'pages/notifications.php',
    }),
]);
