<?php
/**
 * Online cart (session) and in-store POS state, plus transactional checkout.
 * OTC sales decrement stock immediately (status=completed).
 * Prescription online orders stay pending_approval until staff approve.
 */

/** Customer shopping cart: [product_id => quantity] stored in the session. */
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

/** True if any line in the cart/POS basket needs a prescription. */
function lines_require_prescription(mysqli $db, array $lines): bool
{
    foreach ($lines as $productId => $qty) {
        if ((int) $qty <= 0) {
            continue;
        }
        $row = db_one($db, 'SELECT requires_prescription FROM products WHERE id = ?', 'i', [(int) $productId]);
        if ($row && (int) $row['requires_prescription'] === 1) {
            return true;
        }
    }
    return false;
}

/**
 * Save an uploaded prescription (PDF/JPG/PNG, max 5 MB).
 * Files live under uploads/prescriptions/ (blocked from direct web listing).
 * @return string Relative path stored in orders.prescription_path
 */
function save_prescription_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please upload a clear photo or PDF of your prescription.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Prescription file must be 5 MB or smaller.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Prescription must be a PDF, JPG, or PNG file.');
    }

    $dir = dirname(__DIR__) . '/uploads/prescriptions';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create upload folder.');
    }

    $name = 'rx_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the prescription file.');
    }

    return 'uploads/prescriptions/' . $name;
}

/** Staff POS basket for the current counter session. */
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

/**
 * Complete an online or in-store sale in one DB transaction.
 *
 * @param array $lines Map of product_id => quantity
 * @param string $source 'online' or 'instore'
 * @param bool $deductStock False for pending Rx orders (stock taken on approval)
 * @param string $status Order status value
 * @param string|null $prescriptionPath Relative upload path when Rx is required
 * @return int New orders.id
 */
function complete_sale(
    mysqli $db,
    array $lines,
    int $branchId,
    string $source,
    ?int $userId,
    bool $deductStock = true,
    string $status = 'completed',
    ?string $prescriptionPath = null
): int {
    if ($lines === []) {
        throw new RuntimeException('The sale has no items.');
    }

    $allowedStatus = ['pending_approval', 'approved', 'rejected', 'completed'];
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'completed';
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
                'SELECT id, name, unit_price, is_active, requires_prescription FROM products WHERE id = ? FOR UPDATE',
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

            if ($deductStock) {
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
            }

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
                'INSERT INTO orders (user_id, branch_id, source, subtotal, discount_total, total, status, prescription_path)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)',
                'isdddss',
                [$branchId, $source, $subtotal, $discountTotal, $total, $status, $prescriptionPath]
            );
        } else {
            $stmt = db_exec(
                $db,
                'INSERT INTO orders (user_id, branch_id, source, subtotal, discount_total, total, status, prescription_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                'iisdddss',
                [$userId, $branchId, $source, $subtotal, $discountTotal, $total, $status, $prescriptionPath]
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

/**
 * Staff/admin approves a pending Rx order: deduct stock, mark approved.
 */
function approve_prescription_order(mysqli $db, int $orderId, int $reviewerId, string $note = ''): void
{
    $db->begin_transaction();
    try {
        $order = db_one($db, 'SELECT * FROM orders WHERE id = ? FOR UPDATE', 'i', [$orderId]);
        if (!$order || $order['status'] !== 'pending_approval') {
            throw new RuntimeException('That order is not waiting for approval.');
        }

        $items = db_all(
            $db,
            'SELECT oi.product_id, oi.quantity, p.name
             FROM order_items oi
             INNER JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?',
            'i',
            [$orderId]
        );

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $qty = (int) $item['quantity'];
            $stock = db_one(
                $db,
                'SELECT quantity FROM branch_stock WHERE branch_id = ? AND product_id = ? FOR UPDATE',
                'ii',
                [(int) $order['branch_id'], $productId]
            );
            $available = (int) ($stock['quantity'] ?? 0);
            if ($available < $qty) {
                throw new RuntimeException($item['name'] . ' no longer has enough stock at this branch.');
            }
            $stmt = db_exec(
                $db,
                'UPDATE branch_stock SET quantity = quantity - ? WHERE branch_id = ? AND product_id = ? AND quantity >= ?',
                'iiii',
                [$qty, (int) $order['branch_id'], $productId, $qty]
            );
            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException('Could not reserve stock for ' . $item['name'] . '.');
            }
            $stmt->close();
        }

        $note = trim($note);
        db_exec(
            $db,
            'UPDATE orders
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?
             WHERE id = ?',
            'sisi',
            ['approved', $reviewerId, $note !== '' ? $note : null, $orderId]
        )->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** Staff/admin rejects a pending Rx order (no stock change). */
function reject_prescription_order(mysqli $db, int $orderId, int $reviewerId, string $note): void
{
    $note = trim($note);
    if ($note === '') {
        throw new RuntimeException('Please enter a short reason for rejection.');
    }

    $order = db_one($db, 'SELECT id, status FROM orders WHERE id = ?', 'i', [$orderId]);
    if (!$order || $order['status'] !== 'pending_approval') {
        throw new RuntimeException('That order is not waiting for approval.');
    }

    db_exec(
        $db,
        'UPDATE orders
         SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?
         WHERE id = ?',
        'sisi',
        ['rejected', $reviewerId, $note, $orderId]
    )->close();
}

function order_status_label(string $status): string
{
    return match ($status) {
        'pending_approval' => 'Pending prescription approval',
        'approved' => 'Approved — ready for pickup',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
        default => $status,
    };
}
