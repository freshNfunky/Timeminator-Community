<?php
/**
 * Right-side promo column shown in the Community edition.
 *
 * Pays for Community: a slim vertical band next to the main content with
 * links back to Felix Schaller's other services (AI Assessment, Digital
 * Twin, …). Hidden on narrow viewports via CSS; hidden entirely when the
 * operator sets `promo_sidebar_enabled => false` in config.php or toggles
 * it off in Admin → System.
 *
 * Current state: bundled placeholder banners only. Issue #22 plans a
 * remote-fetch path (banners.felixschaller.com with offline fallback);
 * that is a follow-up PR — this one only adds the slot and the shape.
 */
if ((bool) cfg('promo_sidebar_enabled', true) !== true) {
    return;
}
if (!empty($_SESSION['promo_sidebar_dismissed'])) {
    return;
}

// Bundled defaults. Keeps the slot populated when the remote feed is
// unreachable, when there is no outbound internet, or until the designer
// ships creatives. Shape mirrors what the remote feed will return later:
// each entry is {slug, title, body, href, cta}.
$__promos = [
    [
        'slug'  => 'ai-assessment',
        'title' => 'AI Assessment',
        'body'  => 'Wo lohnt sich KI in deinem Workflow — und wo nicht?',
        'href'  => 'https://felixschaller.com/ai-assessment',
        'cta'   => 'Mehr',
    ],
    [
        'slug'  => 'digital-twin',
        'title' => 'Digital Twin',
        'body'  => 'Prozesse simulieren bevor du sie umbaust.',
        'href'  => 'https://felixschaller.com/digital-twin',
        'cta'   => 'Mehr',
    ],
    [
        'slug'  => 'services',
        'title' => 'Felixschaller.com',
        'body'  => 'Beratung, Tools, Prototypen.',
        'href'  => 'https://felixschaller.com',
        'cta'   => 'Besuchen',
    ],
];
?>
<aside class="promo-sidebar" role="complementary" aria-label="Services">
  <div class="promo-sidebar-head">
    <span class="promo-sidebar-brand">FSC</span>
    <form method="post" action="<?= h(route('promo_dismiss')) ?>" class="inline">
      <?= csrf_field() ?>
      <button class="promo-sidebar-close" type="submit" title="Fuer diese Sitzung ausblenden" aria-label="Fuer diese Sitzung ausblenden">&times;</button>
    </form>
  </div>
  <?php foreach ($__promos as $p): ?>
    <a class="promo-banner" href="<?= h($p['href']) ?>" target="_blank" rel="noopener" data-slug="<?= h($p['slug']) ?>">
      <strong><?= h($p['title']) ?></strong>
      <p><?= h($p['body']) ?></p>
      <span class="promo-banner-cta"><?= h($p['cta']) ?> &nearr;</span>
    </a>
  <?php endforeach; ?>
  <p class="promo-sidebar-foot muted">Powered by<br>felixschaller.com</p>
</aside>
