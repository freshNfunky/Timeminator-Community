<?php
/** @var array $entries @var array $clients @var array $projects @var array $filter @var int $total */
?>
<form method="get" class="card filter-bar">
  <input type="hidden" name="r" value="entries">
  <label>Von <input type="date" name="from" value="<?= h($filter['from']) ?>"></label>
  <label>Bis <input type="date" name="to" value="<?= h($filter['to']) ?>"></label>
  <label>Kunde
    <select name="client_id">
      <option value="0">alle</option>
      <?php foreach ($clients as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $filter['client_id'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Projekt
    <select name="project_id">
      <option value="0">alle</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int) $p['id'] ?>" <?= $filter['project_id'] == $p['id'] ? 'selected' : '' ?>><?= h($p['client_name'] . ' / ' . $p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn" type="submit">Filtern</button>
  <a class="btn" href="<?= h(route('entries_export', array_filter($filter, static fn($v) => $v !== '' && $v !== 0 && $v !== null) + ['format' => 'csv'])) ?>">CSV</a>
  <a class="btn" href="<?= h(route('entries_export', array_filter($filter, static fn($v) => $v !== '' && $v !== 0 && $v !== null) + ['format' => 'json'])) ?>">JSON</a>
  <a class="btn btn-primary" href="<?= h(route('entry_form')) ?>">+ Eintrag</a>
</form>

<div class="card">
  <div class="card-head">
    <h2><?= count($entries) ?> Eintraege</h2>
    <span class="pill">Summe: <?= h(fmt_hours($total)) ?></span>
  </div>
  <?php if (!$entries): ?>
    <p class="muted">Keine Eintraege im gewaehlten Zeitraum.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Start</th><th>Ende</th><th>Kunde / Projekt</th><th>Aufgabe</th><th class="r">Dauer</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($entries as $e): ?>
      <tr>
        <td><?= h(fmt_dt($e['start_ts'])) ?></td>
        <td><?= h(fmt_dt($e['end_ts'])) ?></td>
        <td><span class="dot" data-color="<?= h(strtolower((string) ($e['client_color'] ?: ''))) ?>"></span><?= h($e['client_name']) ?> / <?= h($e['project_name']) ?></td>
        <td><?= h($e['task_name']) ?><?= $e['note'] ? ' <span class="muted">· ' . h($e['note']) . '</span>' : '' ?></td>
        <td class="r"><?= h(fmt_hm($e['duration_min'])) ?></td>
        <td class="r nowrap">
          <a class="btn btn-sm" href="<?= h(route('entry_form', ['id' => $e['id']])) ?>">bearb.</a>
          <form method="post" action="<?= h(route('entry_delete')) ?>" class="inline" data-confirm="Eintrag loeschen?">
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
