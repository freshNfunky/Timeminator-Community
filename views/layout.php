<?php /** @var string $__content @var string $__title */ ?>
<?php
$__nonce  = csp_nonce();
$__colors = collect_theme_colors();
// Admin -> System -> Darstellung. "system" (default) follows the browser's
// prefers-color-scheme; "light" / "dark" force the respective palette via
// the `data-theme` attribute the stylesheet already reads.
$__theme     = (string) Settings::get('theme_preference', 'system');
$__themeAttr = in_array($__theme, ['light', 'dark'], true) ? ' data-theme="' . $__theme . '"' : '';
$__banner    = Banners::isVisible();
?>
<!doctype html>
<html lang="de"<?= $__themeAttr ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($__title) ?> · <?= h(cfg('app_name', 'Timeminator')) ?></title>
<link rel="stylesheet" href="<?= h(asset('app.css')) ?>">
<?php if ($__colors): ?>
<style nonce="<?= h($__nonce) ?>">
<?php foreach ($__colors as $__c): ?>
.dot[data-color="<?= h($__c) ?>"] { background-color: <?= h($__c) ?>; }
<?php endforeach; ?>
</style>
<?php endif; ?>
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

<?php require __DIR__ . '/partials/update_banner.php'; ?>

<div class="shell<?= $__banner ? ' has-aside' : '' ?>">
<main class="wrap">
  <?php foreach (flash_take() as $f): ?>
    <div class="flash flash-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
  <?php endforeach; ?>
  <h1 class="page-title"><?= h($__title) ?></h1>
  <?= $__content ?>
</main>
<?php if ($__banner): ?>
<aside class="sidepanel" id="sidepanel">
  <a class="sp-brand" href="https://felixschaller.com" target="_blank" rel="noopener"
     aria-label="FelixSchallerCOM"></a>
  <div class="sp-rotator" id="spRotator" data-endpoint="<?= h(route('sidepanel')) ?>" data-interval="14000"></div>
</aside>
<?php endif; ?>
</div>

<footer class="foot">
  <?= h(cfg('app_name', 'Timeminator')) ?> Community v<?= h(app_version()) ?>
  &middot; <a href="https://felixschaller.com" target="_blank" rel="noopener">made by FelixSchallerCOM</a>
  &middot; <a href="<?= h(cfg('pro_url', 'https://timeminator.felixschaller.com')) ?>" target="_blank" rel="noopener">mehr Funktionen mit Timeminator Pro</a>
</footer>
<script src="<?= h(asset('app.js')) ?>"></script>
</body>
</html>
