<?php
require dirname(__DIR__) . '/includes/init.php';
require_storefront();

$db = db();
$promos = active_promotions($db);

$title = 'Promotions';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h1>Promotions and discounts</h1>
    <p class="muted">Only current, active promotions are offered to customers at checkout.</p>
    <h2>Running now</h2>
    <?php if (!$promos): ?>
      <p class="empty">No promotions are active today.</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($promos as $promo): ?>
          <article class="card">
            <span class="badge badge-ok">Active</span>
            <h3><?= e($promo['name']) ?></h3>
            <p><?= e($promo['description']) ?></p>
            <p class="price">
              <?= $promo['discount_type'] === 'percent'
                  ? e((float) $promo['discount_value'] . '% off')
                  : e(money((float) $promo['discount_value']) . ' off') ?>
            </p>
            <p class="muted"><?= e($promo['start_date']) ?> to <?= e($promo['end_date']) ?></p>
            <p class="muted">Applies to: <?= e($promo['product_names'] ?: 'No products linked') ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
