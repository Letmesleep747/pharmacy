<?php
$db = db();
$id = get_int('id');
$error = '';

$order = $id
    ? db_one(
        $db,
        'SELECT o.*, b.name AS branch_name, b.address, u.name AS customer_name
         FROM orders o
         INNER JOIN branches b ON b.id = o.branch_id
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ?',
        'i',
        [$id]
    )
    : null;

if (!$order || $order['payment_status'] !== 'pending_review') {
    flash_set('error', 'That payment is not waiting for review.');
    redirect(ops_path('payment-approvals.php'));
}

require_ops_order_access($order, 'payment-approvals.php');

if (is_post()) {
    $action   = (string) ($_POST['action'] ?? '');
    $note     = trim((string) ($_POST['review_note'] ?? ''));
    $reviewer = (int) current_user()['id'];

    try {
        if ($action === 'approve') {
            $stmt = db_exec(
                $db,
                "UPDATE orders
                 SET payment_status = 'approved',
                     payment_reviewed_by = ?,
                     payment_reviewed_at = NOW(),
                     payment_review_note = ?
                 WHERE id = ? AND payment_status = 'pending_review'",
                'isi',
                [$reviewer, $note !== '' ? $note : null, $id]
            );

            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new RuntimeException('This payment has already been reviewed.');
            }
            $stmt->close();

            $userId = (int) ($order['user_id'] ?? 0);
            if ($userId > 0) {
                $msg = $order['status'] === 'pending_approval'
                    ? 'Your payment receipt was approved by admin/staff. Your prescription is still awaiting branch review.'
                    : 'Your payment receipt was approved by admin/staff. Your order is being processed at ' . $order['branch_name'] . '.';
                try {
                    db_exec(
                        $db,
                        'INSERT INTO notifications (user_id, order_id, type, title, message) VALUES (?,?,?,?,?)',
                        'iisss',
                        [$userId, $id, 'payment_approved', 'Payment receipt approved', $msg]
                    )->close();
                } catch (Throwable $notifyError) {
                    // Approval still succeeds if notification ENUM is outdated.
                }
            }

            log_activity($db, 'payment_approved', 'Approved payment receipt for order #' . $id, [
                'entity_type' => 'order',
                'entity_id' => $id,
                'branch_id' => (int) $order['branch_id'],
                'details' => [
                    'payment_method' => $order['payment_method'] ?? null,
                    'note' => $note !== '' ? $note : null,
                ],
            ]);
            flash_set('success', 'Payment receipt approved. Customer has been notified.');
            redirect(ops_path('payment-approvals.php'));
        }

        if ($action === 'reject') {
            if ($note === '') {
                throw new RuntimeException('Please enter a reason for rejecting this payment.');
            }

            $db->begin_transaction();
            try {
                $locked = db_one($db, 'SELECT * FROM orders WHERE id = ? FOR UPDATE', 'i', [$id]);
                if (!$locked || $locked['payment_status'] !== 'pending_review') {
                    throw new RuntimeException('This payment has already been reviewed.');
                }

                release_order_reservation($db, $locked);

                $stmt = db_exec(
                    $db,
                    "UPDATE orders
                     SET payment_status = 'refunded',
                         status = 'rejected',
                         reviewed_by = ?,
                         reviewed_at = NOW(),
                         review_note = ?,
                         payment_reviewed_by = ?,
                         payment_reviewed_at = NOW(),
                         payment_review_note = ?
                     WHERE id = ?",
                    'isisi',
                    [$reviewer, $note, $reviewer, $note, $id]
                );

                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new RuntimeException('Could not update this payment.');
                }
                $stmt->close();

                $db->commit();
            } catch (Throwable $e) {
                $db->rollback();
                throw $e;
            }

            $userId = (int) ($order['user_id'] ?? 0);
            if ($userId > 0) {
                try {
                    db_exec(
                        $db,
                        'INSERT INTO notifications (user_id, order_id, type, title, message) VALUES (?,?,?,?,?)',
                        'iisss',
                        [
                            $userId,
                            $id,
                            'payment_rejected_refunded',
                            'Payment rejected — refund processed',
                            'Your payment receipt for order #' . $id . ' was rejected. Demo refund status: refunded. Reason: ' . $note . '. No real money is moved by this demo system.',
                        ]
                    )->close();
                } catch (Throwable $notifyError) {
                    // Rejection still succeeds if notification ENUM is outdated.
                }
            }

            log_activity($db, 'payment_rejected', 'Rejected payment receipt for order #' . $id, [
                'entity_type' => 'order',
                'entity_id' => $id,
                'branch_id' => (int) $order['branch_id'],
                'details' => [
                    'payment_method' => $order['payment_method'] ?? null,
                    'note' => $note,
                ],
            ]);
            flash_set('info', 'Payment rejected; demo refund marked as processed and customer notified.');
            redirect(ops_path('payment-approvals.php'));
        }

        $error = 'Unknown action.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$title = 'Review payment #' . $id;
require dirname(__DIR__) . '/header.php';
?>
<div class="container layout-admin">
    <div><?php require dirname(__DIR__) . '/' . ops_nav_file() . '.php'; ?></div>
    <div>
        <h1>Review payment for order #<?= (int) $id ?></h1>

        <p>Customer: <?= e($order['customer_name'] ?: 'Customer') ?></p>
        <p>Branch: <strong><?= e($order['branch_name']) ?></strong> — <?= e($order['address']) ?></p>
        <p>Total: <strong><?= e(money((float) $order['total'])) ?></strong> · Method: <?= e((string) $order['payment_method']) ?></p>

        <?php if ($error): ?>
            <p class="flash flash-error"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if (!empty($order['payment_receipt_path'])): ?>
            <?php
              $receiptUrl = url(ops_path('payment-receipt.php?id=' . (int) $id));
              $receiptRel = str_replace('\\', '/', (string) $order['payment_receipt_path']);
              $isImage = (bool) preg_match('/\.(jpe?g|png|webp)$/i', $receiptRel);
            ?>
            <p>
                <a class="btn btn-secondary" target="_blank" rel="noopener"
                   href="<?= e($receiptUrl) ?>">View uploaded payment receipt</a>
            </p>
            <div class="panel" style="padding:12px;margin:12px 0;max-width:640px">
              <p class="muted" style="margin-top:0">Receipt preview</p>
              <?php if ($isImage): ?>
                <a href="<?= e($receiptUrl) ?>" target="_blank" rel="noopener">
                  <img src="<?= e($receiptUrl) ?>" alt="Payment receipt for order #<?= (int) $id ?>"
                       style="max-width:100%;height:auto;border-radius:8px;border:1px solid var(--line)">
                </a>
              <?php else: ?>
                <iframe src="<?= e($receiptUrl) ?>" title="Payment receipt PDF"
                        style="width:100%;min-height:420px;border:1px solid var(--line);border-radius:8px;background:#fff"></iframe>
              <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="flash flash-error">No payment receipt uploaded.</p>
        <?php endif; ?>

        <form method="post" class="form">
            <?= csrf_field() ?>

            <label>Review note (required for rejection)
                <input name="review_note" maxlength="255" placeholder="Reason / reference">
            </label>

            <button class="btn" name="action" value="approve">Approve Payment</button>
            <button class="btn btn-danger" name="action" value="reject">Reject &amp; Mark Demo Refund</button>
            <a class="btn btn-secondary" href="<?= e(url(ops_path('payment-approvals.php'))) ?>">Back</a>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/footer.php'; ?>