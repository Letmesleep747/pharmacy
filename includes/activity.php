<?php
/**
 * Staff/admin activity audit trail.
 * Writes never break the main action — failures are swallowed.
 */

function ensure_activity_logs_table(mysqli $db): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $db->query(
        "CREATE TABLE IF NOT EXISTS activity_logs (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          actor_id INT UNSIGNED NULL,
          actor_name VARCHAR(120) NOT NULL,
          actor_role ENUM('staff','admin','system') NOT NULL DEFAULT 'system',
          action VARCHAR(60) NOT NULL,
          entity_type VARCHAR(40) NULL,
          entity_id INT UNSIGNED NULL,
          branch_id INT UNSIGNED NULL,
          summary VARCHAR(255) NOT NULL,
          details TEXT NULL,
          ip_address VARCHAR(45) NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_activity_created (created_at),
          KEY idx_activity_actor (actor_id),
          KEY idx_activity_action (action),
          KEY idx_activity_entity (entity_type, entity_id),
          KEY idx_activity_branch (branch_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ready = true;
}

/**
 * Record one staff/admin action.
 *
 * @param array{
 *   entity_type?: ?string,
 *   entity_id?: ?int,
 *   branch_id?: ?int,
 *   details?: mixed,
 *   actor?: ?array
 * } $opts
 */
function log_activity(mysqli $db, string $action, string $summary, array $opts = []): void
{
    try {
        ensure_activity_logs_table($db);

        $actor = $opts['actor'] ?? current_user();
        $actorId = isset($actor['id']) ? (int) $actor['id'] : null;
        $actorName = trim((string) ($actor['name'] ?? 'System'));
        if ($actorName === '') {
            $actorName = 'System';
        }
        $role = (string) ($actor['role'] ?? 'system');
        if ($role !== 'staff' && $role !== 'admin') {
            $role = 'system';
        }

        $details = $opts['details'] ?? null;
        if ($details !== null && !is_string($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strlen($ip) > 45) {
            $ip = substr($ip, 0, 45);
        }

        $entityType = isset($opts['entity_type']) ? (string) $opts['entity_type'] : null;
        $entityId = isset($opts['entity_id']) ? (int) $opts['entity_id'] : null;
        if ($entityId !== null && $entityId <= 0) {
            $entityId = null;
        }
        $branchId = isset($opts['branch_id']) ? (int) $opts['branch_id'] : null;
        if ($branchId !== null && $branchId <= 0) {
            $branchId = null;
        }

        db_exec(
            $db,
            'INSERT INTO activity_logs
             (actor_id, actor_name, actor_role, action, entity_type, entity_id, branch_id, summary, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'issssiisss',
            [
                $actorId,
                $actorName,
                $role,
                substr($action, 0, 60),
                $entityType !== null && $entityType !== '' ? substr($entityType, 0, 40) : null,
                $entityId,
                $branchId,
                substr($summary, 0, 255),
                $details !== null && $details !== '' ? (string) $details : null,
                is_string($ip) && $ip !== '' ? $ip : null,
            ]
        )->close();
    } catch (Throwable $e) {
        // Audit must not block POS, approvals, or stock updates.
    }
}

/** Human labels for the admin filter dropdown. */
function activity_action_labels(): array
{
    return [
        'login' => 'Sign in',
        'rx_approved' => 'Rx approved',
        'rx_rejected' => 'Rx rejected',
        'payment_approved' => 'Payment approved',
        'payment_rejected' => 'Payment rejected',
        'pickup_collected' => 'Pickup collected',
        'pos_sale' => 'POS sale',
        'stock_updated' => 'Stock updated',
        'product_created' => 'Product created',
        'product_updated' => 'Product updated',
        'product_toggled' => 'Product shown/hidden',
        'branch_created' => 'Branch created',
        'branch_updated' => 'Branch updated',
        'staff_created' => 'Staff created',
        'staff_updated' => 'Staff updated',
        'staff_password_reset' => 'Staff password reset',
        'promotion_created' => 'Promotion created',
        'promotion_updated' => 'Promotion updated',
        'order_cancelled' => 'Order cancelled',
        'password_changed' => 'Password changed',
    ];
}
