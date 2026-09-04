<?php
require dirname(__DIR__) . '/includes/init.php';
require_storefront();

$db = db();

if (is_post()) {
    $action = (string) ($_POST['action'] ?? 'update');
    if ($action === 'clear') {
        cart_clear();
        flash_set('info', 'Cart cleared.');
        redirect('pages/cart.php');
    }
    $qtys = $_POST['qty'] ?? [];
    if (is_array($qtys)) {
        foreach ($qtys as $pid => $qty) {
            cart_set((int) $pid, max(0, (int) $qty));
        }
    }
    flash_set('success', 'Cart updated.');
    redirect('pages/cart.php');
}

$items = [];
$subtotal = 0.0;
$discountTotal = 0.0;
foreach (cart() as $productId => $qty) {
    $product = find_product($db, (int) $productId);
    if (!$product) {
        continue;
    }
    $unit = (float) $product['unit_price'];
    $pricing = apply_sale_pricing($db, (int) $productId, $unit, (int) $qty);
    $subtotal += $unit * (int) $qty;
    $discountTotal += $pricing['discount'];
    $items[] = [
        'product' => $product,
        'qty' => (int) $qty,
        'unit' => $unit,
        'discount' => $pricing['discount'],
        'line_total' => $pricing['line_total'],
        'promo' => $pricing['promotion'],
    ];
}

$title = 'Cart';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h1>Your cart</h1>
    <?php if (!$items): ?>
      <p class="empty">Your cart is empty. <a href="<?= e(url('pages/products.php')) ?>">Browse medications</a></p>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Product</th><th>Qty</th><th>Price</th><th>Discount</th><th>Line</th></tr>
            </thead>
            <tbody>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td>
                    <?= e($item['product']['name']) ?>
                    <?php if ($item['promo']): ?><div class="badge badge-ok"><?= e($item['promo']['name']) ?></div><?php endif; ?>
                  </td>
                  <td><input type="number" name="qty[<?= (int) $item['product']['id'] ?>]" min="0" value="<?= (int) $item['qty'] ?>" style="width:4.5rem"></td>
                  <td><?= e(money($item['unit'])) ?></td>
                  <td><?= e(money($item['discount'])) ?></td>
                  <td><?= e(money($item['line_total'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p><strong>Subtotal</strong> <?= e(money($subtotal)) ?> · <strong>Discounts</strong> <?= e(money($discountTotal)) ?> · <strong>Total</strong> <?= e(money($subtotal - $discountTotal)) ?></p>
        <button class="btn btn-secondary" type="submit">Update cart</button>
        <button class="btn btn-danger" type="submit" name="action" value="clear">Clear</button>
        <a class="btn" href="<?= e(url('pages/checkout.php')) ?>">Checkout</a>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
