<?php
require dirname(__DIR__) . '/includes/init.php';
require_storefront();

$db = db();
$id = get_int('id');
$product = $id ? find_product($db, $id) : null;
if (!$product) {
    http_response_code(404);
    $title = 'Not found';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<section class="section"><div class="container"><p class="empty">That product is not in the catalogue.</p></div></section>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$availability = product_availability($db, $id);
$promo = best_promotion_for_product($db, $id);
$price = (float) $product['unit_price'];
$discount = $promo ? promotion_discount_amount($promo, $price) : 0;

if (is_post() && ($_POST['action'] ?? '') === 'add') {
    $qty = max(1, post_int('qty', 1));
    cart_add($id, $qty);
    flash_set('success', $product['name'] . ' added to your cart.');
    redirect('pages/product.php?id=' . $id);
}

$title = $product['name'];
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
  <div class="container product-hero">
    <div>
      <span class="badge"><?= e($product['category_name']) ?></span>
      <h1><?= e($product['name']) ?></h1>
      <p class="muted">SKU <?= e($product['sku']) ?></p>
      <p><?= e($product['description']) ?></p>
      <p class="price" style="font-size:1.4rem">
        <?= e(money($price - $discount)) ?>
        <?php if ($discount > 0): ?><span class="muted"><s><?= e(money($price)) ?></s></span><?php endif; ?>
      </p>
      <?php if ($promo): ?>
        <p><span class="badge badge-ok"><?= e($promo['name']) ?></span> <?= e($promo['description']) ?></p>
      <?php endif; ?>
      <form method="post" class="filters">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label>Qty
          <input type="number" name="qty" min="1" value="1" style="width:5rem">
        </label>
        <button class="btn" type="submit">Add to cart</button>
      </form>
    </div>
    <div>
      <h2>Availability by branch</h2>
      <p class="muted">Use this list instead of calling each branch individually.</p>
      <ul class="availability">
        <?php foreach ($availability as $row):
            $qty = (int) $row['quantity'];
            $inStock = $qty > 0;
            ?>
          <li>
            <div>
              <strong><?= e($row['name']) ?></strong>
              <div class="muted"><?= e($row['address']) ?></div>
              <div class="muted"><?= e($row['phone']) ?> · <?= e($row['opening_hours']) ?></div>
            </div>
            <div>
              <?php if ($inStock): ?>
                <span class="badge badge-ok">Available</span>
                <div class="muted"><?= $qty ?> in stock</div>
              <?php else: ?>
                <span class="badge badge-danger">Out of stock</span>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
