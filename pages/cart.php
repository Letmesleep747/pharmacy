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
      <p class="empty" id="cart-empty">Your cart is empty. <a href="<?= e(url('pages/products.php')) ?>">Browse medications</a></p>
    <?php else: ?>
      <form method="post" id="cart-form" data-cart-api="<?= e(url('api/cart.php')) ?>">
        <?= csrf_field() ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Product</th><th>Qty</th><th>Price</th><th>Discount</th><th>Line</th></tr>
            </thead>
            <tbody id="cart-body">
              <?php foreach ($items as $item): ?>
                <tr data-product-id="<?= (int) $item['product']['id'] ?>">
                  <td>
                    <?= e($item['product']['name']) ?>
                    <?php if ($item['promo']): ?><div class="badge badge-ok" data-promo><?= e($item['promo']['name']) ?></div><?php endif; ?>
                  </td>
                  <td>
                    <input
                      type="number"
                      name="qty[<?= (int) $item['product']['id'] ?>]"
                      min="0"
                      value="<?= (int) $item['qty'] ?>"
                      style="width:4.5rem"
                      data-cart-qty
                      aria-label="Quantity for <?= e($item['product']['name']) ?>"
                    >
                  </td>
                  <td data-unit><?= e(money($item['unit'])) ?></td>
                  <td data-discount><?= e(money($item['discount'])) ?></td>
                  <td data-line><?= e(money($item['line_total'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p id="cart-totals">
          <strong>Subtotal</strong> <span data-subtotal><?= e(money($subtotal)) ?></span>
          · <strong>Discounts</strong> <span data-discount-total><?= e(money($discountTotal)) ?></span>
          · <strong>Total</strong> <span data-total><?= e(money($subtotal - $discountTotal)) ?></span>
        </p>
        <p class="muted">Quantities update automatically. Only one promotion is applied per product (the best active offer).</p>
        <p class="muted" id="cart-saving" hidden>Saving…</p>
        <button class="btn btn-danger" type="submit" name="action" value="clear">Clear</button>
        <a class="btn" id="cart-checkout" href="<?= e(url('pages/checkout.php')) ?>">Checkout</a>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
