<?php
/**
 * Client edit/new form, used by views/structure/clients.php via
 * list_editor.php. Reads $edit (nullable) and $tracks from scope.
 *
 * @var ?array $edit
 * @var array  $tracks
 */
?>
<h3 class="form-inline-title"><?= h($edit ? t('clients.edit', 'Kunde bearbeiten') : t('clients.new', 'Neuer Kunde')) ?></h3>
<form method="post" action="<?= h(route('client_save')) ?>" class="form-inline">
  <?= csrf_field() ?>
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
  <label><?= h(t('struct.name', 'Name')) ?> <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></label>
  <label><?= h(t('struct.code', 'Kennung (Code)')) ?> <input type="text" name="code" value="<?= h($edit['code'] ?? '') ?>" placeholder="<?= h(t('struct.code_placeholder', 'wird sonst automatisch erzeugt')) ?>"></label>
  <label><?= h(t('clients.track', 'Gleis / Gruppe')) ?>
    <input type="text" name="track" list="tracklist-<?= $edit ? (int) $edit['id'] : 'new' ?>" value="<?= h($edit['track'] ?? '') ?>" placeholder="<?= h(t('clients.track_placeholder', 'z. B. formal, physical, …')) ?>">
    <datalist id="tracklist-<?= $edit ? (int) $edit['id'] : 'new' ?>"><?php foreach (($tracks ?? []) as $__tr): ?><option value="<?= h($__tr) ?>"><?php endforeach; ?></datalist>
  </label>
  <label><?= h(t('clients.color', 'Farbe')) ?> <input type="color" name="color" value="<?= h($edit['color'] ?? '#3b82f6') ?>"></label>
  <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> <?= h(t('struct.active', 'aktiv')) ?></label>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
    <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('clients')) ?>"><?= h(t('common.cancel', 'abbrechen')) ?></a><?php endif; ?>
  </div>
</form>
