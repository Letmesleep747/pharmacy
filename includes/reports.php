<?php
/**
 * Analytics queries used by the admin dashboard and CSV report downloads.
 * All figures come from orders, order_items and branch_stock — not hardcoded.
 */

/** Default date range for charts/reports (last 60 days), with simple validation. */
function report_dates(): array
{
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-60 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from)) {
        $from = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to)) {
        $to = date('Y-m-d');
    }
    return [$from, $to];
}

function sales_summary(mysqli $db, string $from, string $to, int $branchId = 0): array
{
    // Exclude pending/rejected Rx orders from revenue until approved
    $sql = 'SELECT COUNT(*) AS transactions, COALESCE(SUM(total),0) AS revenue, COALESCE(SUM(discount_total),0) AS discounts
            FROM orders WHERE DATE(created_at) BETWEEN ? AND ?
              AND status = \'completed\'';
    $types = 'ss';
    $params = [$from, $to];
    if ($branchId > 0) {
        $sql .= ' AND branch_id = ?';
        $types .= 'i';
        $params[] = $branchId;
    }
    return db_one($db, $sql, $types, $params) ?? ['transactions' => 0, 'revenue' => 0, 'discounts' => 0];
}

function sales_by_day(mysqli $db, string $from, string $to, int $branchId = 0): array
{
    $sql = 'SELECT DATE(created_at) AS d, SUM(total) AS revenue, COUNT(*) AS n
            FROM orders WHERE DATE(created_at) BETWEEN ? AND ?
              AND status = \'completed\'
            ' . ($branchId > 0 ? ' AND branch_id = ?' : '') . '
            GROUP BY DATE(created_at) ORDER BY d';
    $types = $branchId > 0 ? 'ssi' : 'ss';
    $params = $branchId > 0 ? [$from, $to, $branchId] : [$from, $to];
    return db_all($db, $sql, $types, $params);
}

function sales_by_branch(mysqli $db, string $from, string $to): array
{
    return db_all(
        $db,
        'SELECT b.id, b.name, COUNT(o.id) AS transactions, COALESCE(SUM(o.total),0) AS revenue
         FROM branches b
         LEFT JOIN orders o ON o.branch_id = b.id
           AND DATE(o.created_at) BETWEEN ? AND ?
           AND o.status = \'completed\'
         GROUP BY b.id, b.name
         ORDER BY revenue DESC',
        'ss',
        [$from, $to]
    );
}

function best_sellers(mysqli $db, string $from, string $to, int $branchId = 0, int $limit = 10): array
{
    $sql = 'SELECT p.id, p.name, p.sku,
                   SUM(oi.quantity) AS qty_sold,
                   SUM(oi.line_total) AS revenue
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            INNER JOIN products p ON p.id = oi.product_id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
              AND o.status = \'completed\'';
    $types = 'ss';
    $params = [$from, $to];
    if ($branchId > 0) {
        $sql .= ' AND o.branch_id = ?';
        $types .= 'i';
        $params[] = $branchId;
    }
    $sql .= ' GROUP BY p.id, p.name, p.sku ORDER BY qty_sold DESC LIMIT ' . (int) $limit;
    return db_all($db, $sql, $types, $params);
}

function sales_rows(mysqli $db, string $from, string $to, int $branchId = 0): array
{
    $sql = 'SELECT o.id, o.created_at, o.source, o.status, o.total, o.discount_total, b.name AS branch_name, u.name AS customer_name
            FROM orders o
            INNER JOIN branches b ON b.id = o.branch_id
            LEFT JOIN users u ON u.id = o.user_id
            WHERE DATE(o.created_at) BETWEEN ? AND ?';
    $types = 'ss';
    $params = [$from, $to];
    if ($branchId > 0) {
        $sql .= ' AND o.branch_id = ?';
        $types .= 'i';
        $params[] = $branchId;
    }
    $sql .= ' ORDER BY o.created_at DESC';
    return db_all($db, $sql, $types, $params);
}

function inventory_rows(mysqli $db, int $branchId = 0): array
{
    $sql = 'SELECT b.id AS branch_id, p.id AS product_id, b.name AS branch_name, p.name AS product_name, p.sku,
                   s.quantity, s.reserved_qty,
                   GREATEST(0, CAST(s.quantity AS SIGNED) - CAST(s.reserved_qty AS SIGNED)) AS available,
                   s.reorder_level
            FROM branch_stock s
            INNER JOIN branches b ON b.id = s.branch_id
            INNER JOIN products p ON p.id = s.product_id';
    $types = '';
    $params = [];
    if ($branchId > 0) {
        $sql .= ' WHERE s.branch_id = ?';
        $types = 'i';
        $params = [$branchId];
    }
    $sql .= ' ORDER BY b.name, p.name';
    return db_all($db, $sql, $types, $params);
}

function low_stock_rows(mysqli $db, int $branchId = 0): array
{
    $sql = 'SELECT b.name AS branch_name, p.name AS product_name, p.sku, s.quantity, s.reserved_qty,
                   GREATEST(0, CAST(s.quantity AS SIGNED) - CAST(s.reserved_qty AS SIGNED)) AS available,
                   s.reorder_level
            FROM branch_stock s
            INNER JOIN branches b ON b.id = s.branch_id
            INNER JOIN products p ON p.id = s.product_id
            WHERE GREATEST(0, CAST(s.quantity AS SIGNED) - CAST(s.reserved_qty AS SIGNED)) <= s.reorder_level';
    $types = '';
    $params = [];
    if ($branchId > 0) {
        $sql .= ' AND s.branch_id = ?';
        $types = 'i';
        $params = [$branchId];
    }
    $sql .= ' ORDER BY available ASC, p.name';
    return db_all($db, $sql, $types, $params);
}

function csv_download(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
