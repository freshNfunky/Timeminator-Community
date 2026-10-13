<?php /** @var ?string $error */ ?>
<!doctype html>
<html lang="<?= h(I18n::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(t('login.title')) ?> · <?= h(cfg('app_name', 'Timeminator')) ?></title>
<link rel="stylesheet" href="<?= h(asset('app.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card">
  <div class="auth-brand">⏱ <?= h(cfg('app_name', 'Timeminator')) ?></div>
  <p class="auth-sub"><?= h(t('install.intro')) ?></p>
  <?php if ($error): ?>
    <div class="flash flash-err"><?= h($error) ?></div>
  <?php endif; ?>
  <form method="post" action="<?= h(route('login')) ?>" class="auth-form">
    <?= csrf_field() ?>
    <label><?= h(t('login.username')) ?>
      <input type="text" name="username" autocomplete="username" autofocus required>
    </label>
    <label><?= h(t('login.password')) ?>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-primary btn-block" type="submit"><?= h(t('login.submit')) ?></button>
  </form>
  <?php require __DIR__ . '/partials/lang_switcher.php'; ?>
</div>
</body>
</html>
