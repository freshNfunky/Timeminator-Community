<?php
/**
 * Right-side promo column shown in the Community edition.
 *
 * Items are loaded by `PromoFeed::items()`: fresh from the configured
 * `promo_feed_url` (default `https://banner.felixschaller.com/feed.json`)
 * through a short-timeout, server-side fetch so a strict CSP stays
 * strict, cached for an hour under `data/`, and otherwise fall back to
 * `assets/promo-fallback.json` so the slot is never empty.
 *
 * Each item renders in one of three ways, picked per item:
 *   - `html`: a sanitized HTML snippet, mounted into a sandboxed
 *     `<iframe srcdoc>` so even if the sanitizer missed something the
 *     payload cannot touch the host page. Preferred.
 *   - `svg`:  inline SVG, wrapped in the CTA link.
 *   - text:   title + body + cta card, the historical format.
 *
 * Hidden entirely when the operator sets `promo_sidebar_enabled => false`
 * or dismisses the slot for the session via the X button.
 */
if ((bool) cfg('promo_sidebar_enabled', true) !== true) {
    return;
}
if (!empty($_SESSION['promo_sidebar_dismissed'])) {
    return;
}

$__items = PromoFeed::items();
if ($__items === []) {
    return;
}

/**
 * Minimal HTML wrapper for a feed snippet. Keeps the iframe content
 * sized to its slot (full width, auto height) and sets a tiny stylesheet
 * so the snippet inherits the surrounding theme without having to carry
 * one of its own.
 */
$__wrapHtml = static function (string $html): string {
    $base = '<!doctype html><meta charset="utf-8">'
          . '<meta name="viewport" content="width=device-width,initial-scale=1">'
          . '<base target="_top">'
          . '<style>'
          . 'html,body{margin:0;padding:12px;font:14px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#e2e8f0;background:transparent;box-sizing:border-box;overflow:hidden}'
          . '*{box-sizing:inherit}img,svg{max-width:100%;height:auto;display:block}'
          . 'a{color:#3b82f6;text-decoration:none}a:hover{text-decoration:underline}'
          . '@media (prefers-color-scheme: light){html,body{color:#1e293b}}'
          . '</style>';
    return $base . $html;
};
?>
<aside class="promo-sidebar" role="complementary" aria-label="Anzeige">
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
    <?php foreach ($__items as $__i => $p):
        $__slug = isset($p['slug']) ? (string) $p['slug'] : '';
        $__href = isset($p['href']) ? (string) $p['href'] : '';
    ?>
      <?php
        $__kind = isset($p['image'])    ? 'image'
                : (isset($p['html_url']) ? 'html'
                : (isset($p['html'])     ? 'html'
                : (isset($p['svg_url']) ? 'svg'
                : (isset($p['svg'])     ? 'svg' : 'text'))));
      ?>
      <a class="promo-banner<?= $__i === 0 ? ' is-active' : '' ?> promo-banner-<?= h($__kind) ?>"
         href="<?= h($__href) ?>" target="_blank" rel="noopener" data-slug="<?= h($__slug) ?>">
        <?php if (isset($p['image'])): ?>
          <img class="promo-image" src="<?= h((string) $p['image']) ?>" alt="<?= h($p['title'] ?? 'Anzeige') ?>" loading="lazy">
        <?php elseif (isset($p['html_url'])): ?>
          <iframe class="promo-iframe" sandbox="allow-popups allow-popups-to-escape-sandbox"
                  loading="lazy" referrerpolicy="no-referrer"
                  src="<?= h((string) $p['html_url']) ?>"
                  title="<?= h($p['title'] ?? 'Anzeige') ?>"></iframe>
          <span class="promo-iframe-click" aria-hidden="true"></span>
        <?php elseif (isset($p['html'])): ?>
          <iframe class="promo-iframe" sandbox="allow-popups allow-popups-to-escape-sandbox"
                  loading="lazy" referrerpolicy="no-referrer"
                  srcdoc="<?= h($__wrapHtml((string) $p['html'])) ?>"
                  title="<?= h($p['title'] ?? 'Anzeige') ?>"></iframe>
          <span class="promo-iframe-click" aria-hidden="true"></span>
        <?php elseif (isset($p['svg_url'])): ?>
          <img class="promo-image" src="<?= h((string) $p['svg_url']) ?>" alt="<?= h($p['title'] ?? 'Anzeige') ?>" loading="lazy">
        <?php elseif (isset($p['svg'])): ?>
          <span class="promo-svg-wrap"><?= $p['svg'] /* already sanitized by PromoFeed */ ?></span>
        <?php else: ?>
          <strong><?= h($p['title'] ?? '') ?></strong>
          <p><?= h($p['body'] ?? '') ?></p>
          <?php if (!empty($p['cta'])): ?>
            <span class="promo-banner-cta"><?= h($p['cta']) ?> &nearr;</span>
          <?php endif; ?>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (count($__items) > 1): ?>
    <div class="promo-dots" role="tablist" aria-label="Banner wechseln">
      <?php foreach ($__items as $__i => $p): ?>
        <button type="button" class="promo-dot<?= $__i === 0 ? ' is-active' : '' ?>" data-promo-dot="<?= (int) $__i ?>" aria-label="Banner <?= (int) $__i + 1 ?>"></button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <p class="promo-sidebar-foot muted">Powered by<br>felixschaller.com</p>
</aside>
