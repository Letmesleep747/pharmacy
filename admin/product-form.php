<?php
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
$id = get_int('id');
$product = $id ? db_one($db, 'SELECT * FROM products WHERE id = ?', 'i', [$id]) : null;
$categories = all_categories($db);
$errors = [];

$name = $product['name'] ?? '';
$sku = $product['sku'] ?? '';
$description = $product['description'] ?? '';
$unitPrice = $product['unit_price'] ?? '';
$categoryId = (int) ($product['category_id'] ?? 0);
$isActive = (int) ($product['is_active'] ?? 1);
$requiresRx = (int) ($product['requires_prescription'] ?? 0);

if (is_post()) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $sku = strtoupper(trim((string) ($_POST['sku'] ?? '')));
    $description = trim((string) ($_POST['description'] ?? ''));
    $unitPrice = (string) ($_POST['unit_price'] ?? '');
    $categoryId = post_int('category_id');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $requiresRx = isset($_POST['requires_prescription']) ? 1 : 0;

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($sku === '') {
        $errors[] = 'SKU is required.';
    }
    if ($description === '') {
        $errors[] = 'Description is required.';
    }
    if (!is_numeric($unitPrice) || (float) $unitPrice < 0) {
        $errors[] = 'Enter a valid unit price.';
    }
    $cat = db_one($db, 'SELECT id FROM categories WHERE id = ?', 'i', [$categoryId]);
    if (!$cat) {
        $errors[] = 'Choose a category.';
    }
    $dup = db_one($db, 'SELECT id FROM products WHERE sku = ? AND id <> ?', 'si', [$sku, $id]);
    if ($dup) {
        $errors[] = 'That SKU is already in use.';
    }

    if (!$errors) {
        $price = round((float) $unitPrice, 2);
        if ($product) {
            db_exec(
                $db,
                'UPDATE products SET category_id=?, name=?, sku=?, description=?, unit_price=?, is_active=?, requires_prescription=? WHERE id=?',
                'isssdiii',
                [$categoryId, $name, $sku, $description, $price, $isActive, $requiresRx, $id]
            )->close();
            log_activity($db, 'product_updated', 'Updated product ' . $name, [
                'entity_type' => 'product',
                'entity_id' => $id,
                'details' => [
                    'sku' => $sku,
                    'is_active' => $isActive,
                    'requires_prescription' => $requiresRx,
                    'unit_price' => $price,
                ],
            ]);
            flash_set('success', 'Product updated.');
        } else {
            $stmt = db_exec(
                $db,
                'INSERT INTO products (category_id, name, sku, description, unit_price, is_active, requires_prescription) VALUES (?, ?, ?, ?, ?, ?, ?)',
                'isssdii',
                [$categoryId, $name, $sku, $description, $price, $isActive, $requiresRx]
            );
            $newId = (int) $stmt->insert_id;
            $stmt->close();
            $branches = all_branches($db, false);
            foreach ($branches as $branch) {
                db_exec(
                    $db,
                    'INSERT INTO branch_stock (branch_id, product_id, quantity, reorder_level) VALUES (?, ?, 0, 10)',
                    'ii',
                    [(int) $branch['id'], $newId]
                )->close();
            }
            log_activity($db, 'product_created', 'Created product ' . $name, [
                'entity_type' => 'product',
                'entity_id' => $newId,
                'details' => [
                    'sku' => $sku,
                    'is_active' => $isActive,
                    'requires_prescription' => $requiresRx,
                    'unit_price' => $price,
                ],
            ]);
            flash_set('success', 'Product created. Set branch stock next.');
        }
        redirect('admin/products.php');
    }
}

$title = $product ? 'Edit product' : 'Add product';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <h1><?= e($title) ?></h1>
    <?php foreach ($errors as $error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endforeach; ?>
    <form class="form form-wide" method="post">
      <?= csrf_field() ?>
      <label>Name <input type="text" name="name" required maxlength="160" value="<?= e($name) ?>"></label>
      <label>SKU <input type="text" name="sku" required maxlength="40" value="<?= e($sku) ?>"></label>
      <label>Category
        <select name="category_id" required>
          <option value="">Select</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Description <textarea name="description" rows="4" required><?= e($description) ?></textarea></label>
      <label>Unit price (RM) <input type="number" step="0.01" min="0" name="unit_price" required value="<?= e((string) $unitPrice) ?>"></label>
      <label class="check"><input type="checkbox" name="is_active" <?= $isActive ? 'checked' : '' ?>> Visible to customers</label>
      <label class="check"><input type="checkbox" name="requires_prescription" <?= $requiresRx ? 'checked' : '' ?>> Requires prescription (online orders need staff approval)</label>
      <button class="btn" type="submit">Save</button>
    </form>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
