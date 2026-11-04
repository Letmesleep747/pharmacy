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

/**
 * For a cart, show each active branch with per-item available stock.
 * Used at checkout so the customer can pick a branch that can fulfil the order.
 *
 * @param array<int,int> $lines product_id => qty
 * @return list<array{id:int,name:string,address:string,can_fulfil:bool,items:list<array{product_id:int,name:string,needed:int,available:int,ok:bool}>}>
 */
function cart_branch_availability(mysqli $db, array $lines): array
{
    $needed = [];
    foreach ($lines as $productId => $qty) {
        $productId = (int) $productId;
        $qty = (int) $qty;
        if ($productId > 0 && $qty > 0) {
            $needed[$productId] = ($needed[$productId] ?? 0) + $qty;
        }
    }
    if ($needed === []) {
        return [];
    }

    $productIds = array_keys($needed);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));

    $products = db_all(
        $db,
        "SELECT id, name FROM products WHERE id IN ($placeholders) AND is_active = 1",
        $types,
        $productIds
    );
    $names = [];
    foreach ($products as $product) {
        $names[(int) $product['id']] = (string) $product['name'];
    }
    // Drop inactive / missing products from the check.
    $needed = array_filter(
        $needed,
        static fn (int $qty, int $id): bool => isset($names[$id]),
        ARRAY_FILTER_USE_BOTH
    );
    if ($needed === []) {
        return [];
    }

    $productIds = array_keys($needed);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));

    $stockRows = db_all(
        $db,
        "SELECT s.branch_id, s.product_id,
                GREATEST(0, CAST(s.quantity AS SIGNED) - CAST(s.reserved_qty AS SIGNED)) AS available
         FROM branch_stock s
         WHERE s.product_id IN ($placeholders)",
        $types,
        $productIds
    );
    $stockMap = [];
    foreach ($stockRows as $row) {
        $stockMap[(int) $row['branch_id']][(int) $row['product_id']] = (int) $row['available'];
    }

    $branches = all_branches($db, true);
    $result = [];
    foreach ($branches as $branch) {
        $branchId = (int) $branch['id'];
        $items = [];
        $canFulfil = true;
        foreach ($needed as $productId => $qty) {
            $available = (int) ($stockMap[$branchId][$productId] ?? 0);
            $ok = $available >= $qty;
            if (!$ok) {
                $canFulfil = false;
            }
            $items[] = [
                'product_id' => $productId,
                'name' => $names[$productId],
                'needed' => $qty,
                'available' => $available,
                'ok' => $ok,
            ];
        }
        $result[] = [
            'id' => $branchId,
            'name' => (string) $branch['name'],
            'address' => (string) $branch['address'],
            'can_fulfil' => $canFulfil,
            'items' => $items,
        ];
    }

    // Branches that can fulfil the whole cart first.
    usort($result, static function (array $a, array $b): int {
        if ($a['can_fulfil'] === $b['can_fulfil']) {
            return strcmp($a['name'], $b['name']);
        }
        return $a['can_fulfil'] ? -1 : 1;
    });

    return $result;
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
