<?php
/**
 * JSON cart updates for live quantity changes on the cart page.
 * POST: action=update|clear, qty[productId]=n, _csrf=...
 */
require dirname(__DIR__) . '/includes/init.php';
require_storefront();

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$db = db();
$action = (string) ($_POST['action'] ?? 'update');

if ($action === 'clear') {
    cart_clear();
} else {
    $qtys = $_POST['qty'] ?? [];
    if (!is_array($qtys)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid quantities.']);
        exit;
    }
    foreach ($qtys as $pid => $qty) {
        cart_set((int) $pid, max(0, (int) $qty));
    }
}

$summary = cart_summary($db, cart());
$items = [];
foreach ($summary['items'] as $item) {
    $promo = best_promotion_for_product($db, (int) $item['product_id']);
    $items[] = [
        'product_id' => (int) $item['product_id'],
        'name' => (string) $item['name'],
        'quantity' => (int) $item['quantity'],
        'unit_price' => money((float) $item['unit_price']),
        'unit_price_raw' => (float) $item['unit_price'],
        'discount' => money((float) $item['discount_amount']),
        'discount_raw' => (float) $item['discount_amount'],
        'line_total' => money((float) $item['line_total']),
        'line_total_raw' => (float) $item['line_total'],
        'promo' => $promo ? (string) $promo['name'] : null,
    ];
}

echo json_encode([
    'ok' => true,
    'count' => cart_count(),
    'empty' => cart_count() === 0,
    'subtotal' => money((float) $summary['subtotal']),
    'discount' => money((float) $summary['discount']),
    'total' => money((float) $summary['total']),
    'items' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
