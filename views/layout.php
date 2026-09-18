<?php /** @var string $__content @var string $__title */ ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($__title) ?> · <?= h(cfg('app_name', 'Timeminator')) ?></title>
<link rel="stylesheet" href="<?= h(asset('app.css')) ?>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= h(route('dashboard')) ?>">⏱ <?= h(cfg('app_name', 'Timeminator')) ?></a>
  <nav class="mainnav">
    <?php require __DIR__ . '/partials/nav.php'; ?>
  </nav>
  <div class="userbox">
    <span class="who"><?= h(Auth::user()['display_name'] ?: Auth::user()['username']) ?>
      <em><?= h(Auth::user()['role_label'] ?? '') ?></em></span>
    <a class="btn btn-ghost" href="<?= h(route('logout')) ?>">Abmelden</a>
  </div>
</header>

<main class="wrap">
  <?php foreach (flash_take() as $f): ?>
    <div class="flash flash-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
  <?php endforeach; ?>
  <h1 class="page-title"><?= h($__title) ?></h1>
  <?= $__content ?>
</main>

<footer class="foot">
  <?= h(cfg('app_name', 'Timeminator')) ?> Community v<?= h(app_version()) ?>
  &middot; <a href="https://felixschaller.com" target="_blank" rel="noopener">made by FelixSchallerCOM</a>
  &middot; <a href="<?= h(cfg('pro_url', 'https://timeminator.felixschaller.com')) ?>" target="_blank" rel="noopener">mehr Funktionen mit Timeminator Pro</a>
</footer>
<script src="<?= h(asset('app.js')) ?>"></script>
</body>
</html>
