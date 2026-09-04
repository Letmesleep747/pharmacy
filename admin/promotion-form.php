<?php
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
$id = get_int('id');
$promo = $id ? db_one($db, 'SELECT * FROM promotions WHERE id = ?', 'i', [$id]) : null;
$products = all_products($db);
$selected = [];
if ($id) {
    foreach (db_all($db, 'SELECT product_id FROM promotion_products WHERE promotion_id = ?', 'i', [$id]) as $row) {
        $selected[] = (int) $row['product_id'];
    }
}

$errors = [];
$name = $promo['name'] ?? '';
$description = $promo['description'] ?? '';
$discountType = $promo['discount_type'] ?? 'percent';
$discountValue = $promo['discount_value'] ?? '';
$startDate = $promo['start_date'] ?? date('Y-m-d');
$endDate = $promo['end_date'] ?? date('Y-m-d', strtotime('+14 days'));
$isActive = (int) ($promo['is_active'] ?? 1);

if (is_post()) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $discountType = (string) ($_POST['discount_type'] ?? 'percent');
    $discountValue = (string) ($_POST['discount_value'] ?? '');
    $startDate = (string) ($_POST['start_date'] ?? '');
    $endDate = (string) ($_POST['end_date'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $selected = array_map('intval', $_POST['products'] ?? []);

    if ($name === '' || $description === '') {
        $errors[] = 'Name and description are required.';
    }
    if (!in_array($discountType, ['percent', 'fixed'], true)) {
        $errors[] = 'Choose a valid discount type.';
    }
    if (!is_numeric($discountValue) || (float) $discountValue <= 0) {
        $errors[] = 'Enter a discount value greater than zero.';
    }
    if ($discountType === 'percent' && (float) $discountValue > 100) {
        $errors[] = 'Percentage discount cannot exceed 100.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $errors[] = 'Enter valid start and end dates.';
    } elseif ($endDate < $startDate) {
        $errors[] = 'End date cannot be before the start date.';
    }
    if ($selected === []) {
        $errors[] = 'Select at least one product.';
    }

    if (!$errors) {
        $value = round((float) $discountValue, 2);
        $db->begin_transaction();
        try {
            if ($promo) {
                db_exec(
                    $db,
                    'UPDATE promotions SET name=?, description=?, discount_type=?, discount_value=?, start_date=?, end_date=?, is_active=? WHERE id=?',
                    'sssdssii',
                    [$name, $description, $discountType, $value, $startDate, $endDate, $isActive, $id]
                )->close();
                db_exec($db, 'DELETE FROM promotion_products WHERE promotion_id = ?', 'i', [$id])->close();
                $promoId = $id;
            } else {
                $stmt = db_exec(
                    $db,
                    'INSERT INTO promotions (name, description, discount_type, discount_value, start_date, end_date, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    'sssdssi',
                    [$name, $description, $discountType, $value, $startDate, $endDate, $isActive]
                );
                $promoId = (int) $stmt->insert_id;
                $stmt->close();
            }
            foreach ($selected as $productId) {
                if ($productId <= 0) {
                    continue;
                }
                db_exec(
                    $db,
                    'INSERT INTO promotion_products (promotion_id, product_id) VALUES (?, ?)',
                    'ii',
                    [$promoId, $productId]
                )->close();
            }
            $db->commit();
            flash_set('success', 'Promotion saved.');
            redirect('admin/promotions.php');
        } catch (Throwable $e) {
            $db->rollback();
            $errors[] = 'Could not save the promotion.';
        }
    }
}

$title = $promo ? 'Edit promotion' : 'New promotion';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <h1><?= e($title) ?></h1>
    <?php foreach ($errors as $error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endforeach; ?>
    <form class="form form-wide" method="post">
      <?= csrf_field() ?>
      <label>Promotion name <input type="text" name="name" required maxlength="160" value="<?= e($name) ?>"></label>
      <label>Description <textarea name="description" rows="3" required><?= e($description) ?></textarea></label>
      <label>Discount type
        <select name="discount_type">
          <option value="percent" <?= $discountType === 'percent' ? 'selected' : '' ?>>Percent</option>
          <option value="fixed" <?= $discountType === 'fixed' ? 'selected' : '' ?>>Fixed amount (RM)</option>
        </select>
      </label>
      <label>Discount value <input type="number" step="0.01" min="0.01" name="discount_value" required value="<?= e((string) $discountValue) ?>"></label>
      <label>Start date <input type="date" name="start_date" required value="<?= e($startDate) ?>"></label>
      <label>End date <input type="date" name="end_date" required value="<?= e($endDate) ?>"></label>
      <label><input type="checkbox" name="is_active" <?= $isActive ? 'checked' : '' ?>> Active (customers only see it during the date range)</label>
      <fieldset>
        <legend>Applicable products</legend>
        <div class="check-grid">
          <?php foreach ($products as $product): ?>
            <label>
              <input type="checkbox" name="products[]" value="<?= (int) $product['id'] ?>" <?= in_array((int) $product['id'], $selected, true) ? 'checked' : '' ?>>
              <?= e($product['name']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <button class="btn" type="submit">Save promotion</button>
    </form>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
