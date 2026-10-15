<?php
/**
 * Projekt-Cockpit view (#124 → merged into v0.9.1).
 *
 * Renders a burn-up line chart (cumulative Ist over time) with the Soll
 * as a dashed horizontal line, status KPIs, trend, and the last 10
 * entries. Inline SVG — no JS chart library, geometry computed in PHP.
 *
 * @var array $project
 * @var array $burnup
 * @var array $recent
 * @var int   $total_min
 * @var int   $target_min
 * @var int   $percent
 * @var string $status
 * @var float $trend_per_week
 */
$__hasTarget = $target_min > 0;
$__hasData   = !empty($burnup);

$__chartW = 720;
$__chartH = 220;
$__padL   = 44;
$__padR   = 12;
$__padT   = 10;
$__padB   = 26;
$__innerW = $__chartW - $__padL - $__padR;
$__innerH = $__chartH - $__padT - $__padB;

// Y domain is max of (last cumulative, target). Round up to a nice hour mark.
$__maxMin = max($__hasData ? end($burnup)['cumulative_minutes'] : 0, $__target_min = $target_min);
if ($__maxMin <= 0) { $__maxMin = 60; }
$__maxH   = ceil($__maxMin / 60);
// Pretty round-up to nearest 5h for small projects, 10h else.
$__step   = $__maxH <= 20 ? 5 : ($__maxH <= 100 ? 10 : 50);
$__maxH   = (int) (ceil($__maxH / $__step) * $__step);
$__maxMin = $__maxH * 60;

$__points = [];
$__n = count($burnup);
if ($__hasData && $__n > 1) {
    foreach ($burnup as $__i => $__p) {
        $__x = $__padL + ($__i / max(1, $__n - 1)) * $__innerW;
        $__y = $__padT + $__innerH - (($__p['cumulative_minutes'] / $__maxMin) * $__innerH);
        $__points[] = sprintf('%.1f,%.1f', $__x, $__y);
    }
}
$__sollY = $__hasTarget
    ? $__padT + $__innerH - (($target_min / $__maxMin) * $__innerH)
    : null;

$__statusLabel = $status === 'over' ? t('report.status_over', 'ueber Plan')
                : ($status === 'warn' ? t('report.status_warn', 'nahe am Limit')
                : t('report.status_ok', 'im Plan'));

// Y-axis ticks: 0 / step h / 2*step / … / maxH
$__ticks = [];
for ($__h = 0; $__h <= $__maxH; $__h += $__step) {
    $__y = $__padT + $__innerH - (($__h * 60 / $__maxMin) * $__innerH);
    $__ticks[] = ['y' => round($__y, 1), 'label' => $__h . 'h'];
}
// X-axis labels: first + mid + last date
$__xLabels = [];
if ($__hasData) {
    $__fmt = static fn(string $d): string => (new DateTimeImmutable($d))->format('d.m.y');
    $__xLabels[] = ['x' => $__padL, 'label' => $__fmt($burnup[0]['d']), 'anchor' => 'start'];
    if ($__n > 2) {
        $__mid = (int) floor($__n / 2);
        $__xLabels[] = [
            'x' => $__padL + ($__mid / max(1, $__n - 1)) * $__innerW,
            'label' => $__fmt($burnup[$__mid]['d']),
            'anchor' => 'middle',
        ];
    }
    $__xLabels[] = [
        'x' => $__padL + $__innerW,
        'label' => $__fmt(end($burnup)['d']),
        'anchor' => 'end',
    ];
}
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= h($project['client_name']) ?> / <?= h($project['name']) ?></h2>
      <p class="muted"><?= h(sprintf(t('projects.cockpit_subtitle', 'Projekt-Code: %s'), $project['code'])) ?></p>
    </div>
    <div class="cockpit-actions">
      <a class="btn btn-sm" href="<?= h(route('projects', ['edit' => (int) $project['id']])) ?>"><?= h(t('common.edit_short', 'bearb.')) ?></a>
      <a class="btn btn-sm btn-ghost" href="<?= h(route('projects')) ?>">← <?= h(t('projects.back_to_list', 'Zurueck zur Liste')) ?></a>
    </div>
  </div>

  <div class="cockpit-kpis">
    <div class="cockpit-kpi">
      <div class="cockpit-kpi-val"><?= h($__hasTarget ? number_format($project['target_hours'], 2, ',', '.') : '—') ?></div>
      <div class="cockpit-kpi-lbl"><?= h(t('planning.col_target', 'Soll (h)')) ?></div>
    </div>
    <div class="cockpit-kpi">
      <div class="cockpit-kpi-val"><?= h(fmt_hours($total_min)) ?></div>
      <div class="cockpit-kpi-lbl"><?= h(t('planning.col_actual', 'Ist (h)')) ?></div>
    </div>
    <?php if ($__hasTarget): ?>
      <div class="cockpit-kpi">
        <?php $__rest = $target_min - $total_min; ?>
        <div class="cockpit-kpi-val"><?= h(($__rest >= 0 ? '' : '+') . number_format(abs($__rest) / 60, 2, ',', '.')) ?></div>
        <div class="cockpit-kpi-lbl"><?= h($__rest >= 0 ? t('cockpit.rest', 'Rest (h)') : t('cockpit.over', 'ueber (h)')) ?></div>
      </div>
      <div class="cockpit-kpi">
        <div class="cockpit-kpi-val"><?= (int) $percent ?>%</div>
        <div class="cockpit-kpi-lbl"><?= h(t('report.col_percent', '%')) ?></div>
      </div>
    <?php endif; ?>
    <div class="cockpit-kpi">
      <div class="cockpit-kpi-val"><?= h(number_format($trend_per_week, 1, ',', '.')) ?></div>
      <div class="cockpit-kpi-lbl"><?= h(t('cockpit.trend_per_week', 'ø h / Woche (4W)')) ?></div>
    </div>
    <?php if ($__hasTarget): ?>
      <div class="cockpit-kpi">
        <span class="report-status-badge report-badge-<?= h($status) ?>"><?= h($__statusLabel) ?></span>
      </div>
    <?php endif; ?>
  </div>

  <h3 class="cockpit-chart-head"><?= h(t('cockpit.burnup_h3', 'Burn-up: kumulative Ist-Stunden')) ?></h3>
  <?php if (!$__hasData): ?>
    <p class="muted"><?= h(t('cockpit.chart_empty', 'Noch keine Zeiteintraege auf diesem Projekt — der Burn-up erscheint, sobald eine Buchung existiert.')) ?></p>
  <?php else: ?>
    <div class="cockpit-chart-ctl">
      <fieldset class="cockpit-toggles">
        <legend class="sr-only"><?= h(t('cockpit.toggles_legend', 'Linien ein-/ausblenden')) ?></legend>
        <label class="cockpit-toggle">
          <input type="checkbox" class="cockpit-toggle-line cockpit-toggle-ist" checked>
          <span class="cockpit-toggle-swatch cockpit-legend-ist cockpit-legend-ist-<?= h($status) ?>"></span>
          <?= h(t('report.legend_actual', 'Ist')) ?>
        </label>
        <?php if ($__hasTarget): ?>
          <label class="cockpit-toggle">
            <input type="checkbox" class="cockpit-toggle-line cockpit-toggle-soll" checked>
            <span class="cockpit-toggle-swatch cockpit-legend-soll"></span>
            <?= h(t('report.legend_target', 'Soll')) ?>
          </label>
        <?php endif; ?>
        <label class="cockpit-toggle is-pro-locked" title="<?= h(t('nav.pro_feature_tooltip', 'Pro-Feature')) ?>">
          <input type="checkbox" class="cockpit-toggle-line" disabled>
          <span class="cockpit-toggle-swatch cockpit-legend-budget"></span>
          <?= h(t('cockpit.toggle_budget', 'Budget (€)')) ?>
          <span class="pill pill-pro"><?= h(t('nav.pro_pill', 'Pro')) ?></span>
        </label>
        <label class="cockpit-toggle is-pro-locked" title="<?= h(t('nav.pro_feature_tooltip', 'Pro-Feature')) ?>">
          <input type="checkbox" class="cockpit-toggle-line" disabled>
          <span class="cockpit-toggle-swatch cockpit-legend-ideal"></span>
          <?= h(t('cockpit.toggle_ideal', 'Ideal-Verlauf')) ?>
          <span class="pill pill-pro"><?= h(t('nav.pro_pill', 'Pro')) ?></span>
        </label>
      </fieldset>
    <div class="cockpit-chart-wrap">
      <svg viewBox="0 0 <?= $__chartW ?> <?= $__chartH ?>" width="100%" height="<?= $__chartH ?>"
           preserveAspectRatio="none" role="img"
           aria-label="<?= h(t('cockpit.burnup_aria', 'Burn-up-Diagramm kumulative Ist-Stunden')) ?>"
           xmlns="http://www.w3.org/2000/svg">
        <!-- Y gridlines + labels -->
        <?php foreach ($__ticks as $__t): ?>
          <line class="cockpit-grid" x1="<?= $__padL ?>" x2="<?= $__chartW - $__padR ?>" y1="<?= $__t['y'] ?>" y2="<?= $__t['y'] ?>"/>
          <text class="cockpit-tick" x="<?= $__padL - 6 ?>" y="<?= $__t['y'] + 3 ?>" text-anchor="end"><?= h($__t['label']) ?></text>
        <?php endforeach; ?>
        <!-- X labels -->
        <?php foreach ($__xLabels as $__xl): ?>
          <text class="cockpit-tick" x="<?= round($__xl['x'], 1) ?>" y="<?= $__chartH - 8 ?>" text-anchor="<?= h($__xl['anchor']) ?>"><?= h($__xl['label']) ?></text>
        <?php endforeach; ?>
        <!-- Soll horizontal line -->
        <?php if ($__sollY !== null): ?>
          <line class="cockpit-soll" x1="<?= $__padL ?>" x2="<?= $__chartW - $__padR ?>" y1="<?= round($__sollY, 1) ?>" y2="<?= round($__sollY, 1) ?>"/>
          <text class="cockpit-soll-label" x="<?= $__chartW - $__padR - 4 ?>" y="<?= round($__sollY, 1) - 4 ?>" text-anchor="end"><?= h(sprintf(t('cockpit.target_line', 'Soll %s h'), number_format($project['target_hours'], 2, ',', '.'))) ?></text>
        <?php endif; ?>
        <!-- Burn-up line -->
        <?php if (count($__points) > 1): ?>
          <polyline class="cockpit-line cockpit-line-<?= h($status) ?>" fill="none" stroke-width="2" points="<?= implode(' ', $__points) ?>"/>
        <?php endif; ?>
      </svg>
    </div>
    </div><!-- /.cockpit-chart-ctl -->
  <?php endif; ?>

  <h3 class="cockpit-health-head"><?= h(t('cockpit.health_h3', 'Projekt-Health-Checks')) ?>
    <span class="pill pill-pro"><?= h(t('nav.pro_pill', 'Pro')) ?></span>
  </h3>
  <p class="muted"><?= h(t('cockpit.health_intro', 'Automatische Projekt-Gesundheits-Signale fuer Projektleiter: zeigen wo nachgefasst werden muss. Alle folgenden Checks sind in Timeminator Pro verfuegbar.')) ?></p>
  <ul class="cockpit-health-list">
    <?php
      $__healthChecks = [
          ['icon' => '🧾', 'key' => 'cockpit.health.invoices',  'default' => 'Offene Rechnungen — faellig / ueberfaellig'],
          ['icon' => '📜', 'key' => 'cockpit.health.offers',    'default' => 'Angebot vs Aufwand — Soll aus Angebot abgeglichen'],
          ['icon' => '📅', 'key' => 'cockpit.health.schedule',  'default' => 'Terminverschiebungs-Log mit Begruendung'],
          ['icon' => '🧮', 'key' => 'cockpit.health.budget',    'default' => 'Budget-Burn in €, kumuliert + prognostiziert'],
          ['icon' => '👥', 'key' => 'cockpit.health.resource',  'default' => 'Ressourcen-Konflikt-Erkennung (ueberlastete Mitarbeiter)'],
          ['icon' => '📊', 'key' => 'cockpit.health.portfolio', 'default' => 'Portfolio-Heatmap ueber alle Projekte'],
          ['icon' => '📄', 'key' => 'cockpit.health.pdf',       'default' => 'PDF-Export des Cockpits mit Firmenlogo'],
      ];
    ?>
    <?php foreach ($__healthChecks as $__hc): ?>
      <li class="cockpit-health-item is-pro-locked">
        <span class="cockpit-health-icon" aria-hidden="true"><?= $__hc['icon'] ?></span>
        <span class="cockpit-health-label"><?= h(t($__hc['key'], $__hc['default'])) ?></span>
        <a class="cockpit-health-cta" href="<?= h(route('pro_budget')) ?>"><?= h(t('cockpit.health_cta', 'in Pro freischalten')) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>

  <h3 class="cockpit-recent-head"><?= h(t('cockpit.recent_h3', 'Letzte 10 Buchungen')) ?></h3>
  <?php if (!$recent): ?>
    <p class="muted"><?= h(t('cockpit.recent_empty', 'Noch keine Buchungen.')) ?></p>
  <?php else: ?>
    <table class="table">
      <thead><tr>
        <th><?= h(t('entries.start', 'Start')) ?></th>
        <th><?= h(t('users.col_user', 'Benutzer')) ?></th>
        <th><?= h(t('dash.col_task', 'Aufgabe')) ?></th>
        <th class="r"><?= h(t('entries.col_duration', 'Dauer')) ?></th>
        <th><?= h(t('entries.note', 'Notiz')) ?></th>
      </tr></thead>
      <tbody>
        <?php foreach ($recent as $__e): ?>
          <tr>
            <td class="nowrap"><?= h(fmt_dt($__e['start_ts'])) ?></td>
            <td><?= h($__e['user_username'] ?? '—') ?></td>
            <td><?= h($__e['task_name'] ?? '—') ?></td>
            <td class="r nowrap"><?= h(fmt_hm((int) ($__e['duration_min'] ?? 0))) ?></td>
            <td class="muted"><?= h((string) ($__e['note'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <p class="muted cockpit-pro-teaser">
    <?= h(t('cockpit.pro_teaser.intro', 'Budget in €, Terminverschiebungs-Log, Projekt-Health-Checks (Rechnungen, Angebote), Ressourcen-Konflikt-Erkennung, PDF-Export und Portfolio-Heatmap sind Teil von')) ?>
    <a href="<?= h(route('pro_budget')) ?>"><strong><?= h(t('planning.pro_teaser.link', 'Timeminator Pro')) ?></strong></a>.
  </p>
</div>
