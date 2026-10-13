<?php /** @var bool $sent @var ?string $error */ ?>
<!doctype html>
<html lang="<?= h(I18n::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(t('reset.request_title', 'Passwort zuruecksetzen')) ?> · <?= h(cfg('app_name', 'Timeminator')) ?></title>
<link rel="stylesheet" href="<?= h(asset('app.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card">
  <div class="auth-brand">⏱ <?= h(cfg('app_name', 'Timeminator')) ?></div>
  <h1 class="auth-sub"><?= h(t('reset.request_h1', 'Passwort zuruecksetzen')) ?></h1>
  <?php if ($sent): ?>
    <div class="flash flash-ok"><?= h(t('reset.flash_sent', 'Wenn das Konto existiert, haben wir eine Mail mit dem Reset-Link geschickt. Pruefe dein Postfach.')) ?></div>
    <p class="muted"><a href="<?= h(route('login')) ?>"><?= h(t('reset.back_to_login', 'Zurueck zur Anmeldung')) ?></a></p>
  <?php else: ?>
    <?php if ($error): ?>
      <div class="flash flash-err"><?= h($error) ?></div>
    <?php endif; ?>
    <p class="muted"><?= h(t('reset.request_intro', 'Gib deinen Benutzernamen oder deine E-Mail-Adresse ein. Wir schicken dir einen Link zum Setzen eines neuen Passworts.')) ?></p>
    <form method="post" action="<?= h(route('reset_request')) ?>" class="auth-form">
      <?= csrf_field() ?>
      <label><?= h(t('reset.identifier_label', 'Benutzername oder E-Mail')) ?>
        <input type="text" name="identifier" autocomplete="username" autofocus required>
      </label>
      <button class="btn btn-primary btn-block" type="submit"><?= h(t('reset.send_submit', 'Reset-Link anfordern')) ?></button>
    </form>
    <p class="muted" style="margin-top:16px"><a href="<?= h(route('login')) ?>"><?= h(t('reset.back_to_login', 'Zurueck zur Anmeldung')) ?></a></p>
  <?php endif; ?>
  <?php require __DIR__ . '/../partials/lang_switcher.php'; ?>
</div>
</body>
</html>
