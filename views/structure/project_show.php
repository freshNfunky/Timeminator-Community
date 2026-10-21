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
 * @var array $trackers
 * @var ?string $planned_end     ISO date (original project deadline) or null
 * @var ?string $estimated_end   ISO date (latest tracker-derived estimate) or null
 */
$trackers      = $trackers ?? [];
$planned_end   = $planned_end   ?? null;
$estimated_end = $estimated_end ?? null;
$__hasTarget = $target_min > 0;
$__hasData   = !empty($burnup);

$__chartW = 720;
$__chartH = 220;
$__padL   = 44;
$__padR   = 40;  // room for right-axis % labels when a progress overlay is active
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
$__baseline = $__padT + $__innerH;
$__sollY = $__hasTarget
    ? $__padT + $__innerH - (($target_min / $__maxMin) * $__innerH)
    : null;

// X mapping is time-based so the chart can extend past "today" to show
// future deadline / estimated-end vertical lines.
$__ts0 = $__hasData ? strtotime($burnup[0]['d']) : time();
$__tsEndIst = $__hasData ? strtotime(end($burnup)['d']) : time();
$__tsEnd = $__tsEndIst;
foreach ([$planned_end, $estimated_end] as $__d) {
    if ($__d !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $__d)) {
        $__ts = strtotime($__d);
        if ($__ts !== false && $__ts > $__tsEnd) { $__tsEnd = $__ts; }
    }
}
$__tsSpan = max(1, $__tsEnd - $__ts0);
$__xForTs = static function (int $ts) use ($__padL, $__innerW, $__ts0, $__tsSpan) {
    $f = ($ts - $__ts0) / $__tsSpan;
    $f = max(0.0, min(1.0, $f));
    return $__padL + $f * $__innerW;
};

// Precompute x/y per point so the area-fill pass can reuse them.
$__xy = [];
if ($__hasData && $__n > 1) {
    foreach ($burnup as $__p) {
        $__ts = strtotime($__p['d']);
        $__x = $__xForTs($__ts);
        $__y = $__padT + $__innerH - (($__p['cumulative_minutes'] / $__maxMin) * $__innerH);
        $__xy[] = [$__x, $__y];
        $__points[] = sprintf('%.1f,%.1f', $__x, $__y);
    }
}

// X positions for the vertical "deadline" lines.
$__plannedX   = $planned_end   ? $__xForTs(strtotime($planned_end))   : null;
$__estimatedX = $estimated_end ? $__xForTs(strtotime($estimated_end)) : null;
$__linesCoincide = $__plannedX !== null && $__estimatedX !== null && abs($__plannedX - $__estimatedX) < 2;

// Progress-tracker series overlay (second Y axis, right-hand %-scale).
// Each entry: {x, y_now, y_forecast?, x_forecast?} where "forecast" lives
// one month after the entry's `created_at`.
$__progressPts = [];
$__forecastPts = [];
foreach ($trackers as $__t) {
    if ($__t['type'] !== 'progress' || $__t['value'] === null) { continue; }
    $__ts = strtotime((string) $__t['created_at']);
    if ($__ts === false) { continue; }
    $__xp = $__xForTs($__ts);
    $__yp = $__padT + $__innerH - (((float) $__t['value']) / 100.0) * $__innerH;
    $__progressPts[] = ['x' => $__xp, 'y' => $__yp, 'ts' => $__ts, 'value' => (float) $__t['value']];
    if ($__t['value_forecast'] !== null) {
        $__tsF = strtotime('+1 month', $__ts);
        $__xf = $__xForTs($__tsF);
        $__yf = $__padT + $__innerH - (((float) $__t['value_forecast']) / 100.0) * $__innerH;
        $__forecastPts[] = ['x' => $__xp, 'y' => $__yp, 'xf' => $__xf, 'yf' => $__yf, 'value' => (float) $__t['value_forecast']];
    }
}
// Sort by timestamp for a sensible polyline (trackers come DESC).
usort($__progressPts, static fn($a, $b) => $a['ts'] <=> $b['ts']);
$__progressPoly = '';
foreach ($__progressPts as $__pp) {
    $__progressPoly .= sprintf('%.1f,%.1f ', $__pp['x'], $__pp['y']);
}
$__hasProgress = $__progressPoly !== '';
// Right-axis ticks (0, 25, 50, 75, 100 %)
$__pctTicks = [];
if ($__hasProgress) {
    foreach ([0, 25, 50, 75, 100] as $__pct) {
        $__pctTicks[] = [
            'y'     => round($__padT + $__innerH - ($__pct / 100.0) * $__innerH, 1),
            'label' => $__pct . '%',
        ];
    }
}

// Area fills (only when a target is set — otherwise there's no "above/below"
// line to clip against). Green = everything up to min(Ist, Soll), i.e. the
// in-plan integral under the Ist curve. Red = Ist-above-Soll overshoot.
// Linear interpolation at crossing points keeps the shapes clean.
$__greenPoly = '';
$__redPolys  = [];
if ($__hasTarget && $__n > 1) {
    // Green: polygon from baseline up to max(Ist_y, Soll_y) at each sample,
    // i.e. the Ist curve clipped against the Soll line from above (in SVG
    // coordinates "larger y" is further down → "max" gives the lower of
    // the two visually).
    $__greenPts = [sprintf('%.1f,%.1f', $__xy[0][0], $__baseline)];
    foreach ($__xy as [$__x, $__y]) {
        $__greenPts[] = sprintf('%.1f,%.1f', $__x, max($__y, $__sollY));
    }
    $__greenPts[] = sprintf('%.1f,%.1f', end($__xy)[0], $__baseline);
    $__greenPoly = implode(' ', $__greenPts);

    // Red: for every segment that crosses or lives above Soll, build a
    // closed polygon between Ist and Soll. Interpolate the exact crossing
    // x so the two colors meet precisely on the Soll line.
    $__segStart = null; // index at which the current "above Soll" run began
    $__flush = static function () use (&$__segStart, &$__xy, $__sollY, &$__redPolys) {
        if ($__segStart === null) { return; }
        $__segEnd = count($__xy) - 1;
        self_push_red_run($__segStart, $__segEnd, $__xy, $__sollY, $__redPolys);
        $__segStart = null;
    };
    // Define the helper locally; declaring a closure is cleaner than a
    // function_exists trick, but we need something recursive-safe.
    if (!function_exists('self_push_red_run')) {
        /** @internal */
        function self_push_red_run(int $from, int $to, array $xy, float $sollY, array &$polys): void
        {
            // Determine entry/exit x via linear interpolation at the
            // crossings before/after this run.
            $pts = [];
            // Entry crossing (if $from > 0)
            if ($from > 0) {
                [$x0, $y0] = $xy[$from - 1];
                [$x1, $y1] = $xy[$from];
                $pts[] = self_interp_cross($x0, $y0, $x1, $y1, $sollY);
            } else {
                $pts[] = [$xy[$from][0], $sollY];
            }
            // All Ist points in the run (above Soll)
            for ($i = $from; $i <= $to; $i++) {
                $pts[] = $xy[$i];
            }
            // Exit crossing (if $to < last)
            if ($to < count($xy) - 1) {
                [$x0, $y0] = $xy[$to];
                [$x1, $y1] = $xy[$to + 1];
                $pts[] = self_interp_cross($x0, $y0, $x1, $y1, $sollY);
            } else {
                $pts[] = [$xy[$to][0], $sollY];
            }
            // Close back along the Soll line.
            $pts[] = [$pts[0][0], $sollY];
            $polys[] = implode(' ', array_map(
                static fn(array $p) => sprintf('%.1f,%.1f', $p[0], $p[1]),
                $pts
            ));
        }
        /** @internal x where the segment (x0,y0)-(x1,y1) crosses horizontal y=yTarget */
        function self_interp_cross(float $x0, float $y0, float $x1, float $y1, float $yTarget): array
        {
            if ($y1 === $y0) { return [$x1, $yTarget]; }
            $t = ($yTarget - $y0) / ($y1 - $y0);
            $t = max(0.0, min(1.0, $t));
            return [$x0 + $t * ($x1 - $x0), $yTarget];
        }
    }
    foreach ($__xy as $__i => [$__x, $__y]) {
        $__above = $__y < $__sollY; // SVG y: smaller = higher = above Soll
        if ($__above && $__segStart === null) {
            $__segStart = $__i;
        } elseif (!$__above && $__segStart !== null) {
            self_push_red_run($__segStart, $__i - 1, $__xy, $__sollY, $__redPolys);
            $__segStart = null;
        }
    }
    if ($__segStart !== null) {
        self_push_red_run($__segStart, count($__xy) - 1, $__xy, $__sollY, $__redPolys);
    }
}

$__statusLabel = $status === 'over' ? t('cockpit.status_over', 'ueber Plan')
                : ($status === 'warn' ? t('cockpit.status_warn', 'nahe am Limit')
                : t('cockpit.status_ok', 'im Plan'));

// Y-axis ticks: 0 / step h / 2*step / … / maxH
$__ticks = [];
for ($__h = 0; $__h <= $__maxH; $__h += $__step) {
    $__y = $__padT + $__innerH - (($__h * 60 / $__maxMin) * $__innerH);
    $__ticks[] = ['y' => round($__y, 1), 'label' => $__h . 'h'];
}
// X-axis labels anchored on absolute dates (start, today, chart-end).
$__xLabels = [];
if ($__hasData) {
    $__fmt = static fn(int $ts): string => date('d.m.y', $ts);
    $__xLabels[] = ['x' => $__padL, 'label' => $__fmt($__ts0), 'anchor' => 'start'];
    $__tsToday = strtotime('today');
    if ($__tsToday > $__ts0 && $__tsToday < $__tsEnd) {
        $__xLabels[] = [
            'x' => $__xForTs($__tsToday),
            'label' => $__fmt($__tsToday),
            'anchor' => 'middle',
        ];
    }
    $__xLabels[] = [
        'x' => $__padL + $__innerW,
        'label' => $__fmt($__tsEnd),
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
    <div class="cockpit-kpi<?= $__hasTarget ? '' : ' is-missing' ?>">
      <div class="cockpit-kpi-val"><?= h($__hasTarget ? number_format($project['target_hours'], 2, ',', '.') : '—') ?></div>
      <div class="cockpit-kpi-lbl"><?= h(t('planning.col_target', 'Soll (h)')) ?></div>
      <?php if (!$__hasTarget): ?>
        <a class="cockpit-kpi-cta" href="<?= h(route('projects', ['edit' => (int) $project['id']])) ?>#row-<?= (int) $project['id'] ?>"><?= h(t('cockpit.set_target', 'Soll setzen')) ?></a>
      <?php endif; ?>
    </div>
    <?php $__hasEnd = $planned_end !== null && $planned_end !== ''; ?>
    <div class="cockpit-kpi<?= $__hasEnd ? '' : ' is-missing' ?>">
      <div class="cockpit-kpi-val"><?= $__hasEnd ? h((new DateTimeImmutable($planned_end))->format('d.m.Y')) : '—' ?></div>
      <div class="cockpit-kpi-lbl"><?= h(t('planning.col_deadline', 'Deadline')) ?></div>
      <?php if (!$__hasEnd): ?>
        <a class="cockpit-kpi-cta" href="<?= h(route('projects', ['edit' => (int) $project['id']])) ?>#row-<?= (int) $project['id'] ?>"><?= h(t('cockpit.set_deadline', 'Deadline setzen')) ?></a>
      <?php elseif ($estimated_end !== null && $estimated_end !== $planned_end): ?>
        <small class="muted">
          <?= h(sprintf(t('cockpit.deadline_shifted', 'verzoegert auf %s'), (new DateTimeImmutable($estimated_end))->format('d.m.Y'))) ?>
        </small>
      <?php endif; ?>
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
        <div class="cockpit-kpi-lbl"><?= h(t('cockpit.col_percent', '%')) ?></div>
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
          <?= h(t('cockpit.legend_actual', 'Ist')) ?>
        </label>
        <?php if ($__hasTarget): ?>
          <label class="cockpit-toggle">
            <input type="checkbox" class="cockpit-toggle-line cockpit-toggle-areas" checked>
            <span class="cockpit-toggle-swatch cockpit-legend-area"></span>
            <?= h(t('cockpit.legend_areas', 'Plan/Over-Flaechen')) ?>
          </label>
        <?php endif; ?>
        <?php if ($__hasProgress): ?>
          <label class="cockpit-toggle">
            <input type="checkbox" class="cockpit-toggle-line cockpit-toggle-progress" checked>
            <span class="cockpit-toggle-swatch cockpit-legend-progress"></span>
            <?= h(t('cockpit.legend_progress', 'Fortschritt (%)')) ?>
          </label>
        <?php endif; ?>
        <?php if ($__hasTarget): ?>
          <label class="cockpit-toggle">
            <input type="checkbox" class="cockpit-toggle-line cockpit-toggle-soll" checked>
            <span class="cockpit-toggle-swatch cockpit-legend-soll"></span>
            <?= h(t('cockpit.legend_target', 'Soll')) ?>
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
        <!-- Area fills (green = in plan integral, red = over-plan integral) -->
        <?php if ($__greenPoly !== ''): ?>
          <polygon class="cockpit-area cockpit-area-ok" points="<?= $__greenPoly ?>"/>
        <?php endif; ?>
        <?php foreach ($__redPolys as $__rp): ?>
          <polygon class="cockpit-area cockpit-area-over" points="<?= $__rp ?>"/>
        <?php endforeach; ?>
        <!-- Soll horizontal line (dashed, over the fills) -->
        <?php if ($__sollY !== null): ?>
          <line class="cockpit-soll" x1="<?= $__padL ?>" x2="<?= $__chartW - $__padR ?>" y1="<?= round($__sollY, 1) ?>" y2="<?= round($__sollY, 1) ?>"/>
          <text class="cockpit-soll-label" x="<?= $__chartW - $__padR - 4 ?>" y="<?= round($__sollY, 1) - 4 ?>" text-anchor="end"><?= h(sprintf(t('cockpit.target_line', 'Soll %s h'), number_format($project['target_hours'], 2, ',', '.'))) ?></text>
        <?php endif; ?>
        <!-- Vertical deadline markers (dashed, labels right-aligned
             above the 0-line so they stay out of the way of the Soll label) -->
        <?php
          // Stack the two bottom labels: planned slightly higher so they
          // don't overlap when deadline + estimated are close together.
          $__labelPlannedY   = $__baseline - 14;
          $__labelEstimatedY = $__baseline - 2;
        ?>
        <?php if ($__plannedX !== null): ?>
          <line class="cockpit-deadline cockpit-deadline-planned"
                x1="<?= round($__plannedX, 1) ?>" x2="<?= round($__plannedX, 1) ?>"
                y1="<?= $__padT ?>" y2="<?= $__baseline ?>"/>
          <text class="cockpit-deadline-label cockpit-deadline-label-planned"
                x="<?= round($__plannedX, 1) - 4 ?>" y="<?= $__labelPlannedY ?>"
                text-anchor="end"><?= h(sprintf(t('cockpit.deadline_label', 'Deadline %s'), date('d.m.y', strtotime($planned_end)))) ?></text>
        <?php endif; ?>
        <?php if ($__estimatedX !== null && !$__linesCoincide): ?>
          <line class="cockpit-deadline cockpit-deadline-estimated"
                x1="<?= round($__estimatedX, 1) ?>" x2="<?= round($__estimatedX, 1) ?>"
                y1="<?= $__padT ?>" y2="<?= $__baseline ?>"/>
          <text class="cockpit-deadline-label cockpit-deadline-label-estimated"
                x="<?= round($__estimatedX, 1) - 4 ?>" y="<?= $__labelEstimatedY ?>"
                text-anchor="end"><?= h(sprintf(t('cockpit.estimated_label', 'Verzoegerung %s'), date('d.m.y', strtotime($estimated_end)))) ?></text>
        <?php endif; ?>
        <!-- Burn-up line (on top of area fills + deadlines) -->
        <?php if (count($__points) > 1): ?>
          <polyline class="cockpit-line cockpit-line-<?= h($status) ?>" fill="none" stroke-width="2" points="<?= implode(' ', $__points) ?>"/>
        <?php endif; ?>

        <!-- Progress overlay (second series, right-hand %-scale) -->
        <?php if ($__hasProgress): ?>
          <!-- Right axis ticks + labels -->
          <?php foreach ($__pctTicks as $__pt): ?>
            <text class="cockpit-tick cockpit-tick-pct"
                  x="<?= $__chartW - $__padR + 2 ?>" y="<?= $__pt['y'] + 3 ?>" text-anchor="start"><?= h($__pt['label']) ?></text>
          <?php endforeach; ?>
          <!-- Progress line (through recorded "now" values) -->
          <?php if (count($__progressPts) > 1): ?>
            <polyline class="cockpit-progress-line" fill="none" stroke-width="2" points="<?= trim($__progressPoly) ?>"/>
          <?php endif; ?>
          <!-- Progress "now" markers -->
          <?php foreach ($__progressPts as $__pp): ?>
            <circle class="cockpit-progress-dot" cx="<?= round($__pp['x'], 1) ?>" cy="<?= round($__pp['y'], 1) ?>" r="3">
              <title><?= h(sprintf(t('cockpit.progress_tooltip', 'Fortschritt %d%% am %s'), (int) round($__pp['value']), date('d.m.y', $__pp['ts']))) ?></title>
            </circle>
          <?php endforeach; ?>
          <!-- Forecast markers (open circles connected with dashed line to the recorded "now") -->
          <?php foreach ($__forecastPts as $__fp): ?>
            <line class="cockpit-forecast-link"
                  x1="<?= round($__fp['x'], 1) ?>" y1="<?= round($__fp['y'], 1) ?>"
                  x2="<?= round($__fp['xf'], 1) ?>" y2="<?= round($__fp['yf'], 1) ?>"/>
            <circle class="cockpit-forecast-dot" cx="<?= round($__fp['xf'], 1) ?>" cy="<?= round($__fp['yf'], 1) ?>" r="3">
              <title><?= h(sprintf(t('cockpit.forecast_tooltip', 'Prognose %d%% in 1 Monat'), (int) round($__fp['value']))) ?></title>
            </circle>
          <?php endforeach; ?>
        <?php endif; ?>
      </svg>
    </div>
    </div><!-- /.cockpit-chart-ctl -->
  <?php endif; ?>

  <h3 class="cockpit-tracker-head"><?= h(t('tracker.h3', 'Projekt-Tracker')) ?><?= tip('tracker.intro') ?></h3>
  <form method="post" action="<?= h(route('project_tracker_save')) ?>" class="tracker-form" data-tracker-type="status_report" data-tracker-status="on_plan">
    <?= csrf_field() ?>
    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
    <label class="tracker-type-label"><?= h(t('tracker.type', 'Typ')) ?>
      <select name="type">
        <optgroup label="<?= h(t('tracker.group.community', 'Community')) ?>">
          <option value="status_report"><?= h(t('tracker.type.status_report', 'Status-Report')) ?></option>
          <option value="progress"><?= h(t('tracker.type.progress', 'Projektfortschritt (%)')) ?></option>
        </optgroup>
        <optgroup label="<?= h(t('tracker.group.pro', 'Pro (gesperrt)')) ?>">
          <option value="status_report" disabled><?= h(t('tracker.type.invoice_part', 'Teilrechnung')) ?> — <?= h(t('nav.pro_pill', 'Pro')) ?></option>
          <option value="status_report" disabled><?= h(t('tracker.type.payment', 'Zahlungseingang')) ?> — <?= h(t('nav.pro_pill', 'Pro')) ?></option>
          <option value="status_report" disabled><?= h(t('tracker.type.risk', 'Risiko mit Rating')) ?> — <?= h(t('nav.pro_pill', 'Pro')) ?></option>
          <option value="status_report" disabled><?= h(t('tracker.type.decision', 'Entscheidung mit Approval-Chain')) ?> — <?= h(t('nav.pro_pill', 'Pro')) ?></option>
          <option value="status_report" disabled><?= h(t('tracker.type.meeting', 'Kundenmeeting-Protokoll')) ?> — <?= h(t('nav.pro_pill', 'Pro')) ?></option>
        </optgroup>
      </select>
    </label>

    <!-- status_report: Status-Combobox + optionales Datum -->
    <div class="tracker-field tracker-field-status">
      <div class="tracker-status-row">
        <label>
          <?= h(t('tracker.status', 'Status')) ?>
          <select name="status">
            <option value="on_plan"><?= h(t('tracker.status.on_plan', 'Im Plan')) ?></option>
            <option value="delay"><?= h(t('tracker.status.delay', 'Verzoegerung')) ?></option>
            <option value="extension"><?= h(t('tracker.status.extension', 'Projektverlaengerung')) ?></option>
          </select>
        </label>
        <label class="tracker-field-date">
          <?= h(t('tracker.new_deadline', 'Neue Deadline')) ?>
          <input type="date" name="date_value">
        </label>
      </div>
    </div>

    <!-- progress: zwei Werte (jetzt + Prognose 1M) -->
    <div class="tracker-field tracker-field-progress">
      <div class="tracker-progress-row">
        <label>
          <?= h(t('tracker.value_progress', 'Fortschritt heute (%)')) ?>
          <input type="number" name="value" min="0" max="100" step="1" placeholder="<?= h(t('tracker.value_placeholder_now', 'z.B. 45')) ?>">
        </label>
        <label>
          <?= h(t('tracker.value_forecast', 'Prognose in 1 Monat (%)')) ?>
          <input type="number" name="value_forecast" min="0" max="100" step="1" placeholder="<?= h(t('tracker.value_placeholder_forecast', 'z.B. 70')) ?>">
        </label>
      </div>
    </div>

    <label><?= h(t('tracker.note', 'Text / Begruendung')) ?>
      <textarea name="note" rows="3" maxlength="4000" required placeholder="<?= h(t('tracker.note_placeholder', 'z.B. Deadline verschoben auf 2026-12-22 (API-Verzoegerung) ...')) ?>"></textarea>
    </label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= h(t('tracker.submit', 'Eintrag speichern')) ?></button>
    </div>
  </form>

  <?php if ($trackers): ?>
    <?php $__editTrackerId = (int) get('edit_tracker', 0); ?>
    <ul class="tracker-list">
      <?php foreach ($trackers as $__t): ?>
        <?php $__isEditing = $__editTrackerId === (int) $__t['id']; ?>
        <li class="tracker-item<?= $__isEditing ? ' is-editing' : '' ?>" id="tracker-<?= (int) $__t['id'] ?>">
          <div class="tracker-item-head">
            <span class="tracker-item-date"><?= h(fmt_dt($__t['created_at'])) ?></span>
            <span class="tracker-item-user muted"><?= h($__t['user_username'] ?? '—') ?></span>
            <span class="tracker-item-type pill tracker-item-type-<?= h($__t['type']) ?>"><?= h(t('tracker.type.' . $__t['type'], $__t['type'])) ?></span>
            <?php if ($__t['type'] === 'status_report' && $__t['status'] !== null): ?>
              <span class="tracker-item-status pill tracker-item-status-<?= h($__t['status']) ?>"><?= h(t('tracker.status.' . $__t['status'], $__t['status'])) ?></span>
            <?php endif; ?>
            <?php if ($__t['type'] === 'status_report' && $__t['date_value'] !== null): ?>
              <span class="tracker-item-datevalue"><?= h((new DateTimeImmutable($__t['date_value']))->format('d.m.Y')) ?></span>
            <?php endif; ?>
            <?php if ($__t['type'] === 'progress' && $__t['value'] !== null): ?>
              <span class="tracker-item-value"><?= h(number_format($__t['value'], 0, ',', '.')) ?>%<?= $__t['value_forecast'] !== null ? ' → ' . h(number_format($__t['value_forecast'], 0, ',', '.')) . '%' : '' ?></span>
            <?php endif; ?>
            <div class="tracker-item-actions">
              <?php if ($__isEditing): ?>
                <a class="btn btn-sm btn-ghost" href="<?= h(route('project_show', ['id' => (int) $project['id']])) ?>#tracker-<?= (int) $__t['id'] ?>"><?= h(t('common.cancel', 'abbrechen')) ?></a>
              <?php else: ?>
                <a class="btn btn-sm" href="<?= h(route('project_show', ['id' => (int) $project['id'], 'edit_tracker' => (int) $__t['id']])) ?>#tracker-<?= (int) $__t['id'] ?>"><?= h(t('common.edit_short', 'bearb.')) ?></a>
              <?php endif; ?>
              <form method="post" action="<?= h(route('project_tracker_delete')) ?>" class="inline tracker-item-del"
                    data-confirm="<?= h(t('tracker.delete_confirm', 'Status-Eintrag endgueltig entfernen?')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $__t['id'] ?>">
                <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit" aria-label="<?= h(t('tracker.delete', 'loeschen')) ?>">×</button>
              </form>
            </div>
          </div>
          <?php if ($__isEditing): ?>
            <form method="post" action="<?= h(route('project_tracker_update')) ?>" class="tracker-edit-form">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $__t['id'] ?>">
              <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
              <label><?= h(t('tracker.edit_created_at', 'Zeitpunkt (nachtragen falls spaeter erfasst)')) ?>
                <input type="datetime-local" name="created_at"
                       value="<?= h((new DateTimeImmutable($__t['created_at']))->format('Y-m-d\TH:i')) ?>">
              </label>
              <?php if ($__t['type'] === 'status_report'): ?>
                <div class="tracker-status-row">
                  <label><?= h(t('tracker.status', 'Status')) ?>
                    <select name="status">
                      <?php foreach (['on_plan', 'delay', 'extension'] as $__st): ?>
                        <option value="<?= $__st ?>" <?= $__t['status'] === $__st ? 'selected' : '' ?>><?= h(t('tracker.status.' . $__st, $__st)) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label><?= h(t('tracker.new_deadline', 'Neue Deadline')) ?>
                    <input type="date" name="date_value" value="<?= h((string) ($__t['date_value'] ?? '')) ?>">
                  </label>
                </div>
              <?php elseif ($__t['type'] === 'progress'): ?>
                <div class="tracker-progress-row">
                  <label><?= h(t('tracker.value_progress', 'Fortschritt heute (%)')) ?>
                    <input type="number" name="value" min="0" max="100" step="1" value="<?= h($__t['value'] !== null ? (string) (int) $__t['value'] : '') ?>">
                  </label>
                  <label><?= h(t('tracker.value_forecast', 'Prognose in 1 Monat (%)')) ?>
                    <input type="number" name="value_forecast" min="0" max="100" step="1" value="<?= h($__t['value_forecast'] !== null ? (string) (int) $__t['value_forecast'] : '') ?>">
                  </label>
                </div>
              <?php endif; ?>
              <label><?= h(t('tracker.note', 'Text / Begruendung')) ?>
                <textarea name="note" rows="3" maxlength="4000" required><?= h($__t['note']) ?></textarea>
              </label>
              <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= h(t('common.save', 'Speichern')) ?></button>
                <a class="btn btn-ghost" href="<?= h(route('project_show', ['id' => (int) $project['id']])) ?>#tracker-<?= (int) $__t['id'] ?>"><?= h(t('common.cancel', 'abbrechen')) ?></a>
              </div>
            </form>
          <?php else: ?>
            <div class="tracker-item-note"><?= nl2br(h($__t['note'])) ?></div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="muted"><?= h(t('tracker.empty', 'Noch keine Status-Eintraege.')) ?></p>
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
