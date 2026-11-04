<?php
/**
 * Online cart (session) and in-store POS state, plus transactional checkout.
 * Online pickup: increases reserved_qty (quantity unchanged until collection).
 * In-store POS: deducts quantity immediately.
 * Prescription online orders stay pending_approval until staff approve (reservation already held).
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
 * Calculate cart/POS lines using the same pricing rules as complete_sale().
 * Returns itemised rows plus subtotal, discount and final total.
 * This is the SINGLE source of truth for on-screen cart totals.
 */
function cart_summary(mysqli $db, array $lines): array
{
    $items = [];
    $subtotal = 0.0;
    $discountTotal = 0.0;

    foreach ($lines as $productId => $qty) {
        $productId = (int) $productId;
        $qty = (int) $qty;

        if ($qty <= 0) {
            continue;
        }

        $product = db_one(
            $db,
            'SELECT id, name, unit_price, is_active FROM products WHERE id = ?',
            'i',
            [$productId]
        );

        if (!$product || (int) $product['is_active'] !== 1) {
            continue;
        }

        $unitPrice = (float) $product['unit_price'];
        $pricing = apply_sale_pricing($db, $productId, $unitPrice, $qty);

        $lineDiscount = (float) $pricing['discount'];
        $lineTotal    = (float) $pricing['line_total'];

        $subtotal      += $unitPrice * $qty;
        $discountTotal += $lineDiscount;

        $items[] = [
            'product_id'      => $productId,
            'name'            => $product['name'],
            'quantity'        => $qty,
            'unit_price'      => $unitPrice,
            'discount_amount' => $lineDiscount,
            'line_total'      => $lineTotal,
        ];
    }

    $subtotal      = round($subtotal, 2);
    $discountTotal = round($discountTotal, 2);

    return [
        'items'    => $items,
        'subtotal' => $subtotal,
        'discount' => $discountTotal,
        'total'    => round($subtotal - $discountTotal, 2),
    ];
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

/** Save customer-uploaded proof of payment (JPG/PNG/PDF, max 5 MB). */
function save_payment_receipt_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please upload your payment receipt or transaction screenshot.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Payment receipt must be 5 MB or smaller.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Payment receipt must be a PDF, JPG, PNG, or WEBP file.');
    }
    $dir = dirname(__DIR__) . '/uploads/payment_receipts';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create payment receipt folder.');
    }
    $name = 'payment_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the payment receipt.');
    }
    return 'uploads/payment_receipts/' . $name;
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
 * Online: increases reserved_qty and sets pickup_by (quantity unchanged until collection).
 * In-store POS: deducts quantity immediately (walk-in, no reservation).
 *
 * @param array $lines Map of product_id => quantity
 * @param string $source 'online' or 'instore'
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

    $allowedStatus = ['pending_approval', 'approved', 'rejected', 'ready_for_pickup', 'completed', 'expired'];
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'completed';
    }

    $isOnline = $source === 'online';
    $pickupBy = $isOnline ? date('Y-m-d H:i:s', time() + pickup_window_seconds()) : null;

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
                'SELECT quantity, reserved_qty FROM branch_stock WHERE branch_id = ? AND product_id = ? FOR UPDATE',
                'ii',
                [$branchId, $productId]
            );
            $onHand = (int) ($stock['quantity'] ?? 0);
            $reserved = (int) ($stock['reserved_qty'] ?? 0);
            $available = max(0, $onHand - $reserved);
            if ($available < $qty) {
                throw new RuntimeException($product['name'] . ' does not have enough stock at this branch.');
            }

            if ($isOnline) {
                $stmt = db_exec(
                    $db,
                    'UPDATE branch_stock
                     SET reserved_qty = reserved_qty + ?
                     WHERE branch_id = ? AND product_id = ?
                       AND quantity >= reserved_qty + ?',
                    'iiii',
                    [$qty, $branchId, $productId, $qty]
                );
                if ($stmt->affected_rows !== 1) {
                    throw new RuntimeException('Could not reserve stock for ' . $product['name'] . '.');
                }
                $stmt->close();
            } elseif ($deductStock) {
                $stmt = db_exec(
                    $db,
                    'UPDATE branch_stock
                     SET quantity = quantity - ?
                     WHERE branch_id = ? AND product_id = ?
                       AND quantity - reserved_qty >= ?',
                    'iiii',
                    [$qty, $branchId, $productId, $qty]
                );
                if ($stmt->affected_rows !== 1) {
                    throw new RuntimeException('Could not deduct stock for ' . $product['name'] . '.');
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
                'INSERT INTO orders (user_id, branch_id, source, subtotal, discount_total, total, status, pickup_by, prescription_path)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)',
                'isdddsss',
                [$branchId, $source, $subtotal, $discountTotal, $total, $status, $pickupBy, $prescriptionPath]
            );
        } else {
            $stmt = db_exec(
                $db,
                'INSERT INTO orders (user_id, branch_id, source, subtotal, discount_total, total, status, pickup_by, prescription_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iisdddsss',
                [$userId, $branchId, $source, $subtotal, $discountTotal, $total, $status, $pickupBy, $prescriptionPath]
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

function pickup_window_seconds(): int
{
    return 48 * 3600;
}

function release_order_reservation(mysqli $db, array $order): void
{
    $items = db_all(
        $db,
        'SELECT product_id, quantity FROM order_items WHERE order_id = ?',
        'i',
        [(int) $order['id']]
    );
    foreach ($items as $item) {
        db_exec(
            $db,
            'UPDATE branch_stock
             SET reserved_qty = GREATEST(0, CAST(reserved_qty AS SIGNED) - ?)
             WHERE branch_id = ? AND product_id = ?',
            'iii',
            [(int) $item['quantity'], (int) $order['branch_id'], (int) $item['product_id']]
        )->close();
    }
}

/** Expire overdue pickup/Rx holds and return reserved stock to available. */
function expire_overdue_orders(mysqli $db): void
{
    $due = db_all(
        $db,
        'SELECT id, branch_id, status FROM orders
         WHERE pickup_by IS NOT NULL
           AND pickup_by < NOW()
           AND status IN (\'pending_approval\', \'approved\', \'ready_for_pickup\')'
    );
    foreach ($due as $order) {
        $db->begin_transaction();
        try {
            $locked = db_one($db, 'SELECT * FROM orders WHERE id = ? FOR UPDATE', 'i', [(int) $order['id']]);
            if (
                !$locked
                || !in_array($locked['status'], ['pending_approval', 'approved', 'ready_for_pickup'], true)
            ) {
                $db->rollback();
                continue;
            }
            release_order_reservation($db, $locked);
            db_exec(
                $db,
                'UPDATE orders SET status = ?, review_note = COALESCE(review_note, ?) WHERE id = ?',
                'ssi',
                ['expired', 'Not collected by pickup deadline.', (int) $locked['id']]
            )->close();
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
        }
    }
}

/**
 * Staff/admin approves a pending Rx order: keep reservation, mark ready for pickup.
 */
function approve_prescription_order(mysqli $db, int $orderId, int $reviewerId, string $note = ''): void
{
    $db->begin_transaction();
    try {
        $order = db_one($db, 'SELECT * FROM orders WHERE id = ? FOR UPDATE', 'i', [$orderId]);
        if (!$order || $order['status'] !== 'pending_approval') {
            throw new RuntimeException('That order is not waiting for approval.');
        }

        $note = trim($note);
        $pickupBy = $order['pickup_by'] ?? date('Y-m-d H:i:s', time() + pickup_window_seconds());
        db_exec(
            $db,
            'UPDATE orders
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?, pickup_by = ?
             WHERE id = ?',
            'sissi',
            ['ready_for_pickup', $reviewerId, $note !== '' ? $note : null, $pickupBy, $orderId]
        )->close();

        $db->commit();
        log_activity($db, 'rx_approved', 'Approved prescription for order #' . $orderId, [
            'entity_type' => 'order',
            'entity_id' => $orderId,
            'branch_id' => (int) $order['branch_id'],
            'details' => ['note' => $note !== '' ? $note : null],
        ]);
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** Staff/admin rejects a pending Rx order and releases reserved stock. */
function reject_prescription_order(mysqli $db, int $orderId, int $reviewerId, string $note): void
{
    $note = trim($note);
    if ($note === '') {
        throw new RuntimeException('Please enter a short reason for rejection.');
    }

    $db->begin_transaction();
    try {
        $order = db_one($db, 'SELECT * FROM orders WHERE id = ? FOR UPDATE', 'i', [$orderId]);
        if (!$order || $order['status'] !== 'pending_approval') {
            throw new RuntimeException('That order is not waiting for approval.');
        }
        release_order_reservation($db, $order);
        db_exec(
            $db,
            'UPDATE orders
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?
             WHERE id = ?',
            'sisi',
            ['rejected', $reviewerId, $note, $orderId]
        )->close();
        $db->commit();
        log_activity($db, 'rx_rejected', 'Rejected prescription for order #' . $orderId, [
            'entity_type' => 'order',
            'entity_id' => $orderId,
            'branch_id' => (int) $order['branch_id'],
            'details' => ['note' => $note],
        ]);
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** Customer collected the order: convert reserved_qty into a quantity deduction. */
function complete_pickup_order(mysqli $db, int $orderId): void
{
    $db->begin_transaction();
    try {
        $order = db_one($db, 'SELECT * FROM orders WHERE id = ? FOR UPDATE', 'i', [$orderId]);
        if (!$order || !in_array($order['status'], ['ready_for_pickup', 'approved'], true)) {
            throw new RuntimeException('That order is not waiting for collection.');
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
            $qty = (int) $item['quantity'];
            $stmt = db_exec(
                $db,
                'UPDATE branch_stock
                 SET quantity = quantity - ?, reserved_qty = reserved_qty - ?
                 WHERE branch_id = ? AND product_id = ?
                   AND quantity >= ? AND reserved_qty >= ?',
                'iiiiii',
                [$qty, $qty, (int) $order['branch_id'], (int) $item['product_id'], $qty, $qty]
            );
            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException('Could not complete pickup for ' . $item['name'] . '.');
            }
            $stmt->close();
        }

        db_exec($db, 'UPDATE orders SET status = ? WHERE id = ?', 'si', ['completed', $orderId])->close();
        $db->commit();
        log_activity($db, 'pickup_collected', 'Marked order #' . $orderId . ' as collected', [
            'entity_type' => 'order',
            'entity_id' => $orderId,
            'branch_id' => (int) $order['branch_id'],
            'details' => ['items' => count($items)],
        ]);
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function order_status_label(string $status): string
{
    return match ($status) {
        'pending_approval' => 'Pending prescription approval',
        'approved' => 'Approved — ready for pickup',
        'ready_for_pickup' => 'Ready for pickup',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
        'expired' => 'Expired — not collected',
        default => $status,
    };
}

/** Ensure cancelled is allowed on older databases that still use the previous enum. */
function ensure_cancelled_order_status(mysqli $db): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    try {
        $db->query(
            "ALTER TABLE orders
             MODIFY COLUMN status ENUM(
               'pending_approval','approved','rejected','cancelled',
               'ready_for_pickup','completed','expired'
             ) NOT NULL DEFAULT 'completed'"
        );
    } catch (Throwable $e) {
        // Already applied or table missing — ignore.
    }
    $ready = true;
}

/**
 * Customer cancels an uncollected online order and releases reserved stock.
 */
function cancel_customer_order(mysqli $db, int $orderId, int $customerId): void
{
    ensure_cancelled_order_status($db);
    $db->begin_transaction();
    try {
        $order = db_one($db, 'SELECT * FROM orders WHERE id = ? FOR UPDATE', 'i', [$orderId]);
        if (!$order || (int) ($order['user_id'] ?? 0) !== $customerId) {
            throw new RuntimeException('Order not found.');
        }
        if ($order['source'] !== 'online') {
            throw new RuntimeException('Only online pickup orders can be cancelled here.');
        }
        $cancellable = ['pending_approval', 'approved', 'ready_for_pickup'];
        if (!in_array($order['status'], $cancellable, true)) {
            throw new RuntimeException('This order can no longer be cancelled.');
        }

        release_order_reservation($db, $order);
        db_exec(
            $db,
            "UPDATE orders
             SET status = 'cancelled',
                 review_note = ?,
                 reviewed_at = NOW(),
                 payment_status = CASE
                   WHEN payment_status = 'pending_review' THEN 'rejected'
                   ELSE payment_status
                 END
             WHERE id = ?",
            'si',
            ['Cancelled by customer.', $orderId]
        )->close();
        $db->commit();

        log_activity($db, 'order_cancelled', 'Customer cancelled order #' . $orderId, [
            'entity_type' => 'order',
            'entity_id' => $orderId,
            'branch_id' => (int) $order['branch_id'],
            'details' => ['previous_status' => $order['status']],
        ]);
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** Payment / Rx notes and reservation messages for order detail pages. */
function order_status_messages(array $order): array
{
    $status = (string) ($order['status'] ?? '');
    $messages = [];
    $reviewNote = trim((string) ($order['review_note'] ?? ''));
    $paymentNote = trim((string) ($order['payment_review_note'] ?? ''));
    $paymentStatus = (string) ($order['payment_status'] ?? '');

    if ($status === 'pending_approval') {
        $messages[] = ['type' => 'info', 'text' => 'Prescription is with the pharmacy team. Stock is reserved at the pickup branch.'];
    } elseif ($status === 'ready_for_pickup' || $status === 'approved') {
        $messages[] = ['type' => 'ok', 'text' => 'Order is ready for collection. Bring your order number when you pick up.'];
    } elseif ($status === 'expired') {
        $messages[] = [
            'type' => 'error',
            'text' => 'This order expired because it was not collected in time. Reserved stock has been released'
                . ($reviewNote !== '' ? ': ' . $reviewNote : '.'),
        ];
    } elseif ($status === 'rejected') {
        $messages[] = [
            'type' => 'error',
            'text' => 'This order was rejected'
                . ($reviewNote !== '' ? ': ' . $reviewNote : '.')
                . ' Reserved stock has been released.',
        ];
    } elseif ($status === 'cancelled') {
        $messages[] = [
            'type' => 'info',
            'text' => 'This order was cancelled. Reserved stock has been released'
                . ($reviewNote !== '' ? ' (' . $reviewNote . ')' : '.'),
        ];
    } elseif ($status === 'completed') {
        $messages[] = ['type' => 'ok', 'text' => 'Order collected and completed.'];
    }

    if ($paymentStatus === 'pending_review') {
        $messages[] = ['type' => 'info', 'text' => 'Payment receipt is waiting for staff review.'];
    } elseif ($paymentStatus === 'rejected' || $paymentStatus === 'refunded') {
        $messages[] = [
            'type' => 'error',
            'text' => 'Payment was ' . $paymentStatus
                . ($paymentNote !== '' ? ': ' . $paymentNote : '.'),
        ];
    } elseif ($paymentStatus === 'approved' && $paymentNote !== '') {
        $messages[] = ['type' => 'ok', 'text' => 'Payment note: ' . $paymentNote];
    }

    return $messages;
}