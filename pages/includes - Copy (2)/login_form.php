<?php
/** @var string $heading */
/** @var string $intro */
/** @var string $error */
/** @var string|null $registerHref */
?>
<section class="section">
  <div class="container">
    <h1><?= e($heading) ?></h1>
    <p class="muted"><?= e($intro) ?></p>
    <?php if ($error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endif; ?>
    <form class="form" method="post" action="">
      <?= csrf_field() ?>
      <label>Email
        <input type="email" name="email" required autocomplete="username" value="<?= e((string) ($_POST['email'] ?? '')) ?>">
      </label>
      <label>Password
        <input type="password" name="password" required autocomplete="current-password">
      </label>
      <button class="btn" type="submit">Sign in</button>
    </form>
    <?php if (!empty($registerHref)): ?>
      <p>New customer? <a href="<?= e($registerHref) ?>">Create an account</a></p>
    <?php endif; ?>
  </div>
</section>
