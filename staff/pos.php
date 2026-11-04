<?php
/**
 * Staff in-store POS — walk-in sales reduce stock at the staff member's home branch.
 */
require dirname(__DIR__) . '/includes/init.php';
require_staff_portal();

$db = db();
$branchId = staff_branch_id();
$branchName = user_branch_name() ?: ('Branch #' . $branchId);
// Lock POS to the assigned branch (no cross-branch switching).
pos_set_branch($branchId);

$error = '';
$products = all_products($db, true);

if (is_post()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'add') {
        $qty = max(1, post_int('qty', 1));
        $pid = post_int('product_id');
        if ($pid > 0) {
            pos_add_line($pid, $qty);
        }
        redirect('staff/pos.php');
    }
    if ($action === 'update') {
        $qtys = $_POST['qty'] ?? [];
        if (is_array($qtys)) {
            foreach ($qtys as $pid => $qty) {
                pos_set_line((int) $pid, max(0, (int) $qty));
            }
        }
        redirect('staff/pos.php');
    }
    if ($action === 'clear') {
        pos_clear();
        flash_set('info', 'Sale lines cleared.');
        redirect('staff/pos.php');
    }
    if ($action === 'complete') {
        $pos = pos_state();
        $lines = $pos['lines'] ?? [];
        // Always sell against the staff home branch, even if session was tampered.
        pos_set_branch($branchId);
        if (!$lines) {
            $error = 'Add at least one product.';
        } elseif (lines_require_prescription($db, $lines) && empty($_POST['rx_verified'])) {
            $error = 'This sale includes prescription medication. Confirm that a valid prescription was checked in person.';
        } else {
            try {
                $orderId = complete_sale($db, $lines, $branchId, 'instore', null, true, 'completed', null);
                log_activity($db, 'pos_sale', 'Completed POS sale as order #' . $orderId, [
                    'entity_type' => 'order',
                    'entity_id' => (int) $orderId,
                    'branch_id' => $branchId,
                    'details' => [
                        'line_count' => count($lines),
                        'rx_verified' => !empty($_POST['rx_verified']),
                    ],
                ]);
                pos_clear();
                flash_set('success', 'In-store sale recorded as order #' . $orderId . '.');
                redirect('staff/order.php?id=' . $orderId);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$pos = pos_state();
$lines = [];
$subtotal = 0.0;
$discountTotal = 0.0;
$needsRx = false;
foreach ($pos['lines'] as $productId => $qty) {
    $product = find_product($db, (int) $productId);
    if (!$product) {
        continue;
    }
    if ((int) ($product['requires_prescription'] ?? 0) === 1) {
        $needsRx = true;
    }
    $unit = (float) $product['unit_price'];
    $pricing = apply_sale_pricing($db, (int) $productId, $unit, (int) $qty);
    $stock = db_one(
        $db,
        'SELECT quantity, reserved_qty FROM branch_stock WHERE branch_id = ? AND product_id = ?',
        'ii',
        [$branchId, (int) $productId]
    );
    $onHand = (int) ($stock['quantity'] ?? 0);
    $reserved = (int) ($stock['reserved_qty'] ?? 0);
    $subtotal += $unit * (int) $qty;
    $discountTotal += $pricing['discount'];
    $lines[] = [
        'product' => $product,
        'qty' => (int) $qty,
        'unit' => $unit,
        'discount' => $pricing['discount'],
        'line_total' => $pricing['line_total'],
        'stock' => max(0, $onHand - $reserved),
        'promo' => $pricing['promotion'],
    ];
}

$title = 'In-store POS';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/staff_nav.php'; ?>
  <div>
    <h1>In-store sale</h1>
    <p class="muted">Walk-in transactions decrement stock at <strong><?= e($branchName) ?></strong>.</p>
    <?php if ($error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endif; ?>

    <form class="filters" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <label>Product
        <select name="product_id" required>
          <?php foreach ($products as $product): ?>
            <option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?> — <?= e(money((float) $product['unit_price'])) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Qty <input type="number" name="qty" min="1" value="1" style="width:5rem"></label>
      <button class="btn" type="submit">Add line</button>
    </form>

    <?php if (!$lines): ?>
      <p class="empty">No items on this sale yet.</p>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Product</th><th>At branch</th><th>Qty</th><th>Unit</th><th>Discount</th><th>Line</th></tr></thead>
            <tbody>
              <?php foreach ($lines as $line): ?>
                <tr>
                  <td>
                    <?= e($line['product']['name']) ?>
                    <?php if ((int) ($line['product']['requires_prescription'] ?? 0) === 1): ?>
                      <span class="badge badge-warn">Rx</span>
                    <?php endif; ?>
                    <?php if ($line['promo']): ?><div class="badge badge-ok"><?= e($line['promo']['name']) ?></div><?php endif; ?>
                  </td>
                  <td><?= (int) $line['stock'] ?></td>
                  <td><input type="number" min="0" name="qty[<?= (int) $line['product']['id'] ?>]" value="<?= (int) $line['qty'] ?>" style="width:4.5rem"></td>
                  <td><?= e(money($line['unit'])) ?></td>
                  <td><?= e(money($line['discount'])) ?></td>
                  <td><?= e(money($line['line_total'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p><strong>Total <?= e(money($subtotal - $discountTotal)) ?></strong> (discounts <?= e(money($discountTotal)) ?>)</p>
        <?php if ($needsRx): ?>
          <label class="check" style="margin:12px 0">
            <input type="checkbox" name="rx_verified" value="1" required>
            Prescription verified in person for all Rx items
          </label>
        <?php endif; ?>
        <button class="btn btn-secondary" type="submit" name="action" value="update">Update qty</button>
        <button class="btn btn-danger" type="submit" name="action" value="clear">Clear</button>
        <button class="btn" type="submit" name="action" value="complete">Complete sale</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
