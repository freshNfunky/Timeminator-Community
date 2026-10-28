<?php
/** @var array $rows @var string $asOf @var bool $all */
$num    = fn(float $n, int $dec = 1) => number_format($n, $dec, ',', '.');
$signed = fn(float $n) => ($n > 0 ? '+' : '') . number_format($n, 1, ',', '.');
?>
<div class="card">
  <div class="card-head">
    <h2><?= h(t('cockpit.employees_h2', 'Mitarbeiter')) ?></h2>
    <span class="muted"><?= h(t('cockpit.as_of', 'Stand')) ?> <?= h((new DateTimeImmutable($asOf))->format('d.m.Y')) ?></span>
  </div>
  <?php if (!$rows): ?>
    <p class="muted"><?= h(t('cockpit.no_employees', 'Keine Mitarbeiter.')) ?></p>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th><?= h(t('cockpit.col_employee',   'Mitarbeiter')) ?></th>
        <th><?= h(t('cockpit.col_employment', 'Beschaeftigung')) ?></th>
        <th class="r"><?= h(t('cockpit.col_week_actual_target', 'Woche (Ist / Soll)')) ?></th>
        <th class="r"><?= h(t('cockpit.col_month_hours',        'Monat Std.')) ?></th>
        <th class="r"><?= h(t('cockpit.col_vacation_remaining', 'Resturlaub (Tage)')) ?></th>
        <th class="r"><?= h(t('cockpit.col_overtime_balance',   'Ueberstunden (Std.)')) ?></th>
        <th class="r"><?= h(t('cockpit.col_sick_days',          'Krank (Tage)')) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
      $u = $r['user']; $s = $r['summary']; $t = $s['terms'];
      $name = $u['display_name'] ?: $u['username'];
    ?>
      <tr>
        <td><strong><?= h($name) ?></strong></td>
        <?php if (!$t): ?>
          <td colspan="6" class="muted">
            <?= h(t('cockpit.no_contract', 'Keine Vertragsdaten hinterlegt')) ?><?php if (Auth::can('admin.users')): ?> &mdash;
              <?= str_replace(
                  '[LINK]',
                  '<a href="' . h(route('admin_users', ['edit' => $u['id']])) . '">' . h(t('cockpit.manage_user_link', 'Verwaltung &rsaquo; Benutzer')) . '</a>',
                  h(t('cockpit.manage_user_hint', 'unter [LINK] pflegen'))
              ) ?><?php endif; ?>.
          </td>
        <?php else: ?>
          <td><?= h(EmployeeRepo::typeLabel($t['employment_type'])) ?></td>
          <td class="r" title="<?= h(t('cockpit.week_booked', 'gebucht')) . ' ' . h($num((float) $s['week_hours'])) . ($s['week_credit'] > 0 ? ' + ' . h(t('cockpit.week_credit', 'gutgeschrieben')) . ' ' . h($num((float) $s['week_credit'])) . ' ' . h(t('cockpit.week_credit_source', '(Feiertag/Urlaub/Krank)')) : '') ?>">
            <?= $num((float) $s['week_hours'] + (float) $s['week_credit']) ?> /
            <?= $t['employment_type'] === EmployeeRepo::TYPE_FREELANCER ? '&ndash;' : $num((float) $t['weekly_hours']) ?>
            <?php if ($s['holiday']): ?><br><span class="muted"><?= h(t('cockpit.today_holiday', 'heute')) ?>: <?= h($s['holiday']) ?></span><?php endif; ?>
          </td>
          <td class="r"><?= $num((float) $s['month_hours']) ?></td>
          <?php if ($s['vacation']): $v = $s['vacation']; ?>
            <td class="r" title="<?= h(t('cockpit.vacation_entitlement', 'Anspruch')) ?> <?= h($num($v['entitlement'])) ?> + <?= h(t('cockpit.vacation_carryover', 'Vorjahr')) ?> <?= h($num($v['carryover'])) ?> - <?= h(t('cockpit.vacation_taken', 'genommen')) ?> <?= h($num($v['taken'])) ?> - <?= h(t('cockpit.vacation_planned', 'geplant')) ?> <?= h($num($v['planned'])) ?>">
              <strong><?= $num($v['remaining']) ?></strong>
              <?php if ($v['planned'] > 0): ?><br><span class="muted"><?= h(t('cockpit.vacation_of_planned', 'davon geplant')) ?> <?= $num($v['planned'], 0) ?></span><?php endif; ?>
            </td>
          <?php else: ?><td class="r muted">&ndash;</td><?php endif; ?>
          <?php if ($s['overtime']): $o = $s['overtime']; ?>
            <td class="r" title="<?= h(t('cockpit.vacation_carryover', 'Vorjahr')) ?> <?= h($signed($o['carryover'])) ?> | <?= h(t('cockpit.overtime_earned', 'aufgelaufen')) ?> <?= h($signed($o['earned'])) ?> | <?= h(t('cockpit.overtime_reduced', 'abgebaut')) ?> <?= h($num($o['reduced'])) ?>">
              <span class="<?= $o['balance'] < 0 ? 'badge badge-warn' : '' ?>"><strong><?= $signed($o['balance']) ?></strong></span>
            </td>
          <?php else: ?><td class="r muted">&ndash;</td><?php endif; ?>
          <td class="r"><?= $num($s['sick_days'], 0) ?></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?php if (!$all): ?><p class="muted"><?= h(t('cockpit.own_only', 'Du siehst nur dein eigenes Konto.')) ?></p><?php endif; ?>
</div>

<div class="card">
  <div class="card-head">
    <h2>
      <?= h(t('cockpit.weeks_h2', 'Verteilung der Arbeitszeit je Woche')) ?>
      <?= tip('cockpit.weeks_hint') ?>
    </h2>
    <div class="row" style="align-items:flex-end;margin:0">
      <?php if ($all): ?>
      <label style="margin:0"><?= h(t('cockpit.weeks_user', 'Mitarbeiter')) ?>
        <select id="empWeeksUser">
          <?php foreach ($rows as $r): ?><option value="<?= (int) $r['user']['id'] ?>"><?= h($r['user']['display_name'] ?: $r['user']['username']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <?php endif; ?>
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

<script src="<?= h(asset('charts.js')) ?>"></script>
<script nonce="<?= h(csp_nonce()) ?>">
(function () {
  var canvas = document.getElementById('chartEmpWeeks'), legend = document.getElementById('legendEmpWeeks');
  var userSel = document.getElementById('empWeeksUser'), rangeSel = document.getElementById('empWeeksRange');
  var i18n = <?= json_encode([
      'target'   => t('cockpit.chart_target',    'Wochensoll'),
      'total'    => t('cockpit.chart_total',     'gesamt'),
      'of'       => t('cockpit.chart_of',        'von'),
      'weekFrom' => t('cockpit.chart_week_from', 'ab'),
  ], JSON_UNESCAPED_UNICODE) ?>;
  var last = null;
  function draw() {
    if (!last) return;
    TmCharts.stackedArea(canvas, {
      xLabels: last.weeks, weekStarts: last.week_starts, target: last.target, unit: 'h',
      targetLabel: i18n.target, totalLabel: i18n.total, ofLabel: i18n.of, weekFromLabel: i18n.weekFrom,
      series: last.series.map(function (s) { return { label: s.label, color: s.color, data: s.values }; })
    }, legend);
  }
  function load() {
    var url = <?= json_encode(route('cockpit_employee_weeks')) ?> + '&weeks=' + encodeURIComponent(rangeSel.value) + (userSel ? '&user_id=' + encodeURIComponent(userSel.value) : '');
    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) { last = d; draw(); });
  }
  if (userSel) userSel.addEventListener('change', load);
  rangeSel.addEventListener('change', load);
  window.addEventListener('resize', draw);
  load();
})();
</script>
