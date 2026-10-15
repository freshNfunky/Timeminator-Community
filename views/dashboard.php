<?php
/** @var ?array $running @var array $recent @var array $tasks
 *  @var int $minToday @var int $minWeek @var int $minMonth
 *  @var array $planning   Repo::projectPlanningSnapshot(); may be empty */
?>
<div class="grid-tiles">
  <div class="tile"><div class="tile-val"><?= h(fmt_hours($minToday)) ?></div><div class="tile-lbl"><?= h(t('dash.today')) ?></div></div>
  <div class="tile"><div class="tile-val"><?= h(fmt_hours($minWeek)) ?></div><div class="tile-lbl"><?= h(t('dash.this_week')) ?></div></div>
  <div class="tile"><div class="tile-val"><?= h(fmt_hours($minMonth)) ?></div><div class="tile-lbl"><?= h(t('dash.this_month')) ?></div></div>
</div>

<div class="card timer-card">
  <?php if ($running): ?>
    <div class="timer-running" data-start="<?= h($running['start_ts']) ?>">
      <div>
        <span class="badge badge-live"><?= h(t('dash.timer_running_badge', 'laeuft')) ?></span>
        <strong><?= h($running['client_name']) ?> / <?= h($running['project_name']) ?></strong>
        <span class="muted">· <?= h($running['task_name']) ?></span>
      </div>
      <div class="timer-clock" id="timerClock">00:00:00</div>
      <form method="post" action="<?= h(route('timer_stop')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-danger" type="submit"><?= h(t('dash.timer_stop')) ?></button>
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
      <label class="grow"><?= h(t('dash.note')) ?>
        <input type="text" name="note" placeholder="<?= h(t('dash.task_placeholder')) ?>">
      </label>
      <button class="btn btn-primary" type="submit"><?= h(t('dash.timer_start')) ?></button>
    </form>
    <?php if (!$tasks): ?>
      <p class="muted"><?= h(t('dash.no_tasks')) ?>
        <a href="<?= h(route('clients')) ?>"><?= h(t('nav.clients')) ?></a>,
        <a href="<?= h(route('projects')) ?>"><?= h(t('nav.projects')) ?></a>
        <?= h(t('dash.no_tasks_and', 'und')) ?>
        <a href="<?= h(route('tasks')) ?>"><?= h(t('nav.tasks')) ?></a><?= h(t('dash.no_tasks_cont')) ?></p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if (!empty($planning)): ?>
  <?php
    // Precompute rows + percentages here so the <style> block below can
    // emit one width rule per row (strict CSP forbids inline style="…").
    $__planningRows = [];
    foreach ($planning as $__i => $__p) {
        $__targetMin = (int) round($__p['target_hours'] * 60);
        $__pct = $__targetMin > 0 ? (int) round(($__p['actual_minutes'] / $__targetMin) * 100) : 0;
        $__barClass = $__pct > 100 ? 'planning-bar-over' : ($__pct >= 80 ? 'planning-bar-warn' : 'planning-bar-ok');
        $__planningRows[] = [
            'i'        => (int) $__i,
            'id'       => (int) $__p['id'],
            'client'   => (string) $__p['client_name'],
            'name'     => (string) $__p['name'],
            'target_h' => (float) $__p['target_hours'],
            'actual_m' => (int) $__p['actual_minutes'],
            'pct'      => $__pct,
            'fill'     => min(100, max(0, $__pct)),
            'barcls'   => $__barClass,
        ];
    }
  ?>
  <style nonce="<?= h(csp_nonce()) ?>">
  <?php foreach ($__planningRows as $__r): ?>
    .planning-bar-fill-<?= $__r['i'] ?>{width:<?= $__r['fill'] ?>%}
  <?php endforeach; ?>
  </style>
  <div class="card">
    <div class="card-head">
      <h2><?= h(t('planning.dashboard_tile_title', 'Projekt-Planung')) ?></h2>
      <div>
        <span class="pill"><?= (int) count($__planningRows) ?></span>
        <?php if (Auth::can('structure.manage')): ?>
          <a class="btn btn-sm btn-ghost" href="<?= h(route('report_projects')) ?>"><?= h(t('planning.full_report', 'Vollstaendiger Report')) ?></a>
        <?php endif; ?>
      </div>
    </div>
    <table class="table">
      <thead>
        <tr>
          <th><?= h(t('planning.col_project', 'Projekt')) ?></th>
          <th class="r"><?= h(t('planning.col_target', 'Soll (h)')) ?></th>
          <th class="r"><?= h(t('planning.col_actual', 'Ist (h)')) ?></th>
          <th><?= h(t('planning.col_progress', 'Fortschritt')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($__planningRows as $__r): ?>
          <tr>
            <td><?= h($__r['client']) ?> / <a href="<?= h(route('project_show', ['id' => (int) $__r['id']])) ?>"><?= h($__r['name']) ?></a></td>
            <td class="r"><?= h(number_format($__r['target_h'], 2, ',', '.')) ?></td>
            <td class="r"><?= h(fmt_hours($__r['actual_m'])) ?></td>
            <td>
              <div class="planning-bar">
                <div class="planning-bar-fill planning-bar-fill-<?= $__r['i'] ?> <?= $__r['barcls'] ?>"></div>
              </div>
              <small class="muted"><?= (int) $__r['pct'] ?>%</small>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted planning-pro-teaser">
      <?= h(t('planning.pro_teaser.intro', 'Wochen-/Monats-/Person-Breakdown, Gantt-Ansicht und Budget-Report sind Teil von')) ?>
      <a href="<?= h(route('pro_budget')) ?>"><strong><?= h(t('planning.pro_teaser.link', 'Timeminator Pro')) ?></strong></a>.
    </p>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2><?= h(t('dash.recent')) ?></h2>
    <a class="btn btn-sm" href="<?= h(route('entry_form')) ?>"><?= h(t('dash.add_manual')) ?></a>
  </div>
  <?php if (!$recent): ?>
    <p class="muted"><?= h(t('dash.recent_empty')) ?></p>
  <?php else: ?>
  <table class="table">
    <thead><tr>
      <th><?= h(t('dash.col_start')) ?></th>
      <th><?= h(t('dash.col_client_project')) ?></th>
      <th><?= h(t('dash.col_task')) ?></th>
      <th class="r"><?= h(t('dash.col_duration')) ?></th>
    </tr></thead>
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
