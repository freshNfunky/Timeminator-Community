<?php
/**
 * Small "update available" panel shown to admins below the top bar.
 *
 * Rendered only when `Updater::bannerInfo()` returns something: a newer
 * release exists, it has not been skipped globally, and it has not been
 * dismissed in this session. All buttons are forms — no inline JS.
 */
if (!Auth::isAdmin()) {
    return;
}
Updater::opportunisticCheck();
$__info = Updater::bannerInfo();
if (!$__info) {
    return;
}
$__latest = (string) ($__info['latest'] ?? '');
$__notesUrl = (string) ($__info['url'] ?? '');
?>
<aside class="update-banner" role="status" aria-live="polite">
  <div class="update-banner-head">
    <strong><?= h(sprintf(t('update.new_version', 'Neue Version %s'), $__latest)) ?></strong>
    <form method="post" action="<?= h(route('update_dismiss')) ?>" class="inline">
      <?= csrf_field() ?>
      <input type="hidden" name="version" value="<?= h($__latest) ?>">
      <button class="update-banner-close" type="submit" aria-label="<?= h(t('update.close', 'Schliessen')) ?>">&times;</button>
    </form>
  </div>
  <p class="muted update-banner-current"><?= h(sprintf(t('update.installed', 'Installiert: v%s'), app_version())) ?></p>
  <?php if ($__notesUrl !== ''): ?>
    <p><a href="<?= h($__notesUrl) ?>" target="_blank" rel="noopener"><?= h(t('update.release_notes', 'Release-Notes ansehen ↗')) ?></a></p>
  <?php endif; ?>
  <div class="update-banner-actions">
    <form method="post" action="<?= h(route('update_dismiss')) ?>" class="inline">
      <?= csrf_field() ?>
      <input type="hidden" name="version" value="<?= h($__latest) ?>">
      <button class="btn btn-sm btn-ghost" type="submit"><?= h(t('update.ignore', 'Ignorieren')) ?></button>
    </form>
    <form method="post" action="<?= h(route('update_skip')) ?>" class="inline"
          data-confirm="<?= h(sprintf(t('update.skip_confirm', 'Version %s ueberspringen? Das Banner erscheint erst wieder bei einer neueren Version.'), $__latest)) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="version" value="<?= h($__latest) ?>">
      <button class="btn btn-sm" type="submit"><?= h(t('update.skip', 'Ueberspringen')) ?></button>
    </form>
    <a class="btn btn-sm btn-primary" href="<?= h(route('admin_system')) ?>"><?= h(t('update.update', 'Update')) ?></a>
  </div>
</aside>
