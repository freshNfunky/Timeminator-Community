<?php
/**
 * @var array $categories
 * Prozessarten für die Buchung interner Prozesse (v1.0 F6).
 */
?>
<h1 class="page-title"><?= h(t('process_cat.h1', 'Prozessarten')) ?><?= tip('process_cat.help', 'Nicht-projektbezogene Taetigkeiten wie Angebotserstellung, Akquise, Weiterbildung. Werden ueber den Menuepunkt „Interne Prozesse" gebucht.') ?></h1>

<div class="card">
  <table class="table">
    <thead><tr><th colspan="4"><?= h(t('process_cat.col', 'Prozessart')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
      <tr>
        <td colspan="4">
          <details class="row-edit">
            <summary>
              <code><?= h($c['ckey']) ?></code>
              <?= h($c['label']) ?>
              <span class="muted"> · <?= h(t('project_cat.row', 'Reihe')) ?> <?= (int) $c['position'] ?> · <?= $c['active'] ? h(t('common.active', 'aktiv')) : h(t('common.inactive', 'inaktiv')) ?></span>
              <span class="row-edit-label"><?= h(t('common.edit', 'bearbeiten')) ?></span>
            </summary>
            <form method="post" action="<?= h(route('process_category_save')) ?>" class="row-edit-form row" style="gap:8px;align-items:flex-end">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <span style="min-width:90px"><code><?= h($c['ckey']) ?></code></span>
              <label class="grow" style="margin:0"><?= h(t('project_cat.label', 'Label')) ?> <input type="text" name="label" value="<?= h($c['label']) ?>"></label>
              <label style="margin:0;width:56px"><?= h(t('project_cat.row', 'Reihe')) ?> <input type="text" name="position" value="<?= (int) $c['position'] ?>" size="3"></label>
              <label class="check" style="margin:0"><input type="checkbox" name="active" value="1" <?= $c['active'] ? 'checked' : '' ?>> <?= h(t('common.active', 'aktiv')) ?></label>
              <button class="btn btn-sm btn-primary" type="submit"><?= h(t('common.save', 'speichern')) ?></button>
            </form>
          </details>
        </td>
        <td class="r nowrap">
          <form method="post" action="<?= h(route('process_category_delete')) ?>" class="inline" onsubmit="return confirm('<?= h(sprintf(t('process_cat.confirm_delete', 'Prozessart %s loeschen?'), $c['label'])) ?>')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit"><?= h(t('common.delete', 'loeschen')) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$categories): ?><tr><td colspan="5" class="muted"><?= h(t('process_cat.empty', 'Noch keine Prozessarten.')) ?></td></tr><?php endif; ?>
    <tr>
      <td colspan="5">
        <details class="row-edit">
          <summary><?= h(t('process_cat.new', '+ Neue Prozessart')) ?></summary>
          <form method="post" action="<?= h(route('process_category_save')) ?>" class="row-edit-form row" style="gap:8px;align-items:flex-end">
            <?= csrf_field() ?>
            <label style="margin:0"><?= h(t('project_cat.ckey', 'Schluessel')) ?> <input type="text" name="ckey" placeholder="<?= h(t('process_cat.ckey_placeholder', 'z. B. messe')) ?>" required></label>
            <label class="grow" style="margin:0"><?= h(t('project_cat.label', 'Label')) ?> <input type="text" name="label" required></label>
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
