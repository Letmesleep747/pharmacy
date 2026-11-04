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

    <style>
      .profile-layout { max-width: 1050px; }
      .profile-details { width: 100%; box-sizing: border-box; margin-bottom: 20px; padding: 30px; }
      .profile-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 22px; margin: 24px 0; }
      .profile-info p { margin: 8px 0 0; overflow-wrap: anywhere; }
      .profile-password { width: 100%; box-sizing: border-box; margin-bottom: 24px; padding: 0; overflow: hidden; }
      .profile-password summary { cursor: pointer; list-style: none; display: flex; justify-content: space-between; align-items: center; padding: 24px 30px; font-weight: 700; font-size: 1.3rem; }
      .profile-password summary::-webkit-details-marker { display: none; }
      .profile-password summary::after { content: '+'; font-size: 1.5rem; }
      .profile-password[open] summary::after { content: '−'; }
      .profile-password .password-content { padding: 0 30px 30px; }
      .profile-password .form { max-width: 540px; }
      @media (max-width: 600px) { .profile-details { padding: 22px; } .profile-password summary { padding: 20px 22px; } .profile-password .password-content { padding: 0 22px 22px; } }
    </style>

    <div class="profile-layout">
      <article class="card profile-details">
        <h2>Account details</h2>
        <div class="profile-info">
          <div>
            <strong>Name</strong>
            <p><?= e($user['name']) ?></p>
          </div>
          <div>
            <strong>Email address</strong>
            <p><?= e($user['email']) ?></p>
          </div>
        </div>
        <p class="muted">If your name or email needs to be changed, please contact the pharmacy.</p>
      </article>

      <details class="card profile-password" <?= $error !== '' ? 'open' : '' ?>>
        <summary>Change password</summary>
        <div class="password-content">
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
            <button class="btn" type="submit">Update password</button>
          </form>
        </div>
      </details>
    </div>

    <p><a class="btn btn-secondary" href="<?= e(url('index.php')) ?>">Back to home</a></p>
  </div>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
