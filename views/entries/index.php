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
  <span class="view-toggle">
    <a class="btn btn-sm <?= $__isCalendar ? 'btn-primary' : '' ?>" href="<?= h(route('entries', $__filterQp)) ?>"><?= h(t('entries.view_calendar', 'Kalender')) ?></a>
    <a class="btn btn-sm <?= !$__isCalendar ? 'btn-primary' : '' ?>" href="<?= h(route('entries', $__filterQp + ['view' => 'list'])) ?>"><?= h(t('entries.view_list', 'Liste')) ?></a>
  </span>
  <?php if (!$__isCalendar): ?>
    <a class="btn" href="<?= h(route('entries_export', $__filterQp + ['format' => 'csv'])) ?>">CSV</a>
    <a class="btn" href="<?= h(route('entries_export', $__filterQp + ['format' => 'json'])) ?>">JSON</a>
  <?php endif; ?>
  <a class="btn btn-primary" href="<?= h(route('entry_form')) ?>"><?= h(t('entries.add', '+ Eintrag')) ?></a>
</form>

<?php if ($__isCalendar): ?>
  <?php require __DIR__ . '/calendar_week.php'; ?>
<?php else: ?>
<div class="card">
  <div class="card-head">
    <h2><?= h(sprintf(t('entries.count_label', '%d Eintraege'), count($entries))) ?></h2>
    <span class="pill"><?= h(sprintf(t('entries.sum_label', 'Summe: %s'), fmt_hours($total))) ?></span>
  </div>
  <?php if (!$entries): ?>
    <p class="muted"><?= h(t('entries.empty_range', 'Keine Eintraege im gewaehlten Zeitraum.')) ?></p>
  <?php else: ?>
  <table class="table">
    <thead><tr>
      <th><?= h(t('entries.start', 'Start')) ?></th>
      <th><?= h(t('entries.end', 'Ende')) ?></th>
      <th><?= h(t('dash.col_client_project')) ?></th>
      <th><?= h(t('dash.col_task')) ?></th>
      <th class="r"><?= h(t('dash.col_duration')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($entries as $e): ?>
      <tr>
        <td><?= h(fmt_dt($e['start_ts'])) ?></td>
        <td><?= h(fmt_dt($e['end_ts'])) ?></td>
        <td><span class="dot" data-color="<?= h(strtolower((string) ($e['client_color'] ?: ''))) ?>"></span><?= h($e['client_name']) ?> / <?= h($e['project_name']) ?></td>
        <td><?= h($e['task_name']) ?><?= $e['note'] ? ' <span class="muted">· ' . h($e['note']) . '</span>' : '' ?></td>
        <td class="r"><?= h(fmt_hm($e['duration_min'])) ?></td>
        <td class="r nowrap">
          <a class="btn btn-sm" href="<?= h(route('entry_form', ['id' => $e['id']])) ?>"><?= h(t('common.edit_short', 'bearb.')) ?></a>
          <form method="post" action="<?= h(route('entry_delete')) ?>" class="inline" data-confirm="<?= h(t('entries.delete_confirm', 'Eintrag loeschen?')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">x</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>
