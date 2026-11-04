<?php
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
$id = get_int('id');
$edit = $id ? db_one($db, 'SELECT * FROM branches WHERE id = ?', 'i', [$id]) : null;
$errors = [];

$name = $edit['name'] ?? '';
$address = $edit['address'] ?? '';
$phone = $edit['phone'] ?? '';
$hours = $edit['opening_hours'] ?? '9:00–18:00';
$isActive = (int) ($edit['is_active'] ?? 1);

if (is_post() && ($_POST['action'] ?? '') === 'save') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $hours = trim((string) ($_POST['opening_hours'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $editId = post_int('id');

    if ($name === '' || $address === '' || $phone === '' || $hours === '') {
        $errors[] = 'All branch fields are required.';
    } else {
        if ($editId > 0) {
            db_exec(
                $db,
                'UPDATE branches SET name=?, address=?, phone=?, opening_hours=?, is_active=? WHERE id=?',
                'ssssii',
                [$name, $address, $phone, $hours, $isActive, $editId]
            )->close();
            flash_set('success', 'Branch updated.');
        } else {
            $stmt = db_exec(
                $db,
                'INSERT INTO branches (name, address, phone, opening_hours, is_active) VALUES (?, ?, ?, ?, ?)',
                'ssssi',
                [$name, $address, $phone, $hours, $isActive]
            );
            $newId = (int) $stmt->insert_id;
            $stmt->close();
            foreach (all_products($db) as $product) {
                db_exec(
                    $db,
                    'INSERT INTO branch_stock (branch_id, product_id, quantity, reorder_level) VALUES (?, ?, 0, 10)',
                    'ii',
                    [$newId, (int) $product['id']]
                )->close();
            }
            flash_set('success', 'Branch added. Stock rows created at quantity 0.');
        }
        redirect('admin/branches.php');
    }
}

$branches = db_all($db, 'SELECT * FROM branches ORDER BY name');
$title = 'Branches';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <h1>Pharmacy branches</h1>
    <?php foreach ($errors as $error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endforeach; ?>
    <form class="form" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
      <h2><?= $edit ? 'Edit branch' : 'Add branch' ?></h2>
      <label>Name <input type="text" name="name" required value="<?= e($name) ?>"></label>
      <label>Address <input type="text" name="address" required value="<?= e($address) ?>"></label>
      <label>Phone <input type="text" name="phone" required value="<?= e($phone) ?>"></label>
      <label>Opening hours <input type="text" name="opening_hours" required value="<?= e($hours) ?>"></label>
      <label class="check"><input type="checkbox" name="is_active" <?= $isActive ? 'checked' : '' ?>> Active</label>
      <button class="btn" type="submit">Save branch</button>
      <?php if ($edit): ?><a class="btn btn-secondary" href="<?= e(url('admin/branches.php')) ?>">Cancel</a><?php endif; ?>
    </form>
    <h2>All branches</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Address</th><th>Phone</th><th>Hours</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($branches as $branch): ?>
            <tr>
              <td><?= e($branch['name']) ?></td>
              <td><?= e($branch['address']) ?></td>
              <td><?= e($branch['phone']) ?></td>
              <td><?= e($branch['opening_hours']) ?></td>
              <td><?= (int) $branch['is_active'] ? 'Active' : 'Inactive' ?></td>
              <td><a href="<?= e(url('admin/branches.php?id=' . (int) $branch['id'])) ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
