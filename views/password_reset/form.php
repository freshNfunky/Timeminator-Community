<?php /** @var string $token @var bool $invalid @var ?string $error */ ?>
<!doctype html>
<html lang="<?= h(I18n::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(t('reset.form_title', 'Neues Passwort setzen')) ?> · <?= h(cfg('app_name', 'Timeminator')) ?></title>
<link rel="stylesheet" href="<?= h(asset('app.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card">
  <div class="auth-brand">⏱ <?= h(cfg('app_name', 'Timeminator')) ?></div>
  <h1 class="auth-sub"><?= h(t('reset.form_h1', 'Neues Passwort setzen')) ?></h1>
  <?php if ($invalid): ?>
    <div class="flash flash-err"><?= h(t('reset.err_token_invalid', 'Der Link ist abgelaufen oder wurde bereits verwendet. Bitte fordere einen neuen an.')) ?></div>
    <p><a class="btn btn-ghost" href="<?= h(route('reset_request')) ?>"><?= h(t('reset.new_request', 'Neuen Link anfordern')) ?></a></p>
  <?php else: ?>
    <?php if ($error): ?>
      <div class="flash flash-err"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= h(route('reset_form')) ?>" class="auth-form">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <label><?= h(t('reset.password_label', 'Neues Passwort')) ?>
        <input type="password" name="password" minlength="8" autocomplete="new-password" autofocus required>
      </label>
      <label><?= h(t('reset.password_confirm_label', 'Passwort wiederholen')) ?>
        <input type="password" name="password_confirm" minlength="8" autocomplete="new-password" required>
      </label>
      <p class="muted"><?= h(t('reset.password_hint', 'Mindestens 8 Zeichen.')) ?></p>
      <button class="btn btn-primary btn-block" type="submit"><?= h(t('reset.set_submit', 'Passwort speichern')) ?></button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
