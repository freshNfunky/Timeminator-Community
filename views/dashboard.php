<?php
/** @var ?array $running @var array $recent @var array $tasks
 *  @var int $minToday @var int $minWeek @var int $minMonth */
?>
<div class="grid-tiles">
  <div class="tile"><div class="tile-val"><?= h(fmt_hours($minToday)) ?></div><div class="tile-lbl">Heute</div></div>
  <div class="tile"><div class="tile-val"><?= h(fmt_hours($minWeek)) ?></div><div class="tile-lbl">Diese Woche</div></div>
  <div class="tile"><div class="tile-val"><?= h(fmt_hours($minMonth)) ?></div><div class="tile-lbl">Dieser Monat</div></div>
</div>

<div class="card timer-card">
  <?php if ($running): ?>
    <div class="timer-running" data-start="<?= h($running['start_ts']) ?>">
      <div>
        <span class="badge badge-live">laeuft</span>
        <strong><?= h($running['client_name']) ?> / <?= h($running['project_name']) ?></strong>
        <span class="muted">· <?= h($running['task_name']) ?></span>
      </div>
      <div class="timer-clock" id="timerClock">00:00:00</div>
      <form method="post" action="<?= h(route('timer_stop')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-danger" type="submit">Stoppen &amp; buchen</button>
      </form>
    </div>
  <?php else: ?>
    <form method="post" action="<?= h(route('timer_start')) ?>" class="timer-start">
      <?= csrf_field() ?>
      <?php
      $selectedTaskId = 0;
      $wrapClass = 'tp-stack grow';
      require __DIR__ . '/partials/task_picker.php';
      ?>
      <label class="grow">Notiz (optional)
        <input type="text" name="note" placeholder="Woran arbeitest du?">
      </label>
      <button class="btn btn-primary" type="submit">Timer starten</button>
    </form>
    <?php if (!$tasks): ?>
      <p class="muted">Noch keine Aufgaben. Lege zuerst
        <a href="<?= h(route('clients')) ?>">Kunden</a>,
        <a href="<?= h(route('projects')) ?>">Projekte</a> und
        <a href="<?= h(route('tasks')) ?>">Aufgaben</a> an.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head">
    <h2>Letzte Eintraege</h2>
    <a class="btn btn-sm" href="<?= h(route('entry_form')) ?>">+ Manueller Eintrag</a>
  </div>
  <?php if (!$recent): ?>
    <p class="muted">Noch keine Buchungen.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Start</th><th>Kunde / Projekt</th><th>Aufgabe</th><th class="r">Dauer</th></tr></thead>
    <tbody>
      <?php foreach ($recent as $e): ?>
      <tr>
        <td><?= h(fmt_dt($e['start_ts'])) ?></td>
        <td><?= h($e['client_name']) ?> / <?= h($e['project_name']) ?></td>
        <td><?= h($e['task_name']) ?><?= $e['note'] ? ' <span class="muted">· ' . h($e['note']) . '</span>' : '' ?></td>
        <td class="r"><?= h(fmt_hm($e['duration_min'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
