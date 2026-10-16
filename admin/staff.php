<?php
/**
 * Administrator: create staff accounts for a branch, edit assignment, reset password.
 * Passwords are stored as bcrypt hashes and are never displayed.
 */
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
$branches = all_branches($db, false);
$editId = get_int('id');
$resetId = get_int('reset');
$errors = [];

$edit = $editId
    ? db_one($db, 'SELECT id, name, email, role, branch_id FROM users WHERE id = ? AND role = ?', 'is', [$editId, 'staff'])
    : null;
$resetUser = $resetId
    ? db_one($db, 'SELECT id, name, email FROM users WHERE id = ? AND role = ?', 'is', [$resetId, 'staff'])
    : null;

$name = $edit['name'] ?? '';
$email = $edit['email'] ?? '';
$branchId = (int) ($edit['branch_id'] ?? 0);

if (is_post()) {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $branchId = post_int('branch_id');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        $saveId = post_int('id');

        if ($name === '' || strlen($name) > 120) {
            $errors[] = 'Please enter the staff member’s name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        $branch = $branchId > 0
            ? db_one($db, 'SELECT id FROM branches WHERE id = ?', 'i', [$branchId])
            : null;
        if (!$branch) {
            $errors[] = 'Choose a home branch for this staff account.';
        }

        $existing = db_one($db, 'SELECT id, role FROM users WHERE email = ? LIMIT 1', 's', [$email]);
        if ($existing && (int) $existing['id'] !== $saveId) {
            $errors[] = 'An account with that email already exists.';
        }

        if ($saveId <= 0) {
            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
            if ($password !== $confirm) {
                $errors[] = 'Passwords do not match.';
            }
        } else {
            $target = db_one($db, 'SELECT id FROM users WHERE id = ? AND role = ?', 'is', [$saveId, 'staff']);
            if (!$target) {
                $errors[] = 'Staff account not found.';
            }
        }

        if (!$errors) {
            if ($saveId > 0) {
                db_exec(
                    $db,
                    'UPDATE users SET name = ?, email = ?, branch_id = ? WHERE id = ? AND role = ?',
                    'ssiis',
                    [$name, $email, $branchId, $saveId, 'staff']
                )->close();
                flash_set('success', 'Staff account updated. Password is unchanged (use Reset password if needed).');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                db_exec(
                    $db,
                    'INSERT INTO users (name, email, password_hash, role, branch_id) VALUES (?, ?, ?, ?, ?)',
                    'ssssi',
                    [$name, $email, $hash, 'staff', $branchId]
                )->close();
                flash_set('success', 'Staff account created for the selected branch. Share the temporary password securely — it cannot be viewed again.');
            }
            redirect('admin/staff.php');
        }
    }

    if ($action === 'reset_password') {
        $resetId = post_int('id');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        $resetUser = db_one($db, 'SELECT id, name, email FROM users WHERE id = ? AND role = ?', 'is', [$resetId, 'staff']);

        if (!$resetUser) {
            $errors[] = 'Staff account not found.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors && $resetUser) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db_exec(
                $db,
                'UPDATE users SET password_hash = ? WHERE id = ? AND role = ?',
                'sis',
                [$hash, $resetId, 'staff']
            )->close();
            flash_set('success', 'Password reset for ' . $resetUser['email'] . '. Share the new temporary password securely — it is not stored in plain text.');
            redirect('admin/staff.php');
        }
    }
}

$staff = db_all(
    $db,
    'SELECT u.id, u.name, u.email, u.created_at, u.branch_id, b.name AS branch_name
     FROM users u
     LEFT JOIN branches b ON b.id = u.branch_id
     WHERE u.role = ?
     ORDER BY b.name IS NULL, b.name, u.name',
    's',
    ['staff']
);

$title = 'Staff accounts';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <h1>Staff accounts</h1>
    <p class="muted">Create a counter login for each branch. Passwords are hashed and cannot be read back — use Reset password if someone forgets theirs.</p>
    <?php foreach ($errors as $error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endforeach; ?>

    <?php if ($resetUser): ?>
      <form class="form" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="id" value="<?= (int) $resetUser['id'] ?>">
        <h2>Reset password — <?= e($resetUser['name']) ?></h2>
        <p class="muted"><?= e($resetUser['email']) ?></p>
        <label>New temporary password
          <input type="password" name="password" required minlength="8" autocomplete="new-password">
        </label>
        <label>Confirm new password
          <input type="password" name="confirm" required minlength="8" autocomplete="new-password">
        </label>
        <button class="btn" type="submit">Save new password</button>
        <a class="btn btn-secondary" href="<?= e(url('admin/staff.php')) ?>">Cancel</a>
      </form>
    <?php else: ?>
      <form class="form" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
        <h2><?= $edit ? 'Edit staff account' : 'Add staff account' ?></h2>
        <label>Full name
          <input type="text" name="name" required maxlength="120" value="<?= e($name) ?>">
        </label>
        <label>Email (staff sign-in)
          <input type="email" name="email" required maxlength="190" value="<?= e($email) ?>">
        </label>
        <label>Home branch
          <select name="branch_id" required>
            <option value="0">Select branch</option>
            <?php foreach ($branches as $branch): ?>
              <option value="<?= (int) $branch['id'] ?>" <?= $branchId === (int) $branch['id'] ? 'selected' : '' ?>>
                <?= e($branch['name']) ?><?= (int) $branch['is_active'] === 0 ? ' (inactive)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php if (!$edit): ?>
          <label>Temporary password
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
          </label>
          <label>Confirm password
            <input type="password" name="confirm" required minlength="8" autocomplete="new-password">
          </label>
        <?php else: ?>
          <p class="muted">To change the password, use <a href="<?= e(url('admin/staff.php?reset=' . (int) $edit['id'])) ?>">Reset password</a>.</p>
        <?php endif; ?>
        <button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Create staff account' ?></button>
        <?php if ($edit): ?><a class="btn btn-secondary" href="<?= e(url('admin/staff.php')) ?>">Cancel</a><?php endif; ?>
      </form>
    <?php endif; ?>

    <h2>All staff</h2>
    <?php if (!$staff): ?>
      <p class="empty">No staff accounts yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Email</th><th>Home branch</th><th>Created</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($staff as $row): ?>
              <tr>
                <td><?= e($row['name']) ?></td>
                <td><?= e($row['email']) ?></td>
                <td><?= e($row['branch_name'] ?: '— unassigned —') ?></td>
                <td><?= e((string) $row['created_at']) ?></td>
                <td>
                  <a href="<?= e(url('admin/staff.php?id=' . (int) $row['id'])) ?>">Edit</a>
                  ·
                  <a href="<?= e(url('admin/staff.php?reset=' . (int) $row['id'])) ?>">Reset password</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
