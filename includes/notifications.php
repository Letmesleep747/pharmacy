<?php
/**
 * Insert a notification only if the same (user, order, type) does not exist yet.
 * Works even when the DB is missing the uq_notification_event unique key,
 * so repeated syncs / polls can never create duplicate rows.
 */
function notification_insert_once(mysqli $db, int $userId, int $orderId, string $type, string $title, string $message): void
{
    db_exec(
        $db,
        'INSERT INTO notifications (user_id, order_id, type, title, message)
         SELECT ?, ?, ?, ?, ? FROM DUAL
         WHERE NOT EXISTS (
             SELECT 1 FROM notifications WHERE user_id = ? AND order_id = ? AND type = ?
         )',
        'iisssiis',
        [$userId, $orderId, $type, $title, $message, $userId, $orderId, $type]
    )->close();
}

/** Unread notification count for the signed-in (or given) user. */
function unread_notification_count(mysqli $db, ?int $userId = null): int
{
    $userId = $userId ?? (int) (current_user()['id'] ?? 0);
    if ($userId <= 0) {
        return 0;
    }
    $row = db_one(
        $db,
        'SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0',
        'i',
        [$userId]
    );
    return (int) ($row['n'] ?? 0);
}

/** Highest notification id for the user (read or unread) — used as the toast baseline. */
function latest_notification_id(mysqli $db, int $userId): int
{
    $row = db_one($db, 'SELECT MAX(id) AS m FROM notifications WHERE user_id = ?', 'i', [$userId]);
    return (int) ($row['m'] ?? 0);
}

/** Recent unread rows for badges / live toast (newest first). */
function recent_unread_notifications(mysqli $db, int $userId, int $limit = 8, int $afterId = 0): array
{
    if ($userId <= 0) {
        return [];
    }
    $limit = max(1, min(20, $limit));
    $sql = 'SELECT id, order_id, type, title, message, created_at,
                   TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds
            FROM notifications
            WHERE user_id = ? AND is_read = 0';
    $types = 'i';
    $params = [$userId];
    if ($afterId > 0) {
        $sql .= ' AND id > ?';
        $types .= 'i';
        $params[] = $afterId;
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
    return db_all($db, $sql, $types, $params);
}

/** Call only after the associated order change has succeeded. */
function notify_prescription_submitted(mysqli $db, int $orderId): void {
    $order = db_one($db, 'SELECT id, branch_id, status FROM orders WHERE id = ?', 'i', [$orderId]);
    if (!$order || $order['status'] !== 'pending_approval') return;
    $users = db_all($db, "SELECT id FROM users WHERE role = 'admin' OR (role = 'staff' AND branch_id = ?)", 'i', [(int)$order['branch_id']]);
    foreach ($users as $user) {
        notification_insert_once($db, (int)$user['id'],$orderId,'prescription_submitted','Prescription awaiting approval',"Order #$orderId has a prescription to review.");
    }
}
function notify_prescription_reviewed(mysqli $db, int $orderId): void {
    $order = db_one($db, 'SELECT id, user_id, status, review_note FROM orders WHERE id = ?', 'i', [$orderId]);
    if (!$order || !$order['user_id']) return;
    $approved = in_array($order['status'], ['approved','ready_for_pickup'], true);
    if (!$approved && $order['status'] !== 'rejected') return;
    $type = $approved ? 'prescription_approved' : 'prescription_rejected';
    $title = $approved ? 'Prescription approved' : 'Prescription not approved';
    $message = $approved ? "Order #$orderId was approved. Check your order for pickup details." : "Order #$orderId was rejected. If payment was captured, your refund is pending processing.";
    if (!$approved && trim((string)$order['review_note']) !== '') $message .= ' Reason: ' . $order['review_note'];
    notification_insert_once($db, (int)$order['user_id'],$orderId,$type,$title,$message);
}
/** Invoke only after the payment processor confirms a successful refund. */
function notify_refund_completed(mysqli $db, int $orderId): void {
    $order = db_one($db, 'SELECT user_id FROM orders WHERE id = ?', 'i', [$orderId]);
    if (!$order || !$order['user_id']) return;
    notification_insert_once($db, (int)$order['user_id'],$orderId,'refund_completed','Refund completed',"The refund for order #$orderId has been confirmed. Your bank may take additional time to show the funds.");
}

/** Synchronize existing order statuses into notifications, without approval functions. */
function sync_prescription_notifications(mysqli $db, array $user): void {
    $role = (string) ($user['role'] ?? '');
    $userId = (int) $user['id'];
    if ($role === 'admin' || $role === 'staff') {
        $sql = "SELECT id FROM orders WHERE status = 'pending_approval' AND prescription_path IS NOT NULL AND prescription_path <> ''";
        $params = []; $types = '';
        if ($role === 'staff') {
            $sql .= ' AND branch_id = ?'; $types = 'i';
            $params[] = (int) $user['branch_id'];
        }
        foreach (db_all($db, $sql, $types, $params) as $order) {
            notification_insert_once($db, $userId,(int)$order['id'],'prescription_submitted','Prescription awaiting approval','Order #' . $order['id'] . ' has a prescription awaiting review.');
        }
    } elseif ($role === 'customer') {
        $milestones = db_all($db, "SELECT id FROM orders WHERE user_id = ? AND source = 'online' AND status IN ('ready_for_pickup','completed')", 'i', [$userId]);
        foreach ($milestones as $milestone) notify_customer_order_status($db, (int)$milestone['id']);

        $orders = db_all($db, "SELECT id, status, review_note FROM orders WHERE user_id = ? AND prescription_path IS NOT NULL AND prescription_path <> '' AND status IN ('approved','ready_for_pickup','rejected')", 'i', [$userId]);
        foreach ($orders as $order) {
            $approved = $order['status'] !== 'rejected';
            $type = $approved ? 'prescription_approved' : 'prescription_rejected';
            $title = $approved ? 'Prescription approved' : 'Prescription not approved';
            $message = $approved ? 'Order #' . $order['id'] . ' was approved. Check your order for pickup details.' : 'Order #' . $order['id'] . ' was rejected. If payment was collected, contact the pharmacy about refund processing.';
            if (!$approved && trim((string)$order['review_note']) !== '') $message .= ' Reason: ' . $order['review_note'];
            notification_insert_once($db, $userId,(int)$order['id'],$type,$title,$message);
        }
    }
}


/** Create a customer notification for an online order's current pickup milestone. */
function notify_customer_order_status(mysqli $db, int $orderId): void {
    $order = db_one($db, 'SELECT id, user_id, source, status FROM orders WHERE id = ?', 'i', [$orderId]);
    if (!$order || !$order['user_id'] || $order['source'] !== 'online') return;
    $events = [
        'ready_for_pickup' => ['order_ready_for_pickup', 'Order ready for pickup', "Your order #$orderId is ready for pickup. Please collect it at your selected pharmacy branch."],
        'completed' => ['order_completed', 'Order completed', "Your order #$orderId has been marked as collected and completed. Thank you for shopping with us!"],
    ];
    if (!isset($events[$order['status']])) return;
    [$type, $title, $message] = $events[$order['status']];
    notification_insert_once($db, (int)$order['user_id'], $orderId, $type, $title, $message);
}