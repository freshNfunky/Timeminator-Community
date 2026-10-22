<?php
/**
 * @var array $categories
 *
 * Portiert aus Pro: list-with-click-to-expand-edit pattern (bewährt aus
 * v0.9.1 abstract-list-editor), rows bleiben plain-text bis bearbeiten
 * geklickt wird. Portable: nutzt bestehende Community-CSS-Klassen.
 */
?>
<h1 class="page-title"><?= h(t('project_cat.h1', 'Projektarten')) ?><?= tip('project_cat.help', 'Kategorie oberhalb von Kunde. Kundenprojekt (Normalfall) hat einen echten Kunden; braucht ein Projekt keinen Kunden, Kunde entfaellt im Projektformular (z. B. Internes Projekt). Kunde-Label kann ueberschrieben werden (z. B. Foerdermittelgeber fuer ein Foerderprojekt).') ?></h1>

<div class="card">
  <table class="table">
    <thead><tr><th colspan="6"><?= h(t('project_cat.col', 'Projektart')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
      <tr>
        <td colspan="6">
          <details class="row-edit">
            <summary>
              <code><?= h($c['ckey']) ?></code>
              <?= h($c['label']) ?>
              <span class="muted">
                <?= $c['needs_client'] ? h($c['client_label'] ?: t('project_cat.client_default', 'Kunde')) . ' ' . h(t('project_cat.needed', 'benoetigt')) : h(t('project_cat.no_client', 'kein Kunde')) ?>
                · <?= h(t('project_cat.row', 'Reihe')) ?> <?= (int) $c['position'] ?> · <?= $c['active'] ? h(t('common.active', 'aktiv')) : h(t('common.inactive', 'inaktiv')) ?>
              </span>
              <span class="row-edit-label"><?= h(t('common.edit', 'bearbeiten')) ?></span>
            </summary>
            <form method="post" action="<?= h(route('project_category_save')) ?>" class="row-edit-form row" style="gap:8px;align-items:flex-end">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <span style="min-width:90px"><code><?= h($c['ckey']) ?></code></span>
              <label class="grow" style="margin:0"><?= h(t('project_cat.label', 'Label')) ?> <input type="text" name="label" value="<?= h($c['label']) ?>"></label>
              <label class="check" style="margin:0"><input type="checkbox" name="needs_client" value="1" <?= $c['needs_client'] ? 'checked' : '' ?>> <?= h(t('project_cat.needs_client', 'benoetigt')) ?></label>
              <label style="margin:0"><?= h(t('project_cat.client_label', 'Kunde-Label')) ?> <input type="text" name="client_label" value="<?= h($c['client_label'] ?? '') ?>" placeholder="<?= h(t('project_cat.client_default', 'Kunde')) ?>" size="16"></label>
              <label style="margin:0;width:56px"><?= h(t('project_cat.row', 'Reihe')) ?> <input type="text" name="position" value="<?= (int) $c['position'] ?>" size="3"></label>
              <label class="check" style="margin:0"><input type="checkbox" name="active" value="1" <?= $c['active'] ? 'checked' : '' ?>> <?= h(t('common.active', 'aktiv')) ?></label>
              <button class="btn btn-sm btn-primary" type="submit"><?= h(t('common.save', 'speichern')) ?></button>
            </form>
          </details>
        </td>
        <td class="r nowrap">
          <form method="post" action="<?= h(route('project_category_delete')) ?>" class="inline" onsubmit="return confirm('<?= h(sprintf(t('project_cat.confirm_delete', 'Projektart %s loeschen?'), $c['label'])) ?>')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit"><?= h(t('common.delete', 'loeschen')) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$categories): ?><tr><td colspan="7" class="muted"><?= h(t('project_cat.empty', 'Noch keine Projektarten.')) ?></td></tr><?php endif; ?>
    <tr>
      <td colspan="7">
        <details class="row-edit">
          <summary><?= h(t('project_cat.new', '+ Neue Projektart')) ?></summary>
          <form method="post" action="<?= h(route('project_category_save')) ?>" class="row-edit-form row" style="gap:8px;align-items:flex-end">
            <?= csrf_field() ?>
            <label style="margin:0"><?= h(t('project_cat.ckey', 'Schluessel')) ?> <input type="text" name="ckey" placeholder="<?= h(t('project_cat.ckey_placeholder', 'z. B. sponsoring')) ?>" required></label>
            <label class="grow" style="margin:0"><?= h(t('project_cat.label', 'Label')) ?> <input type="text" name="label" required></label>
            <label class="check" style="margin:0"><input type="checkbox" name="needs_client" value="1" checked> <?= h(t('project_cat.needs_client_full', 'benoetigt Kunde')) ?></label>
            <label style="margin:0"><?= h(t('project_cat.client_label', 'Kunde-Label')) ?> <input type="text" name="client_label" placeholder="<?= h(t('project_cat.client_default', 'Kunde')) ?>" size="16"></label>
            <label style="margin:0;width:56px"><?= h(t('project_cat.row', 'Reihe')) ?> <input type="text" name="position" size="3"></label>
            <label class="check" style="margin:0"><input type="checkbox" name="active" value="1" checked> <?= h(t('common.active', 'aktiv')) ?></label>
            <button class="btn btn-sm btn-primary" type="submit"><?= h(t('common.create', 'anlegen')) ?></button>
          </form>
        </details>
      </td>
    </tr>
    </tbody>
  </table>
</div>
