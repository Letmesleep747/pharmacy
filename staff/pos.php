<?php
require dirname(__DIR__) . '/includes/init.php';
require_staff_portal();

$db = db();
$pos = pos_state();
$error = '';
$products = all_products($db, true);
$branches = all_branches($db);

if (is_post()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'branch') {
        pos_set_branch(post_int('branch_id'));
        redirect('staff/pos.php');
    }
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
        $branchId = (int) $pos['branch_id'];
        $lines = $pos['lines'] ?? [];
        if ($branchId <= 0) {
            $error = 'Select a branch before completing the sale.';
        } elseif (!$lines) {
            $error = 'Add at least one product.';
        } else {
            try {
                $orderId = complete_sale($db, $lines, $branchId, 'instore', null);
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
foreach ($pos['lines'] as $productId => $qty) {
    $product = find_product($db, (int) $productId);
    if (!$product) {
        continue;
    }
    $unit = (float) $product['unit_price'];
    $pricing = apply_sale_pricing($db, (int) $productId, $unit, (int) $qty);
    $stock = db_one(
        $db,
        'SELECT quantity FROM branch_stock WHERE branch_id = ? AND product_id = ?',
        'ii',
        [(int) $pos['branch_id'], (int) $productId]
    );
    $subtotal += $unit * (int) $qty;
    $discountTotal += $pricing['discount'];
    $lines[] = [
        'product' => $product,
        'qty' => (int) $qty,
        'unit' => $unit,
        'discount' => $pricing['discount'],
        'line_total' => $pricing['line_total'],
        'stock' => (int) ($stock['quantity'] ?? 0),
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
    <p class="muted">Walk-in transactions decrement stock at the selected branch.</p>
    <?php if ($error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endif; ?>

    <form class="filters" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="branch">
      <label>Branch
        <select name="branch_id" required>
          <option value="0">Select branch</option>
          <?php foreach ($branches as $branch): ?>
            <option value="<?= (int) $branch['id'] ?>" <?= (int) $pos['branch_id'] === (int) $branch['id'] ? 'selected' : '' ?>>
              <?= e($branch['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-secondary" type="submit">Use branch</button>
    </form>

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
                    <?php if ($line['promo']): ?><div class="badge badge-ok"><?= e($line['promo']['name']) ?></div><?php endif; ?>
                  </td>
                  <td><?= (int) $pos['branch_id'] ? (int) $line['stock'] : '—' ?></td>
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
        <button class="btn btn-secondary" type="submit" name="action" value="update">Update qty</button>
        <button class="btn btn-danger" type="submit" name="action" value="clear">Clear</button>
        <button class="btn" type="submit" name="action" value="complete">Complete sale</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
