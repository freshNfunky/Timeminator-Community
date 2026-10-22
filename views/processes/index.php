<?php
/**
 * Interne Prozesse — eigene Buchungs-UI.
 * @var array $entries
 * @var array $categories
 */
?>
<h1 class="page-title"><?= h(t('processes.h1', 'Interne Prozesse')) ?><?= tip('processes.help', 'Nicht-projektbezogene Taetigkeiten wie Angebotserstellung oder Akquise. Buchungen hier zaehlen nicht als Projektzeit und tauchen in Projekt-/Kunden-Statistiken nicht auf.') ?></h1>

<div class="cols">
  <div class="card col-form">
    <h2><?= h(t('processes.new_h2', 'Neue Buchung')) ?></h2>
    <form method="post" action="<?= h(route('process_save')) ?>">
      <?= csrf_field() ?>
      <label>
        <span class="lbl"><?= h(t('processes.category', 'Prozessart')) ?></span>
        <select name="category_id" required>
          <option value=""><?= h(t('processes.pick_category', '— waehlen —')) ?></option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= h($c['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="row">
        <label>
          <span class="lbl"><?= h(t('entries.start', 'Beginn')) ?></span>
          <input type="datetime-local" name="start_ts" required>
        </label>
        <label>
          <span class="lbl"><?= h(t('entries.end', 'Ende')) ?></span>
          <input type="datetime-local" name="end_ts" required>
        </label>
      </div>
      <label>
        <span class="lbl"><?= h(t('entries.note', 'Notiz')) ?></span>
        <input type="text" name="note" maxlength="255" placeholder="<?= h(t('processes.note_placeholder', 'Optional: was wurde gemacht?')) ?>">
      </label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= h(t('common.save', 'Speichern')) ?></button>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2><?= h(t('processes.list_h2', 'Deine Buchungen')) ?> <span class="muted"><?= h(t('processes.list_window', '(letzte 90 Tage + geplante)')) ?></span></h2>
    <?php if (!$entries): ?>
      <p class="muted"><?= h(t('processes.empty', 'Noch keine internen Prozess-Buchungen.')) ?></p>
    <?php else: ?>
      <table class="table">
        <thead><tr>
          <th><?= h(t('processes.category', 'Prozessart')) ?></th>
          <th><?= h(t('entries.start', 'Beginn')) ?></th>
          <th class="r"><?= h(t('processes.duration', 'Dauer')) ?></th>
          <th><?= h(t('entries.note', 'Notiz')) ?></th>
          <th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($entries as $e): ?>
            <tr>
              <td><?= h($e['category_label']) ?></td>
              <td class="nowrap muted"><?= h(substr((string) $e['start_ts'], 0, 16)) ?></td>
              <td class="r nowrap"><?= number_format(((int) $e['duration_min']) / 60, 2, ',', '.') ?> h</td>
              <td class="muted"><?= h($e['note'] ?? '') ?></td>
              <td class="r">
                <form method="post" action="<?= h(route('process_delete')) ?>" class="inline" onsubmit="return confirm('<?= h(t('processes.confirm_delete', 'Buchung loeschen?')) ?>')">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
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
