<?php
/**
 * Promotion rules: active date window, percent/fixed discount, product links.
 */

/** True when the promotion flag is on and today is within start/end dates. */
function promotion_is_current(array $promo): bool
{
    $today = date('Y-m-d');
    return (int) $promo['is_active'] === 1
        && $promo['start_date'] <= $today
        && $promo['end_date'] >= $today;
}

/** Calculate discount for a line; fixed discounts never exceed the line total. */
function promotion_discount_amount(array $promo, float $unitPrice, int $qty = 1): float
{
    $line = $unitPrice * $qty;
    if ($promo['discount_type'] === 'percent') {
        $amount = $line * ((float) $promo['discount_value'] / 100);
    } else {
        $amount = (float) $promo['discount_value'] * $qty;
        if ($amount > $line) {
            $amount = $line;
        }
    }
    return round($amount, 2);
}

/**
 * Pick the currently active promotion that saves the customer the most money.
 * If a product is linked to several overlapping promotions, discounts are NOT stacked —
 * only this single best offer is applied at cart, checkout, and POS.
 */
function best_promotion_for_product(mysqli $db, int $productId): ?array
{
    $rows = db_all(
        $db,
        'SELECT p.*
         FROM promotions p
         INNER JOIN promotion_products pp ON pp.promotion_id = p.id
         WHERE pp.product_id = ?
           AND p.is_active = 1
           AND CURDATE() BETWEEN p.start_date AND p.end_date',
        'i',
        [$productId]
    );

    if (!$rows) {
        return null;
    }

    $priceRow = db_one($db, 'SELECT unit_price FROM products WHERE id = ?', 'i', [$productId]);
    $price = (float) ($priceRow['unit_price'] ?? 0);
    $best = null;
    $bestAmount = -1;
    foreach ($rows as $promo) {
        $amount = promotion_discount_amount($promo, $price, 1);
        if ($amount > $bestAmount) {
            $bestAmount = $amount;
            $best = $promo;
        }
    }
    return $best;
}

function active_promotions(mysqli $db): array
{
    return db_all(
        $db,
        'SELECT p.*,
                GROUP_CONCAT(pr.name ORDER BY pr.name SEPARATOR ", ") AS product_names
         FROM promotions p
         LEFT JOIN promotion_products pp ON pp.promotion_id = p.id
         LEFT JOIN products pr ON pr.id = pp.product_id
         WHERE p.is_active = 1
           AND CURDATE() BETWEEN p.start_date AND p.end_date
         GROUP BY p.id
         ORDER BY p.end_date ASC'
    );
}

function apply_sale_pricing(mysqli $db, int $productId, float $unitPrice, int $qty): array
{
    $promo = best_promotion_for_product($db, $productId);
    $discount = $promo ? promotion_discount_amount($promo, $unitPrice, $qty) : 0.0;
    $lineTotal = round(($unitPrice * $qty) - $discount, 2);
    return [
        'promotion' => $promo,
        'discount' => $discount,
        'line_total' => $lineTotal,
    ];
}
