<?php
/**
 * JSON API: multi-branch availability for one product.
 * Example (GET): /pharmacy/api/products/1/availability
 * Returns Available / Out of stock per branch from branch_stock.
 */
require dirname(__DIR__) . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

$id = get_int('product_id') ?: get_int('id');
$product = $id ? find_product(db(), $id) : null;
if (!$product) {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found.']);
    exit;
}

$branches = [];
foreach (product_availability(db(), $id) as $row) {
    $qty = (int) ($row['available'] ?? $row['quantity']);
    $branches[] = [
        'branch_id' => (int) $row['id'],
        'name' => $row['name'],
        'address' => $row['address'],
        'phone' => $row['phone'],
        'quantity' => $qty,
        'on_hand' => (int) $row['quantity'],
        'reserved' => (int) ($row['reserved_qty'] ?? 0),
        'status' => $qty > 0 ? 'Available' : 'Out of stock',
    ];
}

echo json_encode([
    'product_id' => (int) $product['id'],
    'name' => $product['name'],
    'sku' => $product['sku'],
    'unit_price' => (float) $product['unit_price'],
    'availability' => $branches,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
