<?php
require dirname(__DIR__) . '/includes/init.php';
require_storefront();
require_login();

if (user_role() !== 'customer') {
    flash_set('error', 'Only customer accounts can access the customer profile.');
    redirect('index.php');
}

$db = db();
$userId = (int) current_user()['id'];
$error = '';
$success = '';

$user = db_one(
    $db,
    'SELECT id, name, email, password_hash FROM users WHERE id = ? LIMIT 1',
    'i',
    [$userId]
);

if (!$user) {
    logout_user();
    flash_set('error', 'Your account could not be found. Please sign in again.');
    redirect('pages/login.php');
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
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        try {
            $stmt = db_exec(
                $db,
                'UPDATE users SET password_hash = ? WHERE id = ?',
                'si',
                [$newHash, $userId]
            );
            $stmt->close();

            $success = 'Your password has been changed successfully.';
            $user = db_one(
                $db,
                'SELECT id, name, email, password_hash FROM users WHERE id = ? LIMIT 1',
                'i',
                [$userId]
            );
        } catch (Throwable $e) {
            $error = 'Unable to change your password right now. Please try again.';
        }
    }
}

$title = 'My Profile';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="section">
  <div class="container">
    <h1>My profile</h1>
    <p class="muted">View your customer account details and update your password.</p>

    <?php if ($error): ?>
      <p class="flash flash-error"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
      <p class="flash flash-success"><?= e($success) ?></p>
    <?php endif; ?>

    <div class="grid">
      <article class="card">
        <h2>Account details</h2>
        <p><strong>Name</strong></p>
        <p><?= e($user['name']) ?></p>

        <p><strong>Email address</strong></p>
        <p><?= e($user['email']) ?></p>

        <p class="muted">If your name or email needs to be changed, please contact the pharmacy.</p>
      </article>

      <article class="card">
        <h2>Change password</h2>
        <form class="form" method="post" action="">
          <?= csrf_field() ?>

          <label>Current password
            <input type="password" name="current_password" required autocomplete="current-password">
          </label>

          <label>New password
            <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
          </label>

          <label>Confirm new password
            <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
          </label>

          <p class="muted">Use at least 8 characters. Your current password is required to make this change.</p>
          <button class="btn" type="submit">Change password</button>
        </form>
      </article>
    </div>

    <p><a class="btn btn-secondary" href="<?= e(url('index.php')) ?>">Back to home</a></p>
  </div>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
