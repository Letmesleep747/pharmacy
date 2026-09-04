<?php
require dirname(__DIR__) . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

$db = db();
$q = trim((string) ($_GET['q'] ?? ''));
$products = find_products($db, $q);
$out = [];
foreach ($products as $product) {
    $promo = best_promotion_for_product($db, (int) $product['id']);
    $out[] = [
        'id' => (int) $product['id'],
        'name' => $product['name'],
        'sku' => $product['sku'],
        'category' => $product['category_name'],
        'unit_price' => (float) $product['unit_price'],
        'promotion' => $promo ? $promo['name'] : null,
    ];
}

echo json_encode([
    'count' => count($out),
    'query' => $q,
    'products' => $out,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
