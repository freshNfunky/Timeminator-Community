<?php
/**
 * @var array $entries @var array $clients @var array $projects
 * @var array $filter  @var int   $total
 * @var string $view        'list' or 'calendar'
 * @var string $pro_url
 * @var ?DateTimeImmutable $week_start
 */
$__isCalendar = ($view ?? 'list') === 'calendar';
$__filterQp = array_filter($filter, static fn($v) => $v !== '' && $v !== 0 && $v !== null);
if ($__isCalendar) {
    // Navigation controls the from/to in calendar mode — don't leak the
    // computed week bounds into the toggle links.
    unset($__filterQp['from'], $__filterQp['to']);
}
?>
<div class="entries-tabs" role="tablist">
  <a role="tab" aria-selected="<?= $__isCalendar ? 'true' : 'false' ?>"
     class="entries-tab<?= $__isCalendar ? ' is-active' : '' ?>"
     href="<?= h(route('entries', $__filterQp + ['view' => 'calendar'])) ?>">
    <?= h(t('entries.view_calendar', 'Kalender')) ?>
  </a>
  <a role="tab" aria-selected="<?= !$__isCalendar ? 'true' : 'false' ?>"
     class="entries-tab<?= !$__isCalendar ? ' is-active' : '' ?>"
     href="<?= h(route('entries', $__filterQp + ['view' => 'list'])) ?>">
    <?= h(t('entries.view_list', 'Liste')) ?>
  </a>
  <span class="entries-tab-spacer"></span>
  <button type="button" class="btn btn-primary btn-sm" id="entryAddBtn"><?= h(t('entries.add', '+ Eintrag')) ?></button>
</div>

<form method="get" class="card filter-bar">
  <input type="hidden" name="r" value="entries">
  <?php if ($__isCalendar): ?><input type="hidden" name="view" value="calendar"><?php endif; ?>
  <?php if (!$__isCalendar): ?>
    <label><?= h(t('entries.from', 'Von')) ?> <input type="date" name="from" value="<?= h($filter['from']) ?>"></label>
    <label><?= h(t('entries.to', 'Bis')) ?> <input type="date" name="to" value="<?= h($filter['to']) ?>"></label>
  <?php endif; ?>
  <label><?= h(t('nav.clients')) ?>
    <select name="client_id">
      <option value="0"><?= h(t('common.all', 'alle')) ?></option>
      <?php foreach ($clients as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $filter['client_id'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= h(t('nav.projects')) ?>
    <select name="project_id">
      <option value="0"><?= h(t('common.all', 'alle')) ?></option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int) $p['id'] ?>" <?= $filter['project_id'] == $p['id'] ? 'selected' : '' ?>><?= h($p['client_name'] . ' / ' . $p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn" type="submit"><?= h(t('common.filter', 'Filtern')) ?></button>
  <?php if (!$__isCalendar): ?>
    <a class="btn btn-sm" href="<?= h(route('entries_export', $__filterQp + ['format' => 'csv'])) ?>">CSV</a>
    <a class="btn btn-sm" href="<?= h(route('entries_export', $__filterQp + ['format' => 'json'])) ?>">JSON</a>
  <?php endif; ?>
</form>

<?php if ($__isCalendar): ?>
  <?php require __DIR__ . '/calendar_week.php'; ?>
<?php else: ?>
<?php
// v1.0: Liste zeigt alle 3 Typen gemischt, nach Datum sortiert, mit Typ-Pill.
$__rows = [];
foreach ($entries as $__e) {
    $__rows[] = [
        'kind'  => 'project',
        'id'    => (int) $__e['id'],
        'sort'  => (string) $__e['start_ts'],
        'start' => (string) $__e['start_ts'],
        'end'   => (string) ($__e['end_ts'] ?? ''),
        'label' => (string) ($__e['client_name'] ?? '') . ' / ' . (string) ($__e['project_name'] ?? ''),
        'sub'   => (string) ($__e['task_name'] ?? ''),
        'note'  => (string) ($__e['note'] ?? ''),
        'dur'   => (int) ($__e['duration_min'] ?? 0),
    ];
}
foreach (($processes ?? []) as $__p) {
    $__rows[] = [
        'kind'  => 'process',
        'id'    => (int) $__p['id'],
        'sort'  => (string) $__p['start_ts'],
        'start' => (string) $__p['start_ts'],
        'end'   => (string) ($__p['end_ts'] ?? ''),
        'label' => (string) ($__p['category_label'] ?? ''),
        'sub'   => '',
        'note'  => (string) ($__p['note'] ?? ''),
        'dur'   => (int) ($__p['duration_min'] ?? 0),
    ];
}
foreach (($absences ?? []) as $__a) {
    $__rows[] = [
        'kind'  => 'absence',
        'id'    => (int) $__a['id'],
        'sort'  => (string) $__a['start_date'] . ' 00:00:00',
        'start' => (string) $__a['start_date'],
        'end'   => (string) ($__a['end_date'] ?? $__a['start_date']),
        'label' => (string) ($__a['category_label'] ?? ''),
        'sub'   => $__a['hours'] !== null ? sprintf('%s h', number_format((float) $__a['hours'], 1, ',', '.')) : '',
        'note'  => (string) ($__a['note'] ?? ''),
        'dur'   => 0,
    ];
}
usort($__rows, static fn($a, $b) => strcmp($b['sort'], $a['sort']));
?>
<div class="card">
  <div class="card-head">
    <h2><?= h(sprintf(t('entries.count_label', '%d Eintraege'), count($__rows))) ?></h2>
    <span class="pill"><?= h(sprintf(t('entries.sum_label', 'Summe: %s'), fmt_hours($total))) ?></span>
  </div>
  <?php if (!$__rows): ?>
    <p class="muted"><?= h(t('entries.empty_range', 'Keine Eintraege im gewaehlten Zeitraum.')) ?></p>
  <?php else: ?>
  <table class="table">
    <thead><tr>
      <th><?= h(t('entries.col_type', 'Typ')) ?></th>
      <th><?= h(t('entries.start', 'Start')) ?></th>
      <th><?= h(t('entries.end', 'Ende')) ?></th>
      <th><?= h(t('entries.col_subject', 'Projekt / Kategorie')) ?></th>
      <th><?= h(t('entries.col_detail', 'Aufgabe / Notiz')) ?></th>
      <th class="r"><?= h(t('dash.col_duration')) ?></th>
    </tr></thead>
    <tbody>
      <?php foreach ($__rows as $__r):
        $__pillClass = $__r['kind'] === 'project' ? 'pill' : 'pill pill-' . $__r['kind'];
        $__pillLabel = $__r['kind'] === 'project'  ? t('entries.pill_project',  'Projekt')
                     : ($__r['kind'] === 'process' ? t('entries.pill_process',  'Prozess')
                     : t('entries.pill_absence',  'Abwesenheit'));
        $__fmtStart = $__r['kind'] === 'absence'
          ? h((new DateTimeImmutable($__r['start']))->format('d.m.Y'))
          : h(fmt_dt($__r['start']));
        $__fmtEnd = $__r['kind'] === 'absence'
          ? h((new DateTimeImmutable($__r['end']))->format('d.m.Y'))
          : ($__r['end'] !== '' ? h(fmt_dt($__r['end'])) : '—');
      ?>
      <tr>
        <td><span class="<?= h($__pillClass) ?>"><?= h($__pillLabel) ?></span></td>
        <td><?= $__fmtStart ?></td>
        <td><?= $__fmtEnd ?></td>
        <td><strong><?= h($__r['label']) ?></strong></td>
        <td>
          <?php if ($__r['sub'] !== ''): ?><?= h($__r['sub']) ?><?php endif; ?>
          <?php if ($__r['note'] !== ''): ?><span class="muted"><?= $__r['sub'] !== '' ? ' · ' : '' ?><?= h($__r['note']) ?></span><?php endif; ?>
        </td>
        <td class="r nowrap">
          <?= $__r['dur'] > 0 ? h(fmt_hm($__r['dur'])) : '' ?>
          <button type="button" class="btn btn-sm entry-edit-btn" data-entry-kind="<?= h($__r['kind']) ?>" data-entry-id="<?= (int) $__r['id'] ?>"><?= h(t('common.edit_short', 'bearb.')) ?></button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php
// v1.0 Dialog + Datenpaket zentral — damit sowohl Kalender- als auch
// Liste-View denselben Create/Edit-Flow bekommen (konsistent mit Pro).
$__dlgEntries = [];
foreach (($entries ?? []) as $__e) {
    $__dlgEntries[(int) $__e['id']] = [
        'id'       => (int) $__e['id'],
        'task_id'  => (int) $__e['task_id'],
        'start_ts' => (string) $__e['start_ts'],
        'end_ts'   => (string) ($__e['end_ts'] ?? ''),
        'note'     => (string) ($__e['note'] ?? ''),
    ];
}
$__dlgProcesses = [];
foreach (($processes ?? []) as $__p) {
    $__dlgProcesses[(int) $__p['id']] = [
        'id'          => (int) $__p['id'],
        'category_id' => (int) $__p['category_id'],
        'start_ts'    => (string) $__p['start_ts'],
        'end_ts'      => (string) $__p['end_ts'],
        'note'        => (string) ($__p['note'] ?? ''),
    ];
}
?>
<dialog id="calDialog" class="cal-dialog">
  <form method="dialog" id="calDialogForm">
    <h3 id="calDialogTitle"><?= h(t('cal.dialog_new', 'Neuer Eintrag')) ?></h3>
    <input type="hidden" id="calEntryId" value="">

    <label for="calType"><?= h(t('cal.dialog_type', 'Typ')) ?>
      <select id="calType">
        <option value="project"><?= h(t('cal.type_project', 'Projekt-Zeit')) ?></option>
        <?php if ($processCats ?? []): ?>
          <optgroup label="<?= h(t('cal.group_processes', 'Interne Prozesse')) ?>">
            <?php foreach ($processCats as $__c): ?>
              <option value="p:<?= (int) $__c['id'] ?>"><?= h($__c['label']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endif; ?>
        <?php if ($absenceCats ?? []): ?>
          <optgroup label="<?= h(t('cal.group_absences', 'Abwesenheiten')) ?>">
            <?php foreach ($absenceCats as $__c): ?>
              <option value="<?= (int) $__c['id'] ?>" data-account="<?= h((string) ($__c['account'] ?? '')) ?>"><?= h($__c['label']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endif; ?>
      </select>
    </label>

    <label id="calTaskWrap"><?= h(t('cal.dialog_task', 'Aufgabe')) ?>
      <select id="calTask">
        <option value=""><?= h(t('dash.task_placeholder', 'Aufgabe waehlen …')) ?></option>
        <?php
          $__curGroup = null;
          foreach (($tasks ?? []) as $__t):
            $__g = (string) ($__t['client_name'] ?? '') . ' / ' . (string) ($__t['project_name'] ?? '');
            if ($__g !== $__curGroup) {
              if ($__curGroup !== null) echo '</optgroup>';
              echo '<optgroup label="' . h($__g) . '">';
              $__curGroup = $__g;
            }
        ?>
          <option value="<?= (int) $__t['id'] ?>"><?= h((string) $__t['name']) ?></option>
        <?php endforeach; if ($__curGroup !== null) echo '</optgroup>'; ?>
      </select>
    </label>

    <div class="row" id="calTimeRow">
      <label class="grow"><?= h(t('entries.start', 'Start')) ?>
        <input type="datetime-local" id="calStart">
      </label>
      <label class="grow"><?= h(t('entries.end', 'Ende')) ?>
        <input type="datetime-local" id="calEnd">
      </label>
    </div>

    <div class="row" id="calDateRow" hidden>
      <label class="grow"><?= h(t('cal.dialog_from', 'Von')) ?>
        <input type="date" id="calDateFrom">
      </label>
      <label class="grow"><?= h(t('cal.dialog_to', 'Bis')) ?>
        <input type="date" id="calDateTo">
      </label>
    </div>

    <label id="calHoursWrap" hidden><?= h(t('cal.dialog_hours', 'Stunden (optional)')) ?>
      <input type="text" id="calHours" inputmode="decimal" placeholder="z.B. 4">
    </label>

    <label><?= h(t('entries.note', 'Notiz')) ?>
      <input type="text" id="calNote" maxlength="500">
    </label>

    <p class="flash flash-err" id="calError" hidden></p>

    <div class="form-actions">
      <button class="btn btn-primary" type="button" id="calSaveBtn"><?= h(t('common.save', 'Speichern')) ?></button>
      <button class="btn btn-danger btn-sm" type="button" id="calDeleteBtn" hidden><?= h(t('common.delete', 'Loeschen')) ?></button>
      <button class="btn btn-ghost" type="button" id="calCloseBtn"><?= h(t('common.cancel', 'abbrechen')) ?></button>
    </div>
  </form>
</dialog>

<script nonce="<?= h(csp_nonce()) ?>" type="application/json" id="calDialogData"><?= json_encode([
    'csrf' => csrf_token(),
    'entries'   => $__dlgEntries,
    'processes' => $__dlgProcesses,
    'absences'  => array_values(array_map(static fn($a) => [
        'id'          => (int) $a['id'],
        'cat_id'      => (int) $a['category_id'],
        'start_date'  => (string) $a['start_date'],
        'end_date'    => (string) ($a['end_date'] ?? $a['start_date']),
        'hours'       => $a['hours'] !== null ? (float) $a['hours'] : null,
        'note'        => (string) ($a['note'] ?? ''),
    ], $absences ?? [])),
    'urls' => [
        'entry_save'    => route('entry_api_save'),
        'entry_delete'  => route('entry_api_delete'),
        'absence_save'  => route('absence_api_save'),
        'absence_delete'=> route('absence_api_delete'),
        'process_save'  => route('process_api_save'),
        'process_delete'=> route('process_api_delete'),
    ],
    'i18n' => [
        'title_new'      => t('cal.dialog_new', 'Neuer Eintrag'),
        'title_edit'     => t('cal.dialog_edit', 'Eintrag bearbeiten'),
        'confirm_delete' => t('cal.dialog_confirm_delete', 'Eintrag loeschen?'),
        'err_generic'    => t('cal.dialog_err_generic', 'Konnte nicht speichern.'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= h(asset('calendar.js')) ?>" nonce="<?= h(csp_nonce()) ?>"></script>
