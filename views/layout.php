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
<html lang="<?= h(I18n::current()) ?>"<?= $__themeAttr ?>>
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
<body data-i18n-chart-empty="<?= h(t('charts.empty', 'Keine Daten')) ?>">
<header class="topbar">
  <a class="brand" href="<?= h(route('dashboard')) ?>">⏱ <?= h(cfg('app_name', 'Timeminator')) ?></a>
  <nav class="mainnav">
    <?php require __DIR__ . '/partials/nav.php'; ?>
  </nav>
  <div class="userbox">
    <?php /* Felix-Feedback 2026-11-09 (#111): Benutzername ist ein Link auf
       das Profil (persoenliche Einstellungen, u.a. der Stimmungs-Rhythmus). */ ?>
    <a class="who" href="<?= h(route('profile')) ?>"><?= h(Auth::user()['display_name'] ?: Auth::user()['username']) ?>
      <em><?= h(Auth::user()['role_label'] ?? '') ?></em></a>
    <a class="btn btn-ghost" href="<?= h(route('logout')) ?>"><?= h(t('nav.logout')) ?></a>
  </div>
</header>

<?php require __DIR__ . '/partials/update_banner.php'; ?>

<div class="shell<?= $__banner ? ' has-aside' : '' ?>">
<main class="wrap">
  <?php foreach (flash_take() as $f): ?>
    <div class="flash flash-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
  <?php endforeach; ?>
  <?php /* Felix-Feedback 2026-11-09 (Fix #144): Die EINZIGE page-title-H1
     in der App. Views duerfen keine zweite rendern. Fuer Tooltip neben dem
     Titel setzt die View $__titleTip = 'i18n.key' am Anfang (Variable
     ueberlebt dank gemeinsamem view()-Scope ins Layout). */ ?>
  <h1 class="page-title"><?= h($__title) ?><?php if (!empty($__titleTip ?? null)): ?><?= tip((string) $__titleTip) ?><?php endif; ?></h1>
  <?= $__content ?>
</main>
<?php if ($__banner): ?>
<aside class="sidepanel" id="sidepanel">
  <a class="sp-brand" href="<?= h(cfg('pro_demo_url', 'https://demo.timeminator.com')) ?>" target="_blank" rel="noopener"
     aria-label="<?= h(t('sidepanel.brand_aria')) ?>"></a>
  <div class="sp-rotator" id="spRotator" data-endpoint="<?= h(route('sidepanel')) ?>" data-interval="14000"></div>
</aside>
<?php endif; ?>
</div>

<footer class="foot">
  <?= h(cfg('app_name', 'Timeminator')) ?> <?= h(sprintf(t('footer.community_suffix'), app_version())) ?>
  &middot; <span><?= h(t('footer.made_by')) ?></span>
  &middot; <a href="<?= h(cfg('pro_demo_url', 'https://demo.timeminator.com')) ?>" target="_blank" rel="noopener"><?= h(t('footer.more_features')) ?></a>
</footer>
<script src="<?= h(asset('app.js')) ?>"></script>
</body>
</html>
