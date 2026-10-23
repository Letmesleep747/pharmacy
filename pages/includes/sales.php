<?php

function cart(): array
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    return $_SESSION['cart'];
}

function cart_set(int $productId, int $qty): void
{
    $cart = cart();
    if ($qty <= 0) {
        unset($cart[$productId]);
    } else {
        $cart[$productId] = $qty;
    }
    $_SESSION['cart'] = $cart;
}

function cart_add(int $productId, int $qty): void
{
    $cart = cart();
    $cart[$productId] = ($cart[$productId] ?? 0) + $qty;
    $_SESSION['cart'] = $cart;
}

function cart_count(): int
{
    return array_sum(cart());
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

function pos_state(): array
{
    if (!isset($_SESSION['pos']) || !is_array($_SESSION['pos'])) {
        $_SESSION['pos'] = ['branch_id' => 0, 'lines' => []];
    }
    return $_SESSION['pos'];
}

function pos_set_branch(int $branchId): void
{
    $pos = pos_state();
    $pos['branch_id'] = $branchId;
    $_SESSION['pos'] = $pos;
}

function pos_add_line(int $productId, int $qty): void
{
    $pos = pos_state();
    $pos['lines'][$productId] = ($pos['lines'][$productId] ?? 0) + $qty;
    $_SESSION['pos'] = $pos;
}

function pos_set_line(int $productId, int $qty): void
{
    $pos = pos_state();
    if ($qty <= 0) {
        unset($pos['lines'][$productId]);
    } else {
        $pos['lines'][$productId] = $qty;
    }
    $_SESSION['pos'] = $pos;
}

function pos_clear(): void
{
    $_SESSION['pos'] = ['branch_id' => pos_state()['branch_id'] ?? 0, 'lines' => []];
}

function complete_sale(mysqli $db, array $lines, int $branchId, string $source, ?int $userId): int
{
    if ($lines === []) {
        throw new RuntimeException('The sale has no items.');
    }

    $db->begin_transaction();
    try {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $prepared = [];

        foreach ($lines as $productId => $qty) {
            $productId = (int) $productId;
            $qty = (int) $qty;
            if ($qty <= 0) {
                continue;
            }

            $product = db_one(
                $db,
                'SELECT id, name, unit_price, is_active FROM products WHERE id = ? FOR UPDATE',
                'i',
                [$productId]
            );
            if (!$product || (int) $product['is_active'] !== 1) {
                throw new RuntimeException('A product in this sale is unavailable.');
            }

            $stock = db_one(
                $db,
                'SELECT quantity FROM branch_stock WHERE branch_id = ? AND product_id = ? FOR UPDATE',
                'ii',
                [$branchId, $productId]
            );
            $available = (int) ($stock['quantity'] ?? 0);
            if ($available < $qty) {
                throw new RuntimeException($product['name'] . ' does not have enough stock at this branch.');
            }

            $stmt = db_exec(
                $db,
                'UPDATE branch_stock SET quantity = quantity - ? WHERE branch_id = ? AND product_id = ? AND quantity >= ?',
                'iiii',
                [$qty, $branchId, $productId, $qty]
            );
            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException('Could not reserve stock for ' . $product['name'] . '.');
            }
            $stmt->close();

            $unitPrice = (float) $product['unit_price'];
            $pricing = apply_sale_pricing($db, $productId, $unitPrice, $qty);
            $subtotal += $unitPrice * $qty;
            $discountTotal += $pricing['discount'];
            $prepared[] = [
                'product_id' => $productId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_amount' => $pricing['discount'],
                'line_total' => $pricing['line_total'],
            ];
        }

        if ($prepared === []) {
            throw new RuntimeException('The sale has no items.');
        }

        $total = round($subtotal - $discountTotal, 2);
        $subtotal = round($subtotal, 2);
        $discountTotal = round($discountTotal, 2);

        if ($userId === null) {
            $stmt = db_exec(
                $db,
                'INSERT INTO orders (user_id, branch_id, source, subtotal, discount_total, total) VALUES (NULL, ?, ?, ?, ?, ?)',
                'isddd',
                [$branchId, $source, $subtotal, $discountTotal, $total]
            );
        } else {
            $stmt = db_exec(
                $db,
                'INSERT INTO orders (user_id, branch_id, source, subtotal, discount_total, total) VALUES (?, ?, ?, ?, ?, ?)',
                'iisddd',
                [$userId, $branchId, $source, $subtotal, $discountTotal, $total]
            );
        }
        $orderId = (int) $stmt->insert_id;
        $stmt->close();

        foreach ($prepared as $item) {
            $itemStmt = db_exec(
                $db,
                'INSERT INTO order_items (order_id, product_id, quantity, unit_price, discount_amount, line_total)
                 VALUES (?, ?, ?, ?, ?, ?)',
                'iiiddd',
                [
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['discount_amount'],
                    $item['line_total'],
                ]
            );
            $itemStmt->close();
        }

        $db->commit();
        return $orderId;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
