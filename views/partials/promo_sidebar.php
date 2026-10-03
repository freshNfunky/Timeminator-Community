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
        'slug'  => 'ai-transformation',
        'title' => 'AI Transformation',
        'body'  => 'Von ungesteuerter KI zu einem Stack, den du steuern, auditieren und skalieren kannst.',
        'href'  => 'https://felixschaller.com/services/ai-transformation',
        'cta'   => 'Mehr',
    ],
    [
        'slug'  => 'autonomy-risk',
        'title' => 'Autonomy Risk Evaluation',
        'body'  => 'Strukturelles Risiko in der Architektur — nicht erst im Backlog.',
        'href'  => 'https://felixschaller.com/services/autonomy-risk-evaluation',
        'cta'   => 'Mehr',
    ],
    [
        'slug'  => 'technical-dd',
        'title' => 'Technical Due Diligence',
        'body'  => 'Autonomy &amp; AI — fuer Investoren, die Claims verifizieren statt wiederholen.',
        'href'  => 'https://felixschaller.com/services/technical-dd',
        'cta'   => 'Mehr',
    ],
    [
        'slug'  => 'fractional',
        'title' => 'Fractional Technical Leadership',
        'body'  => 'Engineering-Ownership ohne feste Headcount-Stelle.',
        'href'  => 'https://felixschaller.com/services/fractional',
        'cta'   => 'Mehr',
    ],
    [
        'slug'  => 'digital-twin',
        'title' => 'Digital Twin Perception',
        'body'  => 'Prozesse simulieren bevor du sie umbaust.',
        'href'  => 'https://felixschaller.com/tools/digital-twin-perception',
        'cta'   => 'Mehr',
    ],
];
?>
<aside class="promo-sidebar" role="complementary" aria-label="Services">
  <div class="promo-sidebar-head">
    <a href="https://felixschaller.com" target="_blank" rel="noopener" class="promo-sidebar-brand" aria-label="felixschaller.com">
      <img src="<?= h(asset('fsc-logo.svg')) ?>" alt="FelixSchallerCOM" width="160" height="16">
    </a>
    <form method="post" action="<?= h(route('promo_dismiss')) ?>" class="inline">
      <?= csrf_field() ?>
      <button class="promo-sidebar-close" type="submit" title="Fuer diese Sitzung ausblenden" aria-label="Fuer diese Sitzung ausblenden">&times;</button>
    </form>
  </div>
  <div class="promo-carousel" data-promo-interval="6000">
    <?php foreach ($__promos as $__i => $p): ?>
      <a class="promo-banner<?= $__i === 0 ? ' is-active' : '' ?>" href="<?= h($p['href']) ?>" target="_blank" rel="noopener" data-slug="<?= h($p['slug']) ?>">
        <strong><?= h($p['title']) ?></strong>
        <p><?= h($p['body']) ?></p>
        <span class="promo-banner-cta"><?= h($p['cta']) ?> &nearr;</span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (count($__promos) > 1): ?>
    <div class="promo-dots" role="tablist" aria-label="Banner wechseln">
      <?php foreach ($__promos as $__i => $p): ?>
        <button type="button" class="promo-dot<?= $__i === 0 ? ' is-active' : '' ?>" data-promo-dot="<?= (int) $__i ?>" aria-label="Banner <?= (int) $__i + 1 ?>"></button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <p class="promo-sidebar-foot muted">Powered by<br>felixschaller.com</p>
</aside>
