<?php
/**
 * Right-edge upsell banner shown on every Pro-feature teaser page.
 *
 * Expects `$pro_url` and `$__feature` (short label, e.g. "Rechnungen")
 * in scope. The banner is "sticky" so it stays visible even as the
 * dimmed mockup on the left scrolls — the whole point is the CTA.
 *
 * @var string $pro_url
 * @var string $__feature
 * @var string $__bullets   HTML list of 2–4 short selling points
 */
?>
<aside class="pro-upsell" role="complementary" aria-label="Pro-Feature">
  <span class="pill pill-pro">Pro-Feature</span>
  <h2><?= h($__feature) ?></h2>
  <p class="muted"><?= h($__feature) ?> gibt es in <strong>Timeminator Pro</strong>. In Community sieht man die Oberflaeche, aber nicht die Daten.</p>
  <ul class="pro-upsell-list">
    <?= $__bullets /* already-escaped HTML built by the view */ ?>
  </ul>
  <a class="btn btn-primary pro-upsell-cta" href="<?= h($pro_url) ?>" target="_blank" rel="noopener">
    Jetzt Pro ansehen &nearr;
  </a>
  <p class="muted pro-upsell-foot">Deine Community-Daten bleiben lokal. Pro ist eine separate Instanz.</p>
</aside>
