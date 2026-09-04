<?php
require dirname(__DIR__) . '/includes/init.php';
require_storefront();

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$name = '';
$email = '';

if (is_post()) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if ($name === '' || strlen($name) > 120) {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    $db = db();
    if (!$errors) {
        $existing = db_one($db, 'SELECT id FROM users WHERE email = ?', 's', [$email]);
        if ($existing) {
            $errors[] = 'An account with that email already exists.';
        }
    }
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db_exec(
            $db,
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
            'ssss',
            [$name, $email, $hash, 'customer']
        );
        $id = (int) $stmt->insert_id;
        $stmt->close();
        login_user(['id' => $id, 'name' => $name, 'email' => $email, 'role' => 'customer']);
        flash_set('success', 'Your account is ready.');
        redirect('index.php');
    }
}

$title = 'Register';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h1>Create a customer account</h1>
    <?php foreach ($errors as $error): ?>
      <p class="flash flash-error"><?= e($error) ?></p>
    <?php endforeach; ?>
    <form class="form" method="post">
      <?= csrf_field() ?>
      <label>Full name
        <input type="text" name="name" required maxlength="120" value="<?= e($name) ?>">
      </label>
      <label>Email
        <input type="email" name="email" required maxlength="190" value="<?= e($email) ?>">
      </label>
      <label>Password
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
      </label>
      <label>Confirm password
        <input type="password" name="confirm" required minlength="8" autocomplete="new-password">
      </label>
      <button class="btn" type="submit">Register</button>
    </form>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
