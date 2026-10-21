<?php
/**
 * @var array $batches
 * @var ?array $preview
 * @var array $all_users
 * @var int $filter_user
 * @var string $filter_from
 * @var string $filter_to
 */
?>
<h1 class="page-title"><?= h(t('imports.title', 'CSV-Import')) ?></h1>

<?php if ($preview): ?>
  <div class="card">
    <div class="card-head">
      <h2><?= h(sprintf(t('imports.preview_h2', 'Vorschau: %s'), $preview['label'])) ?><?= tip_text(sprintf(t('imports.preview_intro', 'Trennzeichen erkannt: <code>%s</code>. Uebersprungen wird jede Zeile, deren Kunde/Projekt/Aufgabe nicht schon existiert &mdash; die Import-UI erzeugt bewusst keine Struktur.'), $preview['stats']['delim'])) ?></h2>
      <span class="pill"><?= h(sprintf(t('imports.stats_label', '%d gueltig / %d uebersprungen'), (int) $preview['stats']['ok'], (int) $preview['stats']['bad'])) ?></span>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th><?= h(t('imports.col_status', 'Status')) ?></th>
          <th><?= h(t('entries.start')) ?></th>
          <th><?= h(t('entries.end')) ?></th>
          <th><?= h(t('struct.client')) ?></th>
          <th><?= h(t('projects.col_project')) ?></th>
          <th><?= h(t('dash.col_task')) ?></th>
          <th><?= h(t('users.col_user')) ?></th>
          <th><?= h(t('entries.note')) ?></th>
          <th><?= h(t('imports.col_error', 'Fehler')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($preview['rows'], 0, 200) as $r): ?>
          <tr class="<?= $r['ok'] ? '' : 'muted' ?>">
            <td><?= $r['ok']
                ? '<span class="ok-mark">' . h(t('install.ok')) . '</span>'
                : '<span class="bad-mark">' . h(t('imports.skip', 'skip')) . '</span>' ?></td>
            <td class="nowrap"><?= h($r['start_ts'] ?? ($r['raw']['start_ts'] ?? '')) ?></td>
            <td class="nowrap"><?= h($r['end_ts'] ?? ($r['raw']['end_ts'] ?? '')) ?></td>
            <td><?= h($r['raw']['client_code'] ?? $r['raw']['client_name'] ?? '') ?></td>
            <td><?= h($r['raw']['project_code'] ?? $r['raw']['project_name'] ?? '') ?></td>
            <td><?= h($r['raw']['task_name'] ?? '') ?></td>
            <td><?= h($r['raw']['user_username'] ?? '') ?></td>
            <td><?= h($r['raw']['note'] ?? '') ?></td>
            <td class="muted"><?= h($r['error'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (count($preview['rows']) > 200): ?>
      <p class="muted"><?= h(t('imports.first_200_short', 'Zeige die ersten 200 Zeilen.')) ?><?= tip('imports.first_200_hint') ?></p>
    <?php endif; ?>

    <div class="form-actions">
      <form method="post" action="<?= h(route('imports_confirm')) ?>" class="inline">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit"><?= h(sprintf(t('imports.confirm_btn', 'Import bestaetigen (%d Buchungen)'), (int) $preview['stats']['ok'])) ?></button>
      </form>
      <form method="post" action="<?= h(route('imports_discard')) ?>" class="inline">
        <?= csrf_field() ?>
        <button class="btn" type="submit"><?= h(t('imports.discard', 'Verwerfen')) ?></button>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="card narrow">
    <h2><?= h(t('imports.new_h2', 'Neue CSV hochladen')) ?><?= tip('imports.new_intro') ?></h2>
    <form method="post" action="<?= h(route('imports_upload')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label><?= h(t('imports.label', 'Bezeichnung')) ?> <input type="text" name="label" placeholder="<?= h(t('imports.label_placeholder', 'Q3 2026 Backfill')) ?>"></label>
      <label><?= h(t('imports.file', 'Datei')) ?> <input type="file" name="csv" accept=".csv,text/csv" required></label>
      <div class="form-actions"><button class="btn btn-primary" type="submit"><?= h(t('imports.upload_btn', 'Hochladen und Vorschau')) ?></button></div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <h2><?= h(t('imports.history_h2', 'Vergangene Importe')) ?></h2>

  <form method="get" action="<?= h(route('imports')) ?>" class="inline-filter" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="r" value="imports">
    <label><?= h(t('imports.filter_user', 'Benutzer')) ?>
      <select name="user_id">
        <option value="0"><?= h(t('common.all', 'Alle')) ?></option>
        <?php foreach ($all_users as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= $filter_user === (int) $u['id'] ? 'selected' : '' ?>>
            <?= h(($u['display_name'] ?: $u['username']) . ' (' . $u['username'] . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= h(t('imports.filter_from', 'Ab')) ?>
      <input type="date" name="from" value="<?= h($filter_from) ?>">
    </label>
    <label><?= h(t('imports.filter_to', 'Bis')) ?>
      <input type="date" name="to" value="<?= h($filter_to) ?>">
    </label>
    <button class="btn btn-sm" type="submit"><?= h(t('common.apply', 'Anwenden')) ?></button>
    <?php if ($filter_user || $filter_from !== '' || $filter_to !== ''): ?>
      <a class="btn btn-sm btn-ghost" href="<?= h(route('imports')) ?>"><?= h(t('common.reset', 'Zuruecksetzen')) ?></a>
    <?php endif; ?>
  </form>

  <?php if (!$batches): ?>
    <p class="muted"><?= h(t('imports.history_empty', 'Noch keine Importe.')) ?></p>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th><?= h(t('imports.col_id', 'ID')) ?></th>
        <th><?= h(t('imports.label', 'Bezeichnung')) ?></th>
        <th><?= h(t('imports.col_user', 'Benutzer')) ?></th>
        <th><?= h(t('imports.col_source', 'Quelle')) ?></th>
        <th class="r"><?= h(t('imports.col_entries', 'Buchungen')) ?></th>
        <th><?= h(t('imports.col_created', 'Angelegt')) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td>#<?= (int) $b['id'] ?></td>
          <td><?= h($b['label']) ?></td>
          <td>
            <?php if (!empty($b['user_username'])): ?>
              <?= h($b['user_display_name'] ?: $b['user_username']) ?>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td class="muted"><?= h($b['source']) ?></td>
          <td class="r"><?= (int) $b['entry_count'] ?></td>
          <td class="nowrap"><?= h(fmt_dt($b['created_at'])) ?></td>
          <td class="r">
            <a class="btn btn-sm btn-ghost" href="<?= h(route('imports_show', ['id' => (int) $b['id']])) ?>"><?= h(t('imports.details_btn', 'Details')) ?></a>
            <form method="post" action="<?= h(route('imports_delete')) ?>" class="inline" data-confirm="<?= h(sprintf(t('imports.delete_confirm', 'Batch #%d mit %d Buchungen unwiderruflich loeschen?'), (int) $b['id'], (int) $b['entry_count'])) ?>">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit"><?= h(t('imports.delete_btn', 'Batch loeschen')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
