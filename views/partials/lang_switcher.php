<?php
/**
 * Inline language switcher for the login + installer screens.
 *
 * Those pages have no main nav where the Admin -> System locale toggle
 * lives, so the switcher appears here instead. Each option is just a link
 * that preserves the current query string with `lang=` overridden — the
 * Locale class detects it and persists the choice into the session.
 */
$current = I18n::current();
$qp = $_GET ?? [];
?>
<p class="lang-switcher">
  <?php foreach (I18n::available() as $i => $loc): ?>
    <?php
      $qp['lang'] = $loc['code'];
      $href = '?' . http_build_query($qp);
      $isCurrent = $loc['code'] === $current;
    ?>
    <?php if ($i > 0): ?><span class="lang-sep">·</span><?php endif; ?>
    <?php if ($isCurrent): ?>
      <strong><?= h($loc['native']) ?></strong>
    <?php else: ?>
      <a href="<?= h($href) ?>" rel="nofollow"><?= h($loc['native']) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</p>
