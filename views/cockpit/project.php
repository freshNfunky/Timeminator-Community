<?php
/**
 * v1.0 F2 — Projekt-Cockpit Detail (Shell).
 *
 * F2.2: Health-Flags + Review-Turnus.
 * F2.3 (folgt): Soll/Ist-Matrix hier eingehaengt.
 * F2.4 (folgt): Burn-Up-Chart + Tracker-Timeline hier eingehaengt.
 *
 * @var array $project
 * @var ?array $row
 * @var int $default_review_days
 */
$num = fn($n, int $dec = 1) => $n === null ? '—' : number_format((float) $n, $dec, ',', '.');
$f = $row['flags'] ?? ['kickoff' => false, 'closeout' => false, 'deadline_overdue' => false];
?>
<div class="card">
  <div class="card-head">
    <h2><?= h($project['name']) ?><?php if (!empty($project['code'])): ?> <span class="muted">(<?= h($project['code']) ?>)</span><?php endif; ?></h2>
    <div class="row" style="gap:8px;margin:0">
      <a class="btn btn-ghost btn-sm" href="<?= h(route('cockpit')) ?>">&larr; <?= h(t('cockpit.back_to_overview', 'zurueck zur Uebersicht')) ?></a>
      <?php if (Auth::can('structure.manage')): ?>
        <a class="btn btn-ghost btn-sm" href="<?= h(route('project_show', ['id' => $project['id']])) ?>"><?= h(t('cockpit.open_detail_edit', 'Projekt-Details &amp; Tracker &rarr;')) ?></a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($row): ?>
    <div class="row" style="gap:24px;flex-wrap:wrap">
      <div><span class="muted"><?= h(t('cockpit.col_ist', 'Ist')) ?>:</span> <strong><?= $num($row['ist_hours']) ?> h</strong></div>
      <div><span class="muted"><?= h(t('cockpit.col_target', 'Soll')) ?>:</span> <strong><?= $num($row['target_hours']) ?> h</strong></div>
      <div><span class="muted"><?= h(t('cockpit.col_time_pct', 'Zeit-%')) ?>:</span> <strong><?= $row['zeit_pct'] === null ? '—' : $num($row['zeit_pct'], 0) . '%' ?></strong></div>
      <div><span class="muted"><?= h(t('cockpit.col_progress', 'Fortschritt')) ?>:</span>
        <strong><?= $row['progress'] === null ? '—' : $num($row['progress'], 0) . '%' ?></strong>
        <?php if ($row['forecast'] !== null): ?><span class="muted">(F: <?= $num($row['forecast'], 0) ?>%)</span><?php endif; ?>
      </div>
      <div><span class="muted"><?= h(t('cockpit.col_planned_end', 'Ende')) ?>:</span>
        <strong><?= $row['planned_end'] ? h((new DateTimeImmutable($row['planned_end']))->format('d.m.Y')) : '—' ?></strong>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (Auth::can('structure.manage')): ?>
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.health_h3', 'Projekt-Health')) ?></h3>
    <span class="muted"><?= h(t('cockpit.health_subtitle', 'Kickoff, Abschluss und Review-Turnus')) ?></span>
  </div>

  <form method="post" action="<?= h(route('cockpit_health_save')) ?>" class="form-inline">
    <?= csrf_field() ?>
    <input type="hidden" name="id"    value="<?= (int) $project['id'] ?>">
    <input type="hidden" name="field" value="kickoff_at">
    <label class="check">
      <input type="checkbox" name="done" value="1" <?= $f['kickoff'] ? 'checked' : '' ?> onchange="this.form.submit()">
      <?= h(t('cockpit.health_kickoff', 'Kickoff erfolgt')) ?>
      <?php if (!empty($project['kickoff_at'])): ?><span class="muted">(<?= h((new DateTimeImmutable($project['kickoff_at']))->format('d.m.Y')) ?>)</span><?php endif; ?>
    </label>
  </form>

  <form method="post" action="<?= h(route('cockpit_health_save')) ?>" class="form-inline">
    <?= csrf_field() ?>
    <input type="hidden" name="id"    value="<?= (int) $project['id'] ?>">
    <input type="hidden" name="field" value="closeout_at">
    <label class="check">
      <input type="checkbox" name="done" value="1" <?= $f['closeout'] ? 'checked' : '' ?> onchange="this.form.submit()">
      <?= h(t('cockpit.health_closeout', 'Abschluss erfolgt')) ?>
      <?php if (!empty($project['closeout_at'])): ?><span class="muted">(<?= h((new DateTimeImmutable($project['closeout_at']))->format('d.m.Y')) ?>)</span><?php endif; ?>
    </label>
  </form>

  <form method="post" action="<?= h(route('cockpit_review_interval_save')) ?>" class="form-inline" style="margin-top:8px">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
    <label>
      <?= h(t('cockpit.health_review_days', 'Review-Turnus in Tagen (leer = Default')) ?> <?= (int) $default_review_days ?>)
      <input type="number" name="review_interval_days" value="<?= h((string) ($project['review_interval_days'] ?? '')) ?>" min="1" style="width:6em">
    </label>
    <button class="btn btn-ghost btn-sm" type="submit"><?= h(t('common.save')) ?></button>
  </form>
</div>
<?php endif; ?>

<?php
/** @var list<array{id:int,display_name:?string,username:string}> $matrix_members */
/** @var list<string> $matrix_weeks */
/** @var array<int,array<string,int>> $matrix_ist */
/** @var array<int,array<string,int>> $matrix_soll */
/** @var int $plan_total_minutes */
$weekMonday = static function (string $wk): string {
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $wk, $m)) { return $wk; }
    return (new DateTimeImmutable('today'))->setISODate((int) $m[1], (int) $m[2], 1)->format('Y-m-d');
};
$minToHr = fn(int $m) => $m === 0 ? '' : number_format($m / 60, 1, ',', '.');
?>
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.plan_h3', 'Soll / Ist-Matrix')) ?></h3>
    <span class="muted"><?= h(t('cockpit.plan_subtitle', 'Mitarbeiter x Kalenderwoche — Soll-Stunden editierbar (Ist aus Buchungen)')) ?></span>
  </div>

  <?php if (Auth::can('structure.manage')): ?>
    <form method="post" action="<?= h(route('cockpit_plan_total_save')) ?>" class="form-inline" style="margin-bottom:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
      <label><?= h(t('cockpit.plan_total', 'Projekt-Gesamt-Soll (Stunden)')) ?>
        <input type="number" name="planned_hours" step="0.5" min="0" value="<?= h($plan_total_minutes > 0 ? number_format($plan_total_minutes / 60, 1, '.', '') : '') ?>" style="width:6em">
      </label>
      <button class="btn btn-ghost btn-sm" type="submit"><?= h(t('common.save')) ?></button>
    </form>
  <?php endif; ?>

  <?php if (!$matrix_members): ?>
    <p class="muted"><?= h(t('cockpit.plan_no_members', 'Noch keine Buchungen oder Planungen auf diesem Projekt. Mitarbeiter erscheinen hier nach der ersten Buchung bzw. Wochen-Planung.')) ?></p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="table" id="planMatrix" data-project-id="<?= (int) $project['id'] ?>"
             data-save-url="<?= h(route('cockpit_plan_cell_save')) ?>"
             data-csrf="<?= h(csrf_token()) ?>">
        <thead>
          <tr>
            <th><?= h(t('cockpit.plan_col_member', 'Mitarbeiter')) ?></th>
            <?php foreach ($matrix_weeks as $wk):
              $wkStart = $weekMonday($wk);
              $isCurrent = $wk === (new DateTimeImmutable('today'))->format('o-\WW');
            ?>
              <th class="r" title="ab <?= h($wkStart) ?>" <?= $isCurrent ? 'style="background:var(--panel-alt,#f0f0f0)"' : '' ?>>
                <?= h(substr($wk, -2)) ?>
                <br><span class="muted" style="font-weight:normal;font-size:11px"><?= h((new DateTimeImmutable($wkStart))->format('d.m.')) ?></span>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($matrix_members as $u):
          $uid = (int) $u['id'];
          $name = $u['display_name'] ?: $u['username'];
        ?>
          <tr data-user-id="<?= $uid ?>">
            <td><strong><?= h($name) ?></strong></td>
            <?php foreach ($matrix_weeks as $wk):
              $ist  = $matrix_ist[$uid][$wk]  ?? 0;
              $soll = $matrix_soll[$uid][$wk] ?? 0;
              $wkStart = $weekMonday($wk);
            ?>
              <td class="r plan-cell" data-week="<?= h($wkStart) ?>">
                <div class="plan-ist"><?= h($minToHr((int) $ist)) ?: '—' ?></div>
                <?php if (Auth::can('structure.manage')): ?>
                  <input class="plan-soll-input" type="text" inputmode="decimal"
                         value="<?= h($minToHr((int) $soll)) ?>"
                         placeholder="—" style="width:4.5em;text-align:right">
                <?php else: ?>
                  <div class="plan-soll"><?= h($minToHr((int) $soll)) ?: '—' ?></div>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted" style="font-size:12px;margin-top:6px">
      <?= h(t('cockpit.plan_legend', 'Oben: Ist (gebuchte Stunden). Unten: Soll — Werte eintippen und Feld verlassen, speichert automatisch.')) ?>
    </p>
  <?php endif; ?>
</div>

<?php if ($matrix_members && Auth::can('structure.manage')): ?>
<script nonce="<?= h(csp_nonce()) ?>">
(function () {
  var table = document.getElementById('planMatrix');
  if (!table) return;
  var url    = table.getAttribute('data-save-url');
  var pid    = table.getAttribute('data-project-id');
  var token  = table.getAttribute('data-csrf');
  var inputs = table.querySelectorAll('.plan-soll-input');
  inputs.forEach(function (inp) {
    var cell = inp.closest('td');
    var row  = inp.closest('tr');
    var orig = inp.value;
    inp.addEventListener('focus', function () { orig = inp.value; });
    inp.addEventListener('blur',  function () {
      if (inp.value === orig) return;
      inp.disabled = true;
      var body = new URLSearchParams();
      body.set('csrf_token', token);
      body.set('project_id', pid);
      body.set('user_id', row.getAttribute('data-user-id'));
      body.set('week', cell.getAttribute('data-week'));
      body.set('hours', inp.value.replace(',', '.'));
      fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          inp.disabled = false;
          if (!d.ok) {
            alert(d.error || 'Fehler');
            inp.value = orig;
            return;
          }
          var mins = d.minutes || 0;
          var hrs = mins ? (mins / 60).toFixed(1).replace('.', ',') : '';
          inp.value = hrs;
          orig = inp.value;
        })
        .catch(function (e) { inp.disabled = false; alert(String(e)); inp.value = orig; });
    });
    inp.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') { ev.preventDefault(); inp.blur(); }
      if (ev.key === 'Escape') { inp.value = orig; inp.blur(); }
    });
  });
})();
</script>
<?php endif; ?>

<?php
/** @var list<array<string,mixed>> $timeline @var list<array<string,mixed>> $date_shifts */
?>

<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.burnup_h3', 'Burn-Up (kumulative Stunden)')) ?></h3>
    <span class="muted"><?= h(t('cockpit.burnup_subtitle', 'Ist aus Buchungen vs. Zielstunden')) ?></span>
  </div>
  <div class="chart-box" style="height:260px"><canvas id="cockpitBurnup"></canvas></div>
  <div id="cockpitBurnupLegend"></div>
</div>

<?php if (Auth::can('structure.manage')): ?>
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.date_shift_h3', 'Terminverschiebung')) ?></h3>
    <span class="muted"><?= h(t('cockpit.date_shift_subtitle', 'Neues Enddatum mit Begruendung — Verschiebung wird protokolliert und in der Timeline sichtbar.')) ?></span>
  </div>
  <form method="post" action="<?= h(route('cockpit_date_shift_save')) ?>" class="form-inline">
    <?= csrf_field() ?>
    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
    <label><?= h(t('cockpit.date_shift_current', 'Aktuell')) ?>:
      <strong><?= $project['planned_end'] ? h((new DateTimeImmutable($project['planned_end']))->format('d.m.Y')) : '—' ?></strong>
    </label>
    <label><?= h(t('cockpit.date_shift_new', 'Neues Enddatum'))
      ?> <input type="date" name="new_end" value="<?= h((string) ($project['planned_end'] ?? '')) ?>" required></label>
    <label style="flex:1 1 20em"><?= h(t('cockpit.date_shift_reason', 'Begruendung'))
      ?> <input type="text" name="reason" maxlength="500" required></label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= h(t('cockpit.date_shift_save', 'Verschiebung speichern')) ?></button>
    </div>
  </form>

  <?php if ($date_shifts): ?>
    <table class="table" style="margin-top:10px">
      <thead>
        <tr>
          <th><?= h(t('cockpit.ds_col_when',   'Erfasst')) ?></th>
          <th><?= h(t('cockpit.ds_col_from',   'Von')) ?></th>
          <th><?= h(t('cockpit.ds_col_to',     'Nach')) ?></th>
          <th class="r"><?= h(t('cockpit.ds_col_days', 'Tage')) ?></th>
          <th><?= h(t('cockpit.ds_col_reason', 'Begruendung')) ?></th>
          <th><?= h(t('cockpit.ds_col_user',   'Von wem')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($date_shifts as $s):
        $days = DateShiftRepo::diffDays($s['old_end'], $s['new_end']);
      ?>
        <tr>
          <td><?= h((new DateTimeImmutable($s['created_at']))->format('d.m.Y')) ?></td>
          <td><?= $s['old_end'] ? h((new DateTimeImmutable($s['old_end']))->format('d.m.Y')) : '—' ?></td>
          <td><?= $s['new_end'] ? h((new DateTimeImmutable($s['new_end']))->format('d.m.Y')) : '—' ?></td>
          <td class="r <?= $days > 0 ? 'cell-orange' : '' ?>"><?= ($days > 0 ? '+' : '') . (int) $days ?></td>
          <td><?= h((string) $s['reason']) ?></td>
          <td><?= h((string) ($s['display_name'] ?: $s['username'] ?: '—')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.timeline_h3', 'Tracker-Timeline')) ?></h3>
    <span class="muted"><?= h(t('cockpit.timeline_subtitle', 'Fortschrittsmeldungen, Terminverschiebungen und Status-Reports — neueste zuerst')) ?></span>
  </div>

  <?php if (!$timeline): ?>
    <p class="muted"><?= h(t('cockpit.timeline_empty', 'Noch keine Tracker-Eintraege.')) ?></p>
  <?php else: ?>
    <ul class="tracker-timeline">
    <?php foreach ($timeline as $t):
      $when = (new DateTimeImmutable($t['created_at']))->format('d.m.Y');
    ?>
      <li class="tt-item tt-<?= h($t['type']) ?>">
        <div class="tt-head">
          <strong><?= h($when) ?></strong>
          <span class="muted"><?= h((string) ($t['user_username'] ?? '')) ?></span>
          <span class="pill pill-<?= h($t['type']) ?>">
            <?php switch ((string) $t['type']) {
              case 'progress':      echo h(t('cockpit.tt_progress',   'Fortschritt')); break;
              case 'date_shift':    echo h(t('cockpit.tt_date_shift', 'Terminverschiebung')); break;
              case 'status_report': echo h(t('cockpit.tt_status',     'Status')); break;
              default:              echo h((string) $t['type']);
            } ?>
          </span>
        </div>
        <div class="tt-body">
          <?php if ($t['type'] === 'progress'): ?>
            <strong><?= h(number_format((float) $t['value'], 0, ',', '.')) ?>%</strong>
            <?php if ($t['value_forecast'] !== null): ?>
              <span class="muted">(<?= h(t('cockpit.tt_forecast', 'Prognose')) ?>: <?= h(number_format((float) $t['value_forecast'], 0, ',', '.')) ?>%)</span>
            <?php endif; ?>
            <?php if ($t['date_value']): ?><span class="muted"> · <?= h(t('cockpit.tt_as_of', 'Stand')) ?> <?= h((new DateTimeImmutable($t['date_value']))->format('d.m.Y')) ?></span><?php endif; ?>
            <?php if (!empty($t['project_mood'])):  ?><span class="muted"> · <?= h(t('cockpit.tt_proj_mood', 'Projekt-Mood')) ?> <?= (int) $t['project_mood'] ?>/5</span><?php endif; ?>
            <?php if (!empty($t['customer_mood'])): ?><span class="muted"> · <?= h(t('cockpit.tt_cust_mood', 'Kunde')) ?> <?= (int) $t['customer_mood'] ?>/5</span><?php endif; ?>
          <?php elseif ($t['type'] === 'date_shift'): ?>
            <?= h(t('cockpit.tt_shift_to', 'Neues Enddatum')) ?>:
            <strong><?= $t['date_value'] ? h((new DateTimeImmutable($t['date_value']))->format('d.m.Y')) : '—' ?></strong>
          <?php else: ?>
            <?php if ($t['status']): ?><span class="pill pill-<?= h((string) $t['status']) ?>"><?= h((string) $t['status']) ?></span><?php endif; ?>
            <?php if ($t['date_value']): ?><span class="muted"> · <?= h((new DateTimeImmutable($t['date_value']))->format('d.m.Y')) ?></span><?php endif; ?>
          <?php endif; ?>
          <?php if (!empty($t['note'])): ?>
            <div class="muted" style="margin-top:4px"><?= nl2br(h((string) $t['note'])) ?></div>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<script src="<?= h(asset('charts.js')) ?>"></script>
<script nonce="<?= h(csp_nonce()) ?>">
(function () {
  var canvas = document.getElementById('cockpitBurnup');
  if (!canvas || !window.TmCharts || !TmCharts.lines) return;
  var legend = document.getElementById('cockpitBurnupLegend');
  var url = <?= json_encode(route('cockpit_burnup_data', ['id' => $project['id']])) ?>;
  var i18n = <?= json_encode([
      'cum_hours' => t('cockpit.burnup_cum_hours', 'Ist (Stunden)'),
      'target'    => t('cockpit.burnup_target',    'Soll'),
      'none'      => t('cockpit.burnup_none',      'Noch keine Buchungen auf diesem Projekt.'),
  ], JSON_UNESCAPED_UNICODE) ?>;
  fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
    if (!d.ok || !d.points.length) {
      var ctx = canvas.getContext('2d');
      ctx.font = '13px sans-serif';
      ctx.fillStyle = '#888';
      ctx.textAlign = 'center';
      ctx.fillText(i18n.none, canvas.width / 2, canvas.height / 2);
      return;
    }
    var labels = d.points.map(function (p) { return p.d; });
    var series = [{ label: i18n.cum_hours, color: '#2563eb', data: d.points.map(function (p) { return p.h; }) }];
    if (d.target_h) {
      series.push({ label: i18n.target + ' ' + d.target_h + ' h', color: '#94a3b8', dashed: true,
        data: labels.map(function () { return d.target_h; }) });
    }
    TmCharts.lines(canvas, { xLabels: labels, series: series, unit: 'h' }, legend);
  });
})();
</script>

