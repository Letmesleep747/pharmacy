<?php
/**
 * Catalogue queries: products, categories, branches, and multi-branch stock.
 */

function product_search_sql(): string
{
    return 'SELECT p.*, c.name AS category_name
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1';
}

/** Search active products by name, SKU or description (prepared LIKE). */
function find_products(mysqli $db, string $q = '', int $categoryId = 0): array
{
    $sql = product_search_sql();
    $types = '';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)';
        $like = '%' . $q . '%';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($categoryId > 0) {
        $sql .= ' AND p.category_id = ?';
        $types .= 'i';
        $params[] = $categoryId;
    }
    $sql .= ' ORDER BY p.name ASC';
    return db_all($db, $sql, $types, $params);
}

function find_product(mysqli $db, int $id): ?array
{
    return db_one(
        $db,
        product_search_sql() . ' AND p.id = ? LIMIT 1',
        'i',
        [$id]
    );
}

/**
 * Stock at every active branch for one medication.
 * Available = on-hand quantity minus reserved (held) pickup orders.
 */
function product_availability(mysqli $db, int $productId): array
{
    return db_all(
        $db,
        'SELECT b.id, b.name, b.address, b.phone, b.opening_hours,
                COALESCE(s.quantity, 0) AS quantity,
                COALESCE(s.reserved_qty, 0) AS reserved_qty,
                GREATEST(0, CAST(COALESCE(s.quantity, 0) AS SIGNED) - CAST(COALESCE(s.reserved_qty, 0) AS SIGNED)) AS available
         FROM branches b
         LEFT JOIN branch_stock s ON s.branch_id = b.id AND s.product_id = ?
         WHERE b.is_active = 1
         ORDER BY b.name',
        'i',
        [$productId]
    );
}

function all_categories(mysqli $db): array
{
    return db_all($db, 'SELECT id, name FROM categories ORDER BY name');
}

function all_branches(mysqli $db, bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM branches';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY name';
    return db_all($db, $sql);
}

function all_products(mysqli $db, bool $activeOnly = false): array
{
    $sql = 'SELECT p.*, c.name AS category_name FROM products p INNER JOIN categories c ON c.id = p.category_id';
    if ($activeOnly) {
        $sql .= ' WHERE p.is_active = 1';
    }
    $sql .= ' ORDER BY p.name';
    return db_all($db, $sql);
}
