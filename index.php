<?php
/**
 * Customer storefront home page.
 * Loads featured medications, active promotions and branches from MySQL.
 */
require __DIR__ . '/includes/init.php';
require_storefront();

$db = db();
$q = trim((string) ($_GET['q'] ?? ''));
$promos = active_promotions($db);
$featured = find_products($db, $q === '' ? '' : $q);
if ($q === '') {
    $featured = array_slice($featured, 0, 8);
}
$branches = all_branches($db);

$title = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container">
    <p class="badge">Healthcare · Multi-branch inventory</p>
    <h1>Find your medication at the branch that has it today.</h1>
    <p>Search the catalogue, see live stock at each pharmacy, and pick up promotions at checkout or in store.</p>
    <form class="search-bar" action="<?= e(url('pages/products.php')) ?>" method="get">
      <input type="search" name="q" placeholder="Try Paracetamol 500mg" value="<?= e($q) ?>" aria-label="Search medications">
      <button class="btn" type="submit">Search</button>
    </form>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <h2><?= $q === '' ? 'Featured medications' : 'Search results' ?></h2>
        <p class="muted">Prices shown before branch pickup. Active promotions apply automatically.</p>
      </div>
      <a href="<?= e(url('pages/products.php')) ?>">View all</a>
    </div>
    <?php if (!$featured): ?>
      <p class="empty">No medications matched that search.</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($featured as $product):
            $promo = best_promotion_for_product($db, (int) $product['id']);
            $price = (float) $product['unit_price'];
            $discount = $promo ? promotion_discount_amount($promo, $price) : 0;
            ?>
          <article class="card">
            <span class="badge"><?= e($product['category_name']) ?></span>
            <h3><a href="<?= e(url('pages/product.php?id=' . (int) $product['id'])) ?>"><?= e($product['name']) ?></a></h3>
            <p class="muted"><?= e($product['sku']) ?></p>
            <p class="price">
              <?= e(money($price - $discount)) ?>
              <?php if ($discount > 0): ?><span class="muted"><s><?= e(money($price)) ?></s></span><?php endif; ?>
            </p>
            <?php if ($promo): ?><span class="badge badge-ok"><?= e($promo['name']) ?></span><?php endif; ?>
            <a class="btn btn-small" href="<?= e(url('pages/product.php?id=' . (int) $product['id'])) ?>">Branch availability</a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<section class="section" style="padding-top:0">
  <div class="container grid-2">
    <div class="panel" style="padding:20px">
      <h2>Active promotions</h2>
      <?php if (!$promos): ?>
        <p class="muted">There are no promotions running today.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($promos as $promo): ?>
            <li>
              <strong><?= e($promo['name']) ?></strong>
              — <?= $promo['discount_type'] === 'percent' ? e(rtrim(rtrim($promo['discount_value'], '0'), '.') . '% off') : e(money((float) $promo['discount_value']) . ' off') ?>
              until <?= e($promo['end_date']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= e(url('pages/promotions.php')) ?>">All promotions</a>
      <?php endif; ?>
    </div>
    <div class="panel" style="padding:20px">
      <h2>Our branches</h2>
      <ul>
        <?php foreach ($branches as $branch): ?>
          <li><strong><?= e($branch['name']) ?></strong> — <?= e($branch['address']) ?></li>
        <?php endforeach; ?>
      </ul>
      <a href="<?= e(url('pages/branches.php')) ?>">Branch details</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
