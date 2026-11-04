<?php
/**
 * Staff account — change own password.
 */
require dirname(__DIR__) . '/includes/init.php';
require_staff_portal();

$db = db();
$userId = (int) current_user()['id'];
$error = '';
$success = '';

$user = db_one(
    $db,
    'SELECT id, name, email, password_hash, branch_id FROM users WHERE id = ? AND role = ? LIMIT 1',
    'is',
    [$userId, 'staff']
);

if (!$user) {
    logout_user();
    flash_set('error', 'Your staff account could not be found. Please sign in again.');
    redirect('staff/login.php');
}

if (is_post()) {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'Please complete all password fields.';
    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
        $error = 'Your current password is incorrect.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Your new password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'The new password and confirmation do not match.';
    } elseif ($currentPassword === $newPassword) {
        $error = 'Your new password must be different from your current password.';
    } else {
        try {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            db_exec($db, 'UPDATE users SET password_hash = ? WHERE id = ? AND role = ?', 'sis', [$newHash, $userId, 'staff'])->close();
            log_activity($db, 'password_changed', 'Staff changed their own password', [
                'entity_type' => 'user',
                'entity_id' => $userId,
                'branch_id' => isset($user['branch_id']) ? (int) $user['branch_id'] : null,
            ]);
            $success = 'Your password has been changed successfully.';
            $user = db_one(
                $db,
                'SELECT id, name, email, password_hash, branch_id FROM users WHERE id = ? AND role = ? LIMIT 1',
                'is',
                [$userId, 'staff']
            );
        } catch (Throwable $e) {
            $error = 'Unable to change your password right now. Please try again.';
        }
    }
}

$title = 'My account';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/staff_nav.php'; ?>
  <div>
    <h1>My account</h1>
    <p class="muted">View your staff login details and change your password.</p>
    <?php if ($error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="flash flash-success"><?= e($success) ?></p><?php endif; ?>

    <article class="card" style="margin-bottom:16px">
      <h2 style="margin-top:0">Account</h2>
      <p><strong>Name:</strong> <?= e($user['name']) ?></p>
      <p><strong>Email:</strong> <?= e($user['email']) ?></p>
      <p><strong>Branch:</strong> <?= e(user_branch_name() ?: 'Not assigned') ?></p>
    </article>

    <form class="form" method="post">
      <?= csrf_field() ?>
      <h2>Change password</h2>
      <label>Current password
        <input type="password" name="current_password" required autocomplete="current-password">
      </label>
      <label>New password
        <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
      </label>
      <label>Confirm new password
        <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
      </label>
      <button class="btn" type="submit">Save new password</button>
    </form>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
