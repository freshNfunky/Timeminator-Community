<?php
/** @var array $absences @var array $categories */
$fmt = fn(string $d) => (new DateTimeImmutable($d))->format('d.m.Y');
?>
<h1 class="page-title"><?= h(t('absences.h1', 'Abwesenheiten')) ?><?= tip('absences.help', 'Urlaub, Ausfall wegen Krankheit oder Ueberstundenabbau — getrennt von Projektbuchungen, taucht nirgends in Report/Statistik/Rechnungen auf.') ?></h1>

<div class="cols">
  <div class="card col-form">
    <h2><?= h(t('absences.new_h2', 'Abwesenheit eintragen')) ?></h2>
    <form method="post" action="<?= h(route('absence_save')) ?>">
      <?= csrf_field() ?>
      <label>
        <span class="lbl"><?= h(t('absences.category', 'Art')) ?></span>
        <select name="category_id" required>
          <option value=""><?= h(t('absences.pick_category', 'Art waehlen …')) ?></option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" data-account="<?= h((string) ($c['account'] ?? '')) ?>">
              <?= h($c['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="row">
        <label>
          <span class="lbl"><?= h(t('absences.from', 'Von')) ?></span>
          <input type="date" name="start_date" required>
        </label>
        <label>
          <span class="lbl"><?= h(t('absences.to', 'Bis')) ?></span>
          <input type="date" name="end_date" placeholder="<?= h(t('absences.to_placeholder', 'wie Von, falls leer')) ?>">
        </label>
      </div>
      <label>
        <span class="lbl"><?= h(t('absences.hours_label', 'Stunden je Tag')) ?><?= tip('absences.hours_hint', 'Nur fuer Ueberstundenabbau sinnvoll — leer = ganzer Tag. Zwischen 0,25 und 24 Stunden.') ?></span>
        <input type="text" name="hours" inputmode="decimal" placeholder="<?= h(t('absences.hours_placeholder', 'z. B. 2,5')) ?>">
      </label>
      <label>
        <span class="lbl"><?= h(t('entries.note', 'Notiz')) ?></span>
        <input type="text" name="note" maxlength="255" placeholder="<?= h(t('common.optional', 'optional')) ?>">
      </label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= h(t('common.save', 'Speichern')) ?></button>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2><?= h(t('absences.list_h2', 'Meine Abwesenheiten')) ?></h2>
    <?php if (!$absences): ?>
      <p class="muted"><?= h(t('absences.empty', 'Noch keine Abwesenheiten eingetragen.')) ?></p>
    <?php else: ?>
      <table class="table">
        <thead><tr>
          <th><?= h(t('absences.category', 'Art')) ?></th>
          <th><?= h(t('absences.from', 'Von')) ?></th>
          <th><?= h(t('absences.to', 'Bis')) ?></th>
          <th><?= h(t('absences.hours_col', 'Stunden/Tag')) ?></th>
          <th><?= h(t('entries.note', 'Notiz')) ?></th>
          <th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($absences as $a): ?>
            <tr>
              <td><?= h($a['category_label']) ?></td>
              <td class="nowrap"><?= h($fmt($a['start_date'])) ?></td>
              <td class="nowrap"><?= h($fmt($a['end_date'])) ?></td>
              <td class="nowrap">
                <?php if (isset($a['hours']) && $a['hours'] !== null): ?>
                  <?= h(rtrim(rtrim(number_format((float) $a['hours'], 2, ',', ''), '0'), ',')) ?> h
                <?php else: ?>
                  <span class="muted"><?= h(t('absences.full_day', 'ganzer Tag')) ?></span>
                <?php endif; ?>
              </td>
              <td class="muted"><?= h($a['note'] ?? '') ?></td>
              <td class="r">
                <form method="post" action="<?= h(route('absence_delete')) ?>" class="inline" onsubmit="return confirm('<?= h(t('absences.confirm_delete', 'Abwesenheit loeschen?')) ?>')">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                  <button class="btn btn-sm btn-danger" type="submit"><?= h(t('common.delete', 'loeschen')) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
