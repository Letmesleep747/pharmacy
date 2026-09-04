<?php
require dirname(__DIR__) . '/includes/init.php';
require_storefront();

$db = db();
$q = trim((string) ($_GET['q'] ?? ''));
$categoryId = get_int('category');
$products = find_products($db, $q, $categoryId);
$categories = all_categories($db);

$title = 'Medications';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h1>Medications and products</h1>
    <form class="filters" method="get">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or SKU" aria-label="Search">
      <select name="category" aria-label="Category">
        <option value="0">All categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Filter</button>
    </form>
    <?php if (!$products): ?>
      <p class="empty">No products match your search. Try a different name, such as Paracetamol 500mg.</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($products as $product):
            $promo = best_promotion_for_product($db, (int) $product['id']);
            $price = (float) $product['unit_price'];
            $discount = $promo ? promotion_discount_amount($promo, $price) : 0;
            ?>
          <article class="card">
            <span class="badge"><?= e($product['category_name']) ?></span>
            <h3><a href="<?= e(url('pages/product.php?id=' . (int) $product['id'])) ?>"><?= e($product['name']) ?></a></h3>
            <p class="muted"><?= e(strlen($product['description']) > 90 ? substr($product['description'], 0, 87) . '…' : $product['description']) ?></p>
            <p class="price">
              <?= e(money($price - $discount)) ?>
              <?php if ($discount > 0): ?><span class="muted"><s><?= e(money($price)) ?></s></span><?php endif; ?>
            </p>
            <a class="btn btn-small" href="<?= e(url('pages/product.php?id=' . (int) $product['id'])) ?>">View branches</a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
