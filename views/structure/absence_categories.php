<?php
/**
 * @var array $categories
 * Admin-Katalog für Abwesenheitsarten (v1.0 F5).
 */
$accounts = array_keys(AbsenceRepo::ACCOUNTS);
?>
<h1 class="page-title"><?= h(t('absence_cat.h1', 'Abwesenheitsarten')) ?><?= tip('absence_cat.help', 'Vorlagen fuer Abwesenheiten. Konto steuert welche Logik (Urlaubskonto / Ueberstundenkonto / nur Statistik) spaeter in F1 angewendet wird — vorerst nur Anzeige.') ?></h1>

<div class="card">
  <table class="table">
    <thead><tr><th colspan="5"><?= h(t('absence_cat.col', 'Abwesenheitsart')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
      <tr>
        <td colspan="5">
          <details class="row-edit">
            <summary>
              <code><?= h($c['ckey']) ?></code>
              <?= h($c['label']) ?>
              <span class="muted"> · <?= $c['account'] ? h(AbsenceRepo::accountLabel($c['account'])) : h(t('absence_cat.no_account', 'kein Konto')) ?></span>
              <span class="muted"> · <?= h(t('project_cat.row', 'Reihe')) ?> <?= (int) $c['position'] ?> · <?= $c['active'] ? h(t('common.active', 'aktiv')) : h(t('common.inactive', 'inaktiv')) ?></span>
              <span class="row-edit-label"><?= h(t('common.edit', 'bearbeiten')) ?></span>
            </summary>
            <form method="post" action="<?= h(route('absence_category_save')) ?>" class="row-edit-form row" style="gap:8px;align-items:flex-end">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <span style="min-width:90px"><code><?= h($c['ckey']) ?></code></span>
              <label class="grow" style="margin:0"><?= h(t('project_cat.label', 'Label')) ?> <input type="text" name="label" value="<?= h($c['label']) ?>"></label>
              <label style="margin:0"><?= h(t('absence_cat.account', 'Konto')) ?>
                <select name="account">
                  <option value=""><?= h(t('absence_cat.no_account', 'kein Konto')) ?></option>
                  <?php foreach ($accounts as $__key): ?>
                    <option value="<?= h($__key) ?>" <?= ($c['account'] ?? '') === $__key ? 'selected' : '' ?>><?= h(AbsenceRepo::accountLabel($__key)) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label style="margin:0;width:56px"><?= h(t('project_cat.row', 'Reihe')) ?> <input type="text" name="position" value="<?= (int) $c['position'] ?>" size="3"></label>
              <label class="check" style="margin:0"><input type="checkbox" name="active" value="1" <?= $c['active'] ? 'checked' : '' ?>> <?= h(t('common.active', 'aktiv')) ?></label>
              <button class="btn btn-sm btn-primary" type="submit"><?= h(t('common.save', 'speichern')) ?></button>
            </form>
          </details>
        </td>
        <td class="r nowrap">
          <form method="post" action="<?= h(route('absence_category_delete')) ?>" class="inline" onsubmit="return confirm('<?= h(sprintf(t('absence_cat.confirm_delete', 'Abwesenheitsart %s loeschen?'), $c['label'])) ?>')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit"><?= h(t('common.delete', 'loeschen')) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$categories): ?><tr><td colspan="6" class="muted"><?= h(t('absence_cat.empty', 'Noch keine Abwesenheitsarten.')) ?></td></tr><?php endif; ?>
    <tr>
      <td colspan="6">
        <details class="row-edit">
          <summary><?= h(t('absence_cat.new', '+ Neue Abwesenheitsart')) ?></summary>
          <form method="post" action="<?= h(route('absence_category_save')) ?>" class="row-edit-form row" style="gap:8px;align-items:flex-end">
            <?= csrf_field() ?>
            <label style="margin:0"><?= h(t('project_cat.ckey', 'Schluessel')) ?> <input type="text" name="ckey" placeholder="<?= h(t('absence_cat.ckey_placeholder', 'z. B. elternzeit')) ?>" required></label>
            <label class="grow" style="margin:0"><?= h(t('project_cat.label', 'Label')) ?> <input type="text" name="label" required></label>
            <label style="margin:0"><?= h(t('absence_cat.account', 'Konto')) ?>
              <select name="account">
                <option value=""><?= h(t('absence_cat.no_account', 'kein Konto')) ?></option>
                <?php foreach ($accounts as $__key): ?>
                  <option value="<?= h($__key) ?>"><?= h(AbsenceRepo::accountLabel($__key)) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
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
