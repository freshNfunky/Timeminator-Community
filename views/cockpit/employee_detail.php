<?php
/**
 * Mitarbeiter-Detail (Pro-Parity, Felix-Feedback 2026-11-07).
 * @var array{id:int,username:string,display_name:?string} $user
 * @var array $summary
 * @var string $asOf
 * @var bool $all
 */
$name = $user['display_name'] ?: $user['username'];
$t    = $summary['terms'] ?? null;
$num  = fn(float $n, int $dec = 1) => number_format($n, $dec, ',', '.');
$sig  = fn(float $n) => ($n > 0 ? '+' : '') . number_format($n, 1, ',', '.');
?>
<h1 class="emp-title"><?= h($name) ?></h1>
<p class="muted"><a href="<?= h(route('cockpit_employees')) ?>">&larr; <?= h(t('cockpit.back_to_employees', 'Alle Mitarbeiter')) ?></a></p>

<div class="card">
  <div class="card-head">
    <h2><?= h($name) ?><?= tip('cockpit.employee_subtitle') ?></h2>
    <span class="muted">
      <?= h(EmployeeRepo::typeLabel($t['employment_type'] ?? EmployeeRepo::TYPE_EMPLOYEE)) ?>
    </span>
  </div>
  <?php if (!$t): ?>
    <p class="muted"><?= h(t('cockpit.no_contract', 'Keine Vertragsdaten hinterlegt')) ?>
      <?php if (Auth::can('admin.users')): ?>
        &mdash; <a href="<?= h(route('admin_users', ['edit' => (int) $user['id']])) ?>"><?= h(t('cockpit.manage_user_link', 'Verwaltung &rsaquo; Benutzer')) ?></a>
      <?php endif; ?>.
    </p>
  <?php else: ?>
    <div class="emp-info-grid">
      <div><div class="info-lbl"><?= h(t('cockpit.emp_type', 'Beschaeftigungsart')) ?></div><div><?= h(EmployeeRepo::typeLabel($t['employment_type'])) ?></div></div>
      <div><div class="info-lbl"><?= h(t('cockpit.emp_weekly_hours', 'Wochenarbeitszeit')) ?></div><div><strong><?= $num((float) $t['weekly_hours']) ?> h</strong><?php if (!empty($summary['daily_target'])): ?> <span class="muted">(<?= h(t('cockpit.emp_daily_target', 'Tagessoll')) ?> <?= $num((float) $summary['daily_target']) ?> h)</span><?php endif; ?></div></div>
      <div><div class="info-lbl"><?= h(t('cockpit.emp_vacation_entitlement', 'Urlaubsanspruch')) ?></div><div><?= $num((float) $t['vacation_days'], 0) ?> <?= h(t('cockpit.emp_days_per_year', 'Tage / Jahr')) ?></div></div>
      <div><div class="info-lbl"><?= h(t('cockpit.emp_holiday_region', 'Feiertage nach')) ?></div><div><?= !empty($t['holiday_region']) ? h((string) $t['holiday_region']) : '<span class="muted">— ' . h(t('cockpit.emp_no_region', 'keine')) . ' —</span>' ?></div></div>
      <div><div class="info-lbl"><?= h(t('cockpit.emp_valid_from', 'gilt ab')) ?></div><div><?= h((new DateTimeImmutable($t['valid_from']))->format('d.m.Y')) ?></div></div>
    </div>
    <?php if (Auth::can('admin.users')): ?>
      <p class="muted" style="margin-top:8px"><a href="<?= h(route('admin_users', ['edit' => (int) $user['id']])) ?>">&rsaquo; <?= h(t('cockpit.emp_edit_terms', 'Vertragsdaten bearbeiten')) ?></a></p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- KPI-Tiles -->
<div class="emp-tiles">
  <?php
  $ot = $summary['overtime'] ?? null;
  // Felix-Feedback 2026-11-07: Breakdown-Tooltip, damit der Saldo
  // nachvollziehbar wird (Jahresuebertrag zeigte 5 h Vorjahr - Tile
  // aber -298,8 h; erschien widersprüchlich, ist aber korrekt:
  // Vorjahr + aufgelaufen - abgebaut = Saldo).
  $otTitle = $ot
    ? sprintf(
        '%s: %s | %s: %s | %s: %s',
        t('cockpit.vacation_carryover', 'Vorjahr'),  $sig((float) $ot['carryover']) . ' h',
        t('cockpit.overtime_earned',    'aufgelaufen'),  $sig((float) $ot['earned'])    . ' h',
        t('cockpit.overtime_reduced',   'abgebaut'),  $num((float) $ot['reduced'])  . ' h'
      )
    : t('cockpit.tile_no_contract', 'Keine Vertragsdaten hinterlegt');
  ?>
  <div class="emp-tile <?= $ot && $ot['balance'] < 0 ? 'emp-tile-warn' : '' ?>" title="<?= h($otTitle) ?>">
    <div class="emp-tile-val"><?= $ot ? $sig((float) $ot['balance']) . ' h' : '&mdash;' ?></div>
    <div class="emp-tile-lbl"><?= h(t('cockpit.tile_overtime', 'Ueberstundenkonto')) ?></div>
    <?php if ($ot): ?>
      <div class="emp-tile-sub">
        <?= h(t('cockpit.vacation_carryover', 'Vorjahr')) ?> <?= $sig((float) $ot['carryover']) ?> h ·
        <?= h(t('cockpit.overtime_net_year', 'Jahres-Saldo')) ?> <?= $sig((float) ($ot['earned'] - $ot['reduced'])) ?> h
      </div>
    <?php endif; ?>
  </div>
  <?php
  $v = $summary['vacation'] ?? null;
  $vTitle = $v
    ? sprintf(
        '%s %s + %s %s - %s %s - %s %s',
        t('cockpit.vacation_entitlement', 'Anspruch'), $num((float) $v['entitlement']),
        t('cockpit.vacation_carryover',   'Vorjahr'),  $num((float) $v['carryover']),
        t('cockpit.vacation_taken',       'genommen'), $num((float) $v['taken']),
        t('cockpit.vacation_planned',     'geplant'),  $num((float) $v['planned'])
      )
    : t('cockpit.tile_no_contract', 'Keine Vertragsdaten hinterlegt');
  ?>
  <div class="emp-tile" title="<?= h($vTitle) ?>">
    <div class="emp-tile-val"><?= $v ? $num((float) $v['remaining'], 1) . ' ' . h(t('cockpit.tile_days', 'Tage')) : '&mdash;' ?></div>
    <div class="emp-tile-lbl"><?= h(t('cockpit.tile_vacation_remaining', 'Resturlaub')) ?></div>
    <?php if ($v): ?>
      <div class="emp-tile-sub">
        <?= h(t('cockpit.vacation_entitlement', 'Anspruch')) ?> <?= $num((float) $v['entitlement'], 0) ?> ·
        <?= h(t('cockpit.vacation_taken', 'genommen')) ?> <?= $num((float) $v['taken'], 1) ?>
      </div>
    <?php endif; ?>
  </div>
  <?php $year = (int) (new DateTimeImmutable($asOf))->format('Y'); ?>
  <div class="emp-tile">
    <div class="emp-tile-val"><?= $num((float) ($summary['sick_days'] ?? 0), 0) ?> <?= h(t('cockpit.tile_days', 'Tage')) ?></div>
    <div class="emp-tile-lbl"><?= h(sprintf(t('cockpit.tile_sick_year', 'Krankheit im Jahr %d'), $year)) ?></div>
  </div>
  <?php
    $weekTotal = (float) ($summary['week_hours'] ?? 0) + (float) ($summary['week_credit'] ?? 0);
    $weekTarget = (float) ($t['weekly_hours'] ?? 0);
    $pct = $weekTarget > 0 ? round($weekTotal / $weekTarget * 100) : null;
    $utilClass = $pct !== null ? ($pct >= 90 ? 'emp-tile-ok' : ($pct >= 50 ? 'emp-tile-warn' : 'emp-tile-low')) : '';
  ?>
  <?php
  $utilTitle = $weekTarget > 0
    ? sprintf(
        '%s: %s | %s: %s',
        t('cockpit.week_booked',  'gebucht'),       $num((float) ($summary['week_hours']  ?? 0), 1) . ' h',
        t('cockpit.week_credit',  'gutgeschrieben') . ' (' . t('cockpit.week_credit_source', 'Feiertag/Urlaub/Krank') . ')',
        $num((float) ($summary['week_credit'] ?? 0), 1) . ' h'
      )
    : t('cockpit.tile_no_contract', 'Keine Vertragsdaten hinterlegt');
  ?>
  <div class="emp-tile <?= $utilClass ?>" title="<?= h($utilTitle) ?>">
    <div class="emp-tile-val"><?= $pct !== null ? $pct . ' %' : '&mdash;' ?></div>
    <div class="emp-tile-lbl">
      <?= h(t('cockpit.tile_utilization', 'Auslastung diese Woche')) ?>
      <?php if ($weekTarget > 0): ?>&middot; <?= $num($weekTotal, 1) ?> / <?= $num($weekTarget, 1) ?> h<?php endif; ?>
    </div>
  </div>
</div>

<!-- Verteilung je Woche (Pro-Parity: "Darstellung"-Dropdown + Zeitraum) -->
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h2>
      <?= h(t('cockpit.weeks_h2', 'Verteilung der Arbeitszeit je Woche')) ?>
      <?= tip('cockpit.weeks_hint') ?>
    </h2>
    <div class="row" style="align-items:flex-end;margin:0;gap:12px">
      <label style="margin:0"><?= h(t('cockpit.chart_mode', 'Darstellung')) ?>
        <select id="empWeeksMode">
          <option value="hours"><?= h(t('cockpit.chart_mode_hours', 'Stunden')) ?></option>
          <option value="percent"><?= h(t('cockpit.chart_mode_percent', 'Prozent (%)')) ?></option>
        </select>
      </label>
      <label style="margin:0"><?= h(t('cockpit.weeks_range', 'Zeitraum')) ?>
        <select id="empWeeksRange">
          <option value="13">13 <?= h(t('cockpit.weeks_unit', 'Wochen')) ?></option>
          <option value="26" selected>26 <?= h(t('cockpit.weeks_unit', 'Wochen')) ?></option>
          <option value="52">52 <?= h(t('cockpit.weeks_unit', 'Wochen')) ?></option>
        </select>
      </label>
    </div>
  </div>
  <div class="chart-box"><canvas id="chartEmpWeeks"></canvas></div>
  <div id="legendEmpWeeks"></div>
</div>

<!-- Donut: Wofuer die Zeit draufging -->
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h2><?= h(t('cockpit.emp_donut_h2', 'Wofuer die Zeit draufging')) ?><?= tip('cockpit.emp_donut_hint') ?></h2>
  </div>
  <div class="chart-box"><canvas id="chartEmpDonut"></canvas></div>
  <div id="legendEmpDonut"></div>
</div>

<script src="<?= h(asset('charts.js')) ?>"></script>
<script nonce="<?= h(csp_nonce()) ?>">
(function () {
  var canvas   = document.getElementById('chartEmpWeeks'),
      legend   = document.getElementById('legendEmpWeeks'),
      modeSel  = document.getElementById('empWeeksMode'),
      rangeSel = document.getElementById('empWeeksRange'),
      donutCanvas = document.getElementById('chartEmpDonut'),
      donutLegend = document.getElementById('legendEmpDonut');
  var userId = <?= (int) $user['id'] ?>;
  var i18n = <?= json_encode([
      'target'   => t('cockpit.chart_target',    'Wochensoll'),
      'total'    => t('cockpit.chart_total',     'gesamt'),
      'of'       => t('cockpit.chart_of',        'von'),
      'weekFrom' => t('cockpit.chart_week_from', 'ab'),
  ], JSON_UNESCAPED_UNICODE) ?>;
  var last = null;
  function draw() {
    if (!last) return;
    // Stacked-Area (Stunden oder Prozent)
    TmCharts.stackedArea(canvas, {
      xLabels: last.weeks, weekStarts: last.week_starts, target: last.target, unit: 'h',
      percent: modeSel && modeSel.value === 'percent',
      targetLabel: i18n.target, totalLabel: i18n.total, ofLabel: i18n.of, weekFromLabel: i18n.weekFrom,
      series: last.series.map(function (s) { return { label: s.label, color: s.color, data: s.values }; })
    }, legend);
    // Donut: Summe pro Serie ueber den gewaehlten Zeitraum
    var labels = last.series.map(function (s) { return s.label; });
    var sums   = last.series.map(function (s) {
      return s.values.reduce(function (a, b) { return a + (Number(b) || 0); }, 0);
    });
    if (window.TmCharts && TmCharts.doughnut) {
      TmCharts.doughnut(donutCanvas, labels, sums, donutLegend);
    }
  }
  function load() {
    var url = <?= json_encode(route('cockpit_employee_weeks')) ?>
      + '&weeks=' + encodeURIComponent(rangeSel.value)
      + '&user_id=' + encodeURIComponent(userId);
    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) { last = d; draw(); });
  }
  if (modeSel)  modeSel.addEventListener('change', draw);
  if (rangeSel) rangeSel.addEventListener('change', load);
  // Resize-Observer: Chart folgt dem Container-Width (Felix-Pattern vom Burn-Up).
  [canvas, donutCanvas].forEach(function (c) {
    var box = c && c.parentNode;
    if (!box || typeof ResizeObserver === 'undefined') return;
    var lastW = box.clientWidth, resizeT = null;
    new ResizeObserver(function () {
      var w = box.clientWidth; if (w === lastW) return; lastW = w;
      if (resizeT) clearTimeout(resizeT); resizeT = setTimeout(draw, 80);
    }).observe(box);
  });
  load();
})();
</script>
