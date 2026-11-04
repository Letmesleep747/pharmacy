<?php
/**
 * Customer checkout.
 * Online orders reserve available stock (quantity − reserved_qty) for 48 hours.
 * Rx carts: customer chooses online upload (pay + reserve + pending approval)
 * or walk-in (no online order / no reservation — present Rx at the counter).
 */
require dirname(__DIR__) . '/includes/init.php';
require_storefront();
require_login();

if (user_role() !== 'customer') {
    flash_set('error', 'Only customers can place online orders.');
    redirect('index.php');
}

$db = db();
$needsRx = lines_require_prescription($db, cart());

$paymentMethods = [
    'fpx'     => 'FPX',
    'card'    => 'Credit / Debit Card',
    'tng'     => "Touch 'n Go eWallet",
    'duitnow' => 'DuitNow QR (Demo)',
];

/* Demo FPX bank list. */
$fpxBanks = [
    'MB2U0227' => 'Maybank2u',
    'CIMB0226' => 'CIMB Clicks',
    'PBB0233'  => 'Public Bank PBe',
    'RHB0218'  => 'RHB Now',
    'HLB0224'  => 'Hong Leong Connect',
    'BIMB0340' => 'Bank Islam',
    'ABMB0212' => 'AmOnline',
    'BSN0601'  => 'myBSN',
    'BKRM0602' => 'Bank Rakyat',
    'AMBB0209' => 'Affinmax',
];

/* Read the real order total from the DB; fall back to the cart summary. */
$resolveOrderTotal = static function ($db, int $orderId, float $fallback): float {
    try {
        $row = db_one($db, 'SELECT * FROM orders WHERE id = ?', 'i', [$orderId]);
    } catch (Throwable $e) {
        return $fallback;
    }

    if (!is_array($row)) {
        return $fallback;
    }

    foreach (['total', 'total_amount', 'grand_total', 'amount', 'order_total', 'total_price'] as $key) {
        if (isset($row[$key]) && is_numeric($row[$key]) && (float) $row[$key] > 0) {
            return (float) $row[$key];
        }
    }

    return $fallback;
};

$step             = isset($_GET['step']) ? (string) $_GET['step'] : 'form';
$error            = '';
$selectedPayment  = '';
$selectedBank     = '';
$selectedBranchId = 0;
$rxMethod         = 'online';
$receipt          = null;
$pending          = null;

/* ---------------- POST ---------------- */
if (is_post()) {
    $action = (string) ($_POST['action'] ?? 'place_order');

    /* TnG: cancelled */
    if ($action === 'tng_cancel') {
        unset($_SESSION['tng_pending']);
        flash_set('error', "Touch 'n Go payment was cancelled. Your order was not placed.");
        redirect('pages/checkout.php');
    }

    /* TnG: confirmed (kept for optional gateway flow) */
    if ($action === 'tng_confirm') {
        $pending = $_SESSION['tng_pending'] ?? null;
        if (!$pending) {
            flash_set('error', 'Your payment session has expired. Please place the order again.');
            redirect('pages/checkout.php');
        }

        try {
            $prescriptionPath = $pending['prescription_path'] ?? null;
            $status = (string) ($pending['status'] ?? 'ready_for_pickup');
            if ($needsRx && ($prescriptionPath === null || $prescriptionPath === '')) {
                throw new RuntimeException('Prescription file missing. Please start checkout again and upload your prescription.');
            }

            $orderId = complete_sale(
                $db,
                cart(),
                (int) $pending['branch_id'],
                'online',
                (int) current_user()['id'],
                false,
                $status,
                $prescriptionPath
            );

            unset($_SESSION['tng_pending']);
            cart_clear();

            $_SESSION['checkout_receipt'] = [
                'order_id'       => (int) $orderId,
                'paid_at'        => date('d M Y, g:i A'),
                'branch_name'    => $pending['branch_name'],
                'branch_address' => $pending['branch_address'],
                'payment_label'  => $paymentMethods['tng'],
                'payment_detail' => $pending['payment_detail'],
                'reference'      => $pending['reference'],
                'total'          => $resolveOrderTotal($db, (int) $orderId, (float) $pending['total']),
                'status'         => $status,
            ];

            redirect('pages/checkout.php?step=receipt');
        } catch (Throwable $e) {
            unset($_SESSION['tng_pending']);
            flash_set('error', $e->getMessage());
            redirect('pages/checkout.php');
        }
    }

    /* ------- Normal place order ------- */
    $selectedBranchId = post_int('branch_id');
    $selectedPayment  = trim((string) ($_POST['payment_method'] ?? ''));
    $selectedBank     = trim((string) ($_POST['fpx_bank'] ?? ''));
    $rxMethod         = trim((string) ($_POST['rx_method'] ?? 'online'));

    if (!$needsRx) {
        $rxMethod = 'online';
    }
    if ($needsRx && !in_array($rxMethod, ['online', 'walkin'], true)) {
        $rxMethod = 'online';
    }

    if ($error === '' && $needsRx && $rxMethod === 'walkin') {
        $error = 'Walk-in prescriptions are not checked out online. Choose "Upload online" to pay and reserve, or visit a branch with your paper prescription.';
    }

    if ($error === '' && !array_key_exists($selectedPayment, $paymentMethods)) {
        $error = 'Please select a valid payment method.';
    }

    if ($error === '' && $selectedPayment === 'fpx') {
        if (!array_key_exists($selectedBank, $fpxBanks)) {
            $error = 'Please choose your FPX bank.';
        }
    }

    if ($error === '' && (($_FILES['payment_receipt']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
        $error = 'Please upload your payment receipt or transaction screenshot.';
    }

    $branch = null;
    if ($error === '') {
        $branch = db_one(
            $db,
            'SELECT id, name, address FROM branches WHERE id = ? AND is_active = 1',
            'i',
            [$selectedBranchId]
        );
        if (!$branch) {
            $error = 'Please choose a valid pickup branch.';
        } else {
            $branchCheck = cart_branch_availability($db, cart());
            foreach ($branchCheck as $row) {
                if ((int) $row['id'] === (int) $branch['id'] && !$row['can_fulfil']) {
                    $short = [];
                    foreach ($row['items'] as $item) {
                        if (!$item['ok']) {
                            $short[] = $item['name'] . ' (need ' . $item['needed'] . ', available ' . $item['available'] . ')';
                        }
                    }
                    $error = 'That branch does not have enough stock for your cart: ' . implode('; ', $short);
                    break;
                }
            }
        }
    }

    if ($error === '') {
        /* Use the same pricing function that complete_sale() uses. */
        $cartSummary = cart_summary($db, cart());
        $cartTotal   = (float) $cartSummary['total'];

        if ($selectedPayment === 'fpx') {
            $paymentDetail = 'FPX — ' . $fpxBanks[$selectedBank];
        } elseif ($selectedPayment === 'card') {
            $last4 = substr(
                preg_replace('/\D/', '', (string) ($_POST['card_number'] ?? '')),
                -4
            );
            $paymentDetail = 'Credit / Debit Card •••• ' . $last4;
        } elseif ($selectedPayment === 'duitnow') {
            $paymentDetail = 'DuitNow QR';
        } else {
            $paymentDetail = $paymentMethods[$selectedPayment];
        }

        try {
            $prescriptionPath = null;
            $status = 'ready_for_pickup';

            if ($needsRx) {
                $prescriptionPath = save_prescription_upload($_FILES['prescription'] ?? []);
                $status = 'pending_approval';
            }

            $paymentReceiptPath = save_payment_receipt_upload($_FILES['payment_receipt'] ?? []);

            $orderId = complete_sale(
                $db,
                cart(),
                (int) $branch['id'],
                'online',
                (int) current_user()['id'],
                false,
                $status,
                $prescriptionPath
            );

            db_exec(
                $db,
                "UPDATE orders
                 SET payment_status = 'pending_review', payment_method = ?, payment_receipt_path = ?
                 WHERE id = ?",
                'ssi',
                [$selectedPayment, $paymentReceiptPath, (int) $orderId]
            )->close();

            // --- SAFE NOTIFICATION BLOCK ---
            try {
                $reviewers = db_all(
                    $db,
                    "SELECT id FROM users WHERE role = 'admin' OR (role = 'staff' AND branch_id = ?)",
                    'i',
                    [(int) $branch['id']]
                );

                foreach ($reviewers as $reviewer) {
                    db_exec(
                        $db,
                        'INSERT INTO notifications (user_id, order_id, type, title, message) VALUES (?,?,?,?,?)',
                        'iisss',
                        [
                            (int) $reviewer['id'],
                            (int) $orderId,
                            'payment_submitted',
                            'Payment receipt needs review',
                            'Order #' . $orderId . ' payment receipt was submitted for ' . $branch['name'] . '. Please verify the payment.',
                        ]
                    )->close();
                }
            } catch (Throwable $e) {
                // Ignore notification failures so the checkout still succeeds
            }
            // --- END SAFE NOTIFICATION BLOCK ---

            cart_clear();

            $_SESSION['checkout_receipt'] = [
                'order_id'       => (int) $orderId,
                'paid_at'        => date('d M Y, g:i A'),
                'branch_name'    => $branch['name'],
                'branch_address' => $branch['address'],
                'payment_label'  => $paymentMethods[$selectedPayment],
                'payment_detail' => $paymentDetail,
                'reference'      => 'SUB' . strtoupper(bin2hex(random_bytes(4))),
                'total'          => $resolveOrderTotal($db, (int) $orderId, $cartTotal),
                'status'         => $status,
            ];

            redirect('pages/checkout.php?step=receipt');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

/* ---------------- Step resolution ---------------- */
if ($step === 'receipt') {
    $receipt = $_SESSION['checkout_receipt'] ?? null;
    if (!$receipt) {
        flash_set('error', 'No recent payment receipt was found.');
        redirect('pages/checkout.php');
    }
} elseif ($step === 'tng') {
    $pending = $_SESSION['tng_pending'] ?? null;
    if (!$pending) {
        flash_set('error', 'Your payment session has expired. Please place the order again.');
        redirect('pages/checkout.php');
    }
} else {
    if (!is_post()) {
        unset($_SESSION['checkout_receipt']);
    }
    if (cart() === []) {
        flash_set('error', 'Your cart is empty.');
        redirect('pages/cart.php');
    }
}

$branches         = all_branches($db);
$cartSummary      = cart_summary($db, cart());
$branchAvailability = cart_branch_availability($db, cart());
$branchAvailabilityById = [];
foreach ($branchAvailability as $row) {
    $branchAvailabilityById[(int) $row['id']] = $row;
}

$title = $step === 'receipt'
    ? 'Payment Receipt'
    : ($step === 'tng' ? "Touch 'n Go eWallet" : 'Checkout');

require dirname(__DIR__) . '/includes/header.php';
?>

<style>
/* =============== checkout / receipt CSS =============== */
.payment-box { max-width:600px; margin:25px auto; padding:25px; background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 4px 15px rgba(0,0,0,.06); }
.payment-box h2 { margin-bottom:8px; }
.payment-box label { display:block; margin-top:18px; font-weight:600; }
.payment-box input, .payment-box select { display:block; width:100%; box-sizing:border-box; padding:12px; margin-top:7px; border:1px solid #d1d5db; border-radius:7px; font-size:15px; background:#fff; }
.payment-note { color:#6b7280; font-size:14px; line-height:1.5; margin-top:10px; }
.demo-warning { color:#b91c1c; font-weight:bold; }
.qr-panel { margin-top:20px; padding:20px; text-align:center; background:#fff5f8; border:2px dashed #e91e63; border-radius:12px; }
.qr-panel img { display:block; width:250px; max-width:100%; height:auto; margin:15px auto; }
.payment-submit { width:100%; margin-top:25px; padding:14px; border:none; border-radius:8px; background:#d81b60; color:#fff; font-size:16px; font-weight:bold; cursor:pointer; }
.payment-submit:hover { background:#ad1457; }
.payment-section[hidden] { display:none !important; }

.tng-gateway { max-width:480px; margin:25px auto; background:#fff; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,.08); }
.tng-header { display:flex; align-items:center; justify-content:space-between; padding:18px 22px; background:linear-gradient(135deg,#0067b1,#00a0e3); color:#fff; }
.tng-logo { font-weight:800; line-height:1.1; font-size:15px; }
.tng-logo span { font-weight:500; font-size:13px; opacity:.9; }
.tng-secure { font-size:12px; background:rgba(255,255,255,.18); padding:5px 10px; border-radius:20px; }
.tng-body { padding:26px 22px 28px; text-align:center; }
.tng-amount { font-size:34px; font-weight:800; color:#0067b1; margin:6px 0 18px; }
.tng-row { display:flex; justify-content:space-between; font-size:14px; padding:9px 0; border-bottom:1px dashed #e5e7eb; text-align:left; }
.tng-row span:first-child { color:#6b7280; }
.tng-field { text-align:left; margin-top:18px; }
.tng-field label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#374151; }
.tng-field input { width:100%; box-sizing:border-box; padding:12px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb; font-size:15px; }
.tng-pay { width:100%; margin-top:22px; padding:15px; border:none; border-radius:10px; background:#0067b1; color:#fff; font-size:16px; font-weight:700; cursor:pointer; }
.tng-pay:hover { background:#00528e; }
.tng-cancel { width:100%; margin-top:10px; padding:12px; border:1px solid #d1d5db; border-radius:10px; background:#fff; color:#374151; font-size:14px; font-weight:600; cursor:pointer; }
.tng-cancel:hover { background:#f3f4f6; }

.receipt-box { max-width:560px; margin:25px auto; background:#fff; border:1px solid #e5e7eb; border-radius:16px; box-shadow:0 8px 24px rgba(0,0,0,.08); overflow:hidden; }
.receipt-top { padding:28px 24px 22px; text-align:center; background:#f0fdf4; border-bottom:1px solid #dcfce7; }
.receipt-check { width:58px; height:58px; line-height:58px; margin:0 auto 14px; border-radius:50%; background:#16a34a; color:#fff; font-size:30px; font-weight:700; }
.receipt-top h2 { margin:0 0 6px; color:#166534; }
.receipt-amount { margin:22px 24px 0; padding:18px; text-align:center; background:#fff7fb; border:1px dashed #f9a8d4; border-radius:12px; }
.receipt-amount span { display:block; font-size:13px; color:#9d174d; letter-spacing:.05em; text-transform:uppercase; }
.receipt-amount strong { display:block; margin-top:6px; font-size:30px; color:#be185d; }
.receipt-table { width:calc(100% - 48px); margin:22px 24px 0; border-collapse:collapse; font-size:14px; }
.receipt-table th, .receipt-table td { padding:11px 0; text-align:left; vertical-align:top; border-bottom:1px solid #f3f4f6; }
.receipt-table th { width:42%; color:#6b7280; font-weight:600; }
.receipt-table td { color:#111827; }
.badge-paid { display:inline-block; padding:4px 10px; border-radius:20px; background:#dcfce7; color:#166534; font-size:12px; font-weight:700; }
.receipt-note { margin:18px 24px 0; padding:12px 14px; font-size:13px; line-height:1.5; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:10px; }
.receipt-actions { display:flex; flex-wrap:wrap; gap:10px; padding:22px 24px 26px; }
.receipt-actions .btn { flex:1 1 auto; text-align:center; padding:13px 18px; border-radius:9px; font-weight:700; font-size:14px; text-decoration:none; border:none; cursor:pointer; }
.btn-primary { background:#d81b60; color:#fff; }
.btn-primary:hover { background:#ad1457; }
.btn-outline { background:#fff; color:#374151; border:1px solid #d1d5db; }
.btn-outline:hover { background:#f3f4f6; }

@media print {
    .receipt-actions, header, footer, nav { display:none !important; }
}
</style>

<?php if ($step === 'receipt'): ?>

    <!-- ============ PAYMENT RECEIPT ============ -->
    <section class="section">
        <div class="container">
            <div class="receipt-box">
                <div class="receipt-top">
                    <div class="receipt-check">&check;</div>
                    <h2>Payment Receipt Submitted</h2>
                    <p class="muted">
                        Your payment proof has been submitted. Admin/staff must verify it before the order is confirmed.
                    </p>
                    <p class="flash flash-info" style="margin-top:12px">
                        Your order was sent to <?= e($receipt['branch_name']) ?>. You will receive a notification after admin/staff review the payment receipt and order.
                    </p>
                </div>

                <div class="receipt-amount">
                    <span>Total Paid</span>
                    <strong>RM <?= number_format((float) $receipt['total'], 2) ?></strong>
                </div>

                <table class="receipt-table">
                    <tr><th>Receipt No.</th><td><?= e($receipt['reference']) ?></td></tr>
                    <tr><th>Order ID</th><td>#<?= (int) $receipt['order_id'] ?></td></tr>
                    <tr><th>Date &amp; Time</th><td><?= e($receipt['paid_at']) ?></td></tr>
                    <tr><th>Payment Method</th><td><?= e($receipt['payment_detail']) ?></td></tr>
                    <tr>
                        <th>Pickup Branch</th>
                        <td>
                            <?= e($receipt['branch_name']) ?><br>
                            <small class="muted"><?= e($receipt['branch_address']) ?></small>
                        </td>
                    </tr>
                    <tr><th>Status</th><td><span class="badge-paid">Awaiting payment verification</span></td></tr>
                </table>

                <p class="receipt-note">
                    <strong>Payment proof received.</strong> This receipt confirms your submission only; it does not mean the payment has been verified. This demo system does not transfer or refund real money.
                </p>

                <div class="receipt-actions">
                    <button type="button" class="btn btn-primary" onclick="window.print()">Print Receipt</button>
                    <a class="btn btn-outline" href="../index.php">Continue Shopping</a>
                </div>
            </div>
        </div>
    </section>

<?php elseif ($step === 'tng'): ?>

    <!-- ============ SIMULATED TOUCH 'N GO GATEWAY ============ -->
    <section class="section">
        <div class="container">
            <div class="tng-gateway">
                <div class="tng-header">
                    <div class="tng-logo">Touch 'n Go<br><span>eWallet</span></div>
                    <div class="tng-secure">&#128274; Secure Gateway</div>
                </div>
                <div class="tng-body">
                    <p class="muted" style="margin:0;">You are paying</p>
                    <div class="tng-amount">RM <?= number_format((float) $pending['total'], 2) ?></div>
                    <div class="tng-row"><span>Merchant</span><strong>Pharmacy Online</strong></div>
                    <div class="tng-row"><span>Reference</span><strong><?= e($pending['reference']) ?></strong></div>
                    <div class="tng-row" style="border-bottom:none;"><span>Pickup Branch</span><strong><?= e($pending['branch_name']) ?></strong></div>
                    <div class="tng-field">
                        <label for="tng_phone">eWallet mobile number</label>
                        <input type="text" id="tng_phone" value="01X-XXX XXXX" disabled>
                    </div>
                    <p class="demo-warning" style="margin-top:18px;">DEMO ONLY — NO REAL PAYMENT WILL BE MADE</p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="tng_confirm">
                        <button type="submit" class="tng-pay">Confirm Payment</button>
                    </form>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="tng_cancel">
                        <button type="submit" class="tng-cancel">Cancel &amp; return to checkout</button>
                    </form>
                    <p class="payment-note">
                        In a live system this page would be the real Touch 'n Go eWallet gateway, reached through a merchant integration.
                    </p>
                </div>
            </div>
        </div>
    </section>

<?php else: ?>

    <!-- ============ CHECKOUT FORM ============ -->
    <section class="section">
        <div class="container">
            <div class="payment-box">
                <h2>Pharmacy Checkout</h2>
                <p class="muted">
                    <?php if ($needsRx): ?>
                        Your cart includes prescription medication. Choose how you will provide the prescription, then continue.
                    <?php else: ?>
                        Select your pickup branch and preferred payment method.
                    <?php endif; ?>
                </p>

                <?php if ($error !== ''): ?>
                    <p class="flash flash-error"><?= e($error) ?></p>
                <?php endif; ?>

                <form method="post" class="form" id="checkout-form" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="place_order">

                    <?php if ($needsRx): ?>
                    <fieldset class="payment-section" style="margin:0 0 18px;border:0;padding:0">
                        <legend style="font-weight:600;margin-bottom:8px">How will you provide your prescription?</legend>
                        <label style="display:block;margin-bottom:8px">
                            <input type="radio" name="rx_method" id="rx_method_online" value="online" <?= $rxMethod !== 'walkin' ? 'checked' : '' ?>>
                            <strong>Upload online</strong> — pay now, we reserve stock at your branch, then staff approve before pickup
                        </label>
                        <label style="display:block">
                            <input type="radio" name="rx_method" id="rx_method_walkin" value="walkin" <?= $rxMethod === 'walkin' ? 'checked' : '' ?>>
                            <strong>Walk in</strong> — bring the paper prescription to a branch (no online order, no stock reservation)
                        </label>
                    </fieldset>

                    <div id="rx-walkin-panel" class="payment-section" <?= $rxMethod === 'walkin' ? '' : 'hidden' ?> style="margin-bottom:18px">
                        <h3>Walk-in with your prescription</h3>
                        <p class="payment-note">
                            Online checkout is not used for this option. Visit any active branch with a valid prescription;
                            counter staff will verify it in person and sell from available stock (POS). Nothing is reserved online.
                        </p>
                        <ul class="muted" style="margin:0 0 12px;padding-left:1.2rem">
                            <?php foreach ($branches as $branch): ?>
                                <li><strong><?= e($branch['name']) ?></strong> — <?= e($branch['address']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="btn btn-secondary" href="<?= e(url('pages/branches.php')) ?>">View branches</a>
                        <a class="btn btn-secondary" href="<?= e(url('pages/cart.php')) ?>">Back to cart</a>
                    </div>
                    <?php endif; ?>

                    <div id="checkout-online-fields" <?= ($needsRx && $rxMethod === 'walkin') ? 'hidden' : '' ?>>

                        <label for="branch_id">Pickup Branch</label>
                        <select name="branch_id" id="branch_id" <?= ($needsRx && $rxMethod === 'walkin') ? '' : 'required' ?>>
                            <option value="">Choose a branch</option>
                            <?php foreach ($branchAvailability as $branch): ?>
                                <option
                                    value="<?= (int) $branch['id'] ?>"
                                    <?= $selectedBranchId === (int) $branch['id'] ? 'selected' : '' ?>
                                    <?= $branch['can_fulfil'] ? '' : 'disabled' ?>
                                >
                                    <?= e($branch['name']) ?>
                                    — <?= $branch['can_fulfil'] ? 'in stock for your cart' : 'not enough stock' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="payment-note">Only branches with enough available stock for every item in your cart can be selected. Available = on hand − reserved.</p>

                        <div id="branch-stock-panel" class="payment-section" style="margin-top:14px">
                            <h3 style="margin:0 0 8px;font-size:1rem">Stock at each branch</h3>
                            <ul class="availability" id="branch-stock-list">
                                <?php foreach ($branchAvailability as $branch): ?>
                                  <li data-branch-id="<?= (int) $branch['id'] ?>">
                                    <div>
                                      <strong><?= e($branch['name']) ?></strong>
                                      <div class="muted"><?= e($branch['address']) ?></div>
                                      <ul class="muted" style="margin:6px 0 0;padding-left:1.1rem">
                                        <?php foreach ($branch['items'] as $item): ?>
                                          <li>
                                            <?= e($item['name']) ?>:
                                            need <?= (int) $item['needed'] ?>,
                                            available <?= (int) $item['available'] ?>
                                            <?php if (!$item['ok']): ?>
                                              <span class="badge badge-danger">Short</span>
                                            <?php endif; ?>
                                          </li>
                                        <?php endforeach; ?>
                                      </ul>
                                    </div>
                                    <div>
                                      <?php if ($branch['can_fulfil']): ?>
                                        <span class="badge badge-ok">Can fulfil cart</span>
                                      <?php else: ?>
                                        <span class="badge badge-danger">Not enough stock</span>
                                      <?php endif; ?>
                                    </div>
                                  </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <?php if ($needsRx): ?>
                        <div id="rx-upload-panel" class="payment-section" style="margin-top: 18px;">
                            <h3>Upload prescription</h3>
                            <p class="payment-note">
                                Upload a clear photo or PDF (max 5 MB). After you pay, stock is reserved for 48 hours
                                and the order stays <strong>pending approval</strong> until branch staff review it.
                                If rejected, the reservation is released (demo refund message).
                            </p>
                            <label for="prescription">Prescription file (PDF/JPG/PNG)</label>
                            <input type="file" name="prescription" id="prescription"
                                   accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                   <?= $rxMethod !== 'walkin' ? 'required' : '' ?>>
                        </div>
                        <?php endif; ?>

                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="">Choose payment method</option>
                            <?php foreach ($paymentMethods as $key => $name): ?>
                                <option value="<?= e($key) ?>" <?= $selectedPayment === $key ? 'selected' : '' ?>>
                                    <?= e($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div id="fpx-section" class="payment-section" hidden>
                            <h3 style="margin-top:24px;">FPX Online Banking</h3>
                            <p class="payment-note">
                                Choose your bank. In a live store you would be redirected to your bank's secure login page.
                            </p>
                            <label for="fpx_bank">Select Bank</label>
                            <select name="fpx_bank" id="fpx_bank">
                                <option value="">Choose your bank</option>
                                <?php foreach ($fpxBanks as $code => $bankName): ?>
                                    <option value="<?= e($code) ?>" <?= $selectedBank === $code ? 'selected' : '' ?>>
                                        <?= e($bankName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="payment-note"><strong>Demo only — no payment processed.</strong></p>
                        </div>

                        <div id="card-section" class="payment-section" hidden>
                            <h3 style="margin-top:24px;">Credit / Debit Card</h3>
                            <p class="payment-note">
                                Demo only. Use fictional test details. These fields are never stored on the server.
                            </p>
                            <label for="card_number">Test Card Number</label>
                            <input type="text" id="card_number" name="card_number" inputmode="numeric"
                                   autocomplete="off" maxlength="19" placeholder="1234 5678 9012 3456"
                                   pattern="[0-9 ]{13,19}">
                            <label for="card_name">Cardholder Name</label>
                            <input type="text" id="card_name" name="card_name" autocomplete="off"
                                   placeholder="DEMO CUSTOMER" maxlength="60">
                            <div style="display:flex; gap:15px;">
                                <div style="flex:1;">
                                    <label for="card_expiry">Expiry Date</label>
                                    <input type="text" id="card_expiry" name="card_expiry" autocomplete="off"
                                           placeholder="MM/YY" maxlength="5" pattern="(0[1-9]|1[0-2])/[0-9]{2}">
                                </div>
                                <div style="flex:1;">
                                    <label for="card_cvv">Test CVV</label>
                                    <input type="password" id="card_cvv" name="card_cvv" inputmode="numeric"
                                           autocomplete="off" placeholder="123" maxlength="4" pattern="[0-9]{3,4}">
                                </div>
                            </div>
                            <p class="payment-note">
                                <strong>Demo mode:</strong> No card will be charged. Never enter your actual card number, expiry date or CVV here.
                            </p>
                        </div>

                        <div id="duitnow-section" class="payment-section" hidden>
                            <div class="qr-panel">
                                <h3 style="color:#d81b60;">DuitNow QR Payment</h3>
                                <img src="DuitNow QR Demo.png" alt="DuitNow QR demonstration image">
                                <p class="demo-warning">DEMO ONLY — NOT VALID FOR PAYMENT</p>
                            </div>
                        </div>

                        <div id="tng-section" class="payment-section payment-note" hidden>
                            <h3>Touch 'n Go eWallet</h3>
                            <p>Pay with the Touch 'n Go eWallet app, then upload your transaction receipt below. Admin or branch staff will verify the payment before your order is confirmed.</p>
                            <p><strong>Demo only — no real payment processed.</strong></p>
                        </div>

                        <!-- ============ CART SUMMARY / AMOUNT TO PAY ============ -->
                        <div style="margin-top:20px;padding:14px;border:1px solid #e5e7eb;border-radius:8px">

                            <?php if (!empty($cartSummary['items'])): ?>
                                <div class="table-wrap" style="margin-bottom:14px">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Medicine</th>
                                                <th>Qty</th>
                                                <th>Unit Price</th>
                                                <th>Line Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cartSummary['items'] as $item): ?>
                                                <tr>
                                                    <td><?= e($item['name']) ?></td>
                                                    <td><?= (int) $item['quantity'] ?></td>
                                                    <td><?= e(money((float) $item['unit_price'])) ?></td>
                                                    <td><?= e(money((float) $item['line_total'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="3">Subtotal</th>
                                                <td><?= e(money((float) $cartSummary['subtotal'])) ?></td>
                                            </tr>
                                            <?php if ((float) $cartSummary['discount'] > 0): ?>
                                                <tr>
                                                    <th colspan="3">Discount</th>
                                                    <td>-<?= e(money((float) $cartSummary['discount'])) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th colspan="3">Total</th>
                                                <td><strong><?= e(money((float) $cartSummary['total'])) ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <h3>Amount to Pay: RM <?= number_format((float) $cartSummary['total'], 2) ?></h3>

                            <p class="payment-note">
                                After making payment using your selected method, upload the transaction receipt/screenshot below.
                                Admin or branch staff will verify it. Accepted files: PDF/JPG/PNG/WEBP, maximum 5 MB.
                            </p>

                            <label for="payment_receipt">Payment receipt / transaction screenshot</label>
                            <input type="file" name="payment_receipt" id="payment_receipt"
                                   accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
                                   required>
                        </div>

                        <button type="submit" class="payment-submit" id="checkout-submit">
                            Submit Payment Receipt &amp; Place Order
                        </button>

                    </div><!-- #checkout-online-fields -->
                </form>
            </div>
        </div>
    </section>

<?php endif; ?>

<?php if ($step === 'form'): ?>
<script>
(function () {
    const onlineFields = document.getElementById('checkout-online-fields');
    const walkinPanel  = document.getElementById('rx-walkin-panel');
    const rxOnline     = document.getElementById('rx_method_online');
    const rxWalkin     = document.getElementById('rx_method_walkin');
    const prescription = document.getElementById('prescription');
    const branchSelect = document.getElementById('branch_id');
    const methodSelect = document.getElementById('payment_method');

    function syncRxMethod() {
        if (!rxOnline || !rxWalkin || !onlineFields) return;
        const walkin = rxWalkin.checked;
        onlineFields.hidden = walkin;
        if (walkinPanel) walkinPanel.hidden = !walkin;
        if (prescription) prescription.required = !walkin;
        if (branchSelect) branchSelect.required = !walkin;
        if (methodSelect) methodSelect.required = !walkin;
    }
    if (rxOnline) rxOnline.addEventListener('change', syncRxMethod);
    if (rxWalkin) rxWalkin.addEventListener('change', syncRxMethod);
    syncRxMethod();

    function highlightBranchStock() {
        if (!branchSelect) return;
        const selected = branchSelect.value;
        document.querySelectorAll('#branch-stock-list > li').forEach((li) => {
            const match = selected !== '' && li.getAttribute('data-branch-id') === selected;
            li.style.outline = match ? '2px solid var(--teal-500, #0f766e)' : '';
            li.style.background = match ? '#f0fdfa' : '';
        });
    }
    if (branchSelect) {
        branchSelect.addEventListener('change', highlightBranchStock);
        highlightBranchStock();
    }
})();

const methodSelect = document.getElementById('payment_method');

if (methodSelect) {
    const sections = {
        fpx:     document.getElementById('fpx-section'),
        card:    document.getElementById('card-section'),
        duitnow: document.getElementById('duitnow-section'),
        tng:     document.getElementById('tng-section')
    };

    const bankSelect = document.getElementById('fpx_bank');
    const cardFields = [
        document.getElementById('card_number'),
        document.getElementById('card_expiry'),
        document.getElementById('card_cvv')
    ];

    function updatePaymentMethod() {
        const selected = methodSelect.value;
        Object.entries(sections).forEach(([method, section]) => {
            if (section) section.hidden = method !== selected;
        });
        if (bankSelect) bankSelect.required = (selected === 'fpx');
        cardFields.forEach(field => { if (field) field.required = (selected === 'card'); });
    }

    methodSelect.addEventListener('change', updatePaymentMethod);
    updatePaymentMethod();

    const cardNumber = document.getElementById('card_number');
    if (cardNumber) {
        cardNumber.addEventListener('input', function () {
            let digits = this.value.replace(/\D/g, '').slice(0, 16);
            this.value = digits.match(/.{1,4}/g)?.join(' ') || '';
        });
    }

    const cardExpiry = document.getElementById('card_expiry');
    if (cardExpiry) {
        cardExpiry.addEventListener('input', function () {
            let digits = this.value.replace(/\D/g, '').slice(0, 4);
            if (digits.length > 2) digits = digits.slice(0, 2) + '/' + digits.slice(2);
            this.value = digits;
        });
    }

    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (event) {
            const walkin = document.getElementById('rx_method_walkin');
            if (walkin && walkin.checked) {
                event.preventDefault();
                alert('Walk-in does not use online checkout. Visit a branch with your prescription, or choose Upload online.');
                return;
            }
            if (methodSelect.value !== 'card') return;

            const number = document.getElementById('card_number').value.replace(/\D/g, '');
            const expiry = document.getElementById('card_expiry').value;
            const cvv    = document.getElementById('card_cvv').value;

            if (
                number.length < 13 ||
                number.length > 16 ||
                !/^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(expiry) ||
                !/^[0-9]{3,4}$/.test(cvv)
            ) {
                event.preventDefault();
                alert('Please enter valid fictional demo card details. Do not use a real bank card.');
            }
        });
    }
}
</script>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>