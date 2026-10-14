<?php
/**
 * @var array $rows           ProjectReport::rows()
 * @var array $clients        active clients for the filter dropdown
 * @var ?int  $filter_client
 * @var string $filter_from
 * @var string $filter_to
 */

$__rowCount    = count($rows);
$__hasAny      = $__rowCount > 0;
// Compute chart geometry server-side (strict CSP: no inline style="").
$__barMaxH     = 0;
foreach ($rows as $__r) {
    $__barMaxH = max($__barMaxH, $__r['target_hours'], $__r['actual_minutes'] / 60);
}
if ($__barMaxH <= 0) { $__barMaxH = 1; }
$__chartHeight = 200;
$__barWidth    = 24;
$__barGap      = 10;
$__groupWidth  = $__barWidth * 2 + $__barGap;
$__labelHeight = 48;
$__chartWidth  = max(320, $__rowCount * ($__groupWidth + 24) + 40);
?>
<div class="card">
  <div class="card-head">
    <h2><?= h(t('report.projects_h2', 'Projekt-Report — Ist vs Plan')) ?></h2>
    <?php if ($__hasAny): ?>
      <a class="btn btn-sm" href="<?= h(route('report_projects_export', [
        'client' => $filter_client ?? 0,
        'from'   => $filter_from,
        'to'     => $filter_to,
      ])) ?>"><?= h(t('report.csv_export', 'als CSV')) ?></a>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= h(route('report_projects')) ?>" class="inline-filter report-filter">
    <input type="hidden" name="r" value="report_projects">
    <label><?= h(t('report.filter_client', 'Kunde')) ?>
      <select name="client">
        <option value="0"><?= h(t('common.all', 'Alle')) ?></option>
        <?php foreach ($clients as $__c): ?>
          <option value="<?= (int) $__c['id'] ?>" <?= $filter_client === (int) $__c['id'] ? 'selected' : '' ?>>
            <?= h($__c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= h(t('report.filter_from', 'Ab')) ?>
      <input type="date" name="from" value="<?= h($filter_from) ?>">
    </label>
    <label><?= h(t('report.filter_to', 'Bis')) ?>
      <input type="date" name="to" value="<?= h($filter_to) ?>">
    </label>
    <button class="btn btn-sm" type="submit"><?= h(t('common.apply', 'Anwenden')) ?></button>
    <?php if ($filter_client || $filter_from !== '' || $filter_to !== ''): ?>
      <a class="btn btn-sm btn-ghost" href="<?= h(route('report_projects')) ?>"><?= h(t('common.reset', 'Zuruecksetzen')) ?></a>
    <?php endif; ?>
  </form>

  <?php if (!$__hasAny): ?>
    <p class="muted"><?= h(t('report.empty', 'Noch keine aktiven Projekte mit gesetztem Soll. Lege ein Soll bei einem Projekt an, um den Report zu nutzen.')) ?></p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th><?= h(t('planning.col_project', 'Projekt')) ?></th>
          <th><?= h(t('struct.client', 'Kunde')) ?></th>
          <th class="r"><?= h(t('planning.col_target', 'Soll (h)')) ?></th>
          <th class="r"><?= h(t('planning.col_actual', 'Ist (h)')) ?></th>
          <th class="r"><?= h(t('report.col_diff', 'Differenz (h)')) ?></th>
          <th class="r"><?= h(t('report.col_percent', '%')) ?></th>
          <th><?= h(t('report.col_status', 'Status')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $__r): ?>
          <?php
            $__diffH = $__r['diff_minutes'] / 60;
            $__badgeClass = 'report-badge-' . $__r['status'];
            $__badgeLabel = $__r['status'] === 'over' ? t('report.status_over', 'ueber Plan')
                           : ($__r['status'] === 'warn' ? t('report.status_warn', 'nahe am Limit')
                           : t('report.status_ok', 'im Plan'));
          ?>
          <tr>
            <td><?= h($__r['name']) ?></td>
            <td><?= h($__r['client_name']) ?></td>
            <td class="r"><?= h(number_format($__r['target_hours'], 2, ',', '.')) ?></td>
            <td class="r"><?= h(fmt_hours($__r['actual_minutes'])) ?></td>
            <td class="r"><?= h(($__diffH >= 0 ? '+' : '') . number_format($__diffH, 2, ',', '.')) ?></td>
            <td class="r"><?= (int) $__r['percent'] ?>%</td>
            <td><span class="report-status-badge <?= $__badgeClass ?>"><?= h($__badgeLabel) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <h3 class="report-chart-head"><?= h(t('report.chart_h3', 'Soll vs Ist (Balken je Projekt)')) ?></h3>
    <div class="report-chart-wrap">
      <svg viewBox="0 0 <?= $__chartWidth ?> <?= $__chartHeight + $__labelHeight ?>"
           width="100%" height="<?= $__chartHeight + $__labelHeight ?>"
           role="img" aria-label="<?= h(t('report.chart_aria', 'Balkendiagramm Soll vs Ist pro Projekt')) ?>"
           xmlns="http://www.w3.org/2000/svg">
        <?php $__x = 20; foreach ($rows as $__r):
          $__targetH = $__r['target_hours'];
          $__actualH = $__r['actual_minutes'] / 60;
          $__tH = (int) round(($__targetH / $__barMaxH) * $__chartHeight);
          $__aH = (int) round(($__actualH / $__barMaxH) * $__chartHeight);
          $__tY = $__chartHeight - $__tH;
          $__aY = $__chartHeight - $__aH;
          $__fillClass = 'report-chart-ist-' . $__r['status'];
          $__short = mb_substr($__r['name'], 0, 14);
        ?>
          <rect x="<?= $__x ?>" y="<?= $__tY ?>" width="<?= $__barWidth ?>" height="<?= $__tH ?>"
                class="report-chart-soll">
            <title><?= h(sprintf(t('report.chart_bar_target', 'Soll: %s h'), number_format($__targetH, 2, ',', '.'))) ?></title>
          </rect>
          <rect x="<?= $__x + $__barWidth + $__barGap ?>" y="<?= $__aY ?>" width="<?= $__barWidth ?>" height="<?= $__aH ?>"
                class="<?= $__fillClass ?>">
            <title><?= h(sprintf(t('report.chart_bar_actual', 'Ist: %s h'), number_format($__actualH, 2, ',', '.'))) ?></title>
          </rect>
          <text x="<?= $__x + $__groupWidth / 2 ?>" y="<?= $__chartHeight + 16 ?>"
                text-anchor="middle" class="report-chart-label"><?= h($__short) ?></text>
        <?php $__x += $__groupWidth + 24; endforeach; ?>
      </svg>
      <p class="muted report-chart-legend">
        <span class="report-chart-legend-swatch report-chart-legend-soll"></span> <?= h(t('report.legend_target', 'Soll')) ?>
        <span class="report-chart-legend-swatch report-chart-legend-ist-ok"></span> <?= h(t('report.legend_actual', 'Ist')) ?>
        <small class="muted">· <?= h(t('report.chart_tip', 'rot = ueber Plan, gelb = nah am Limit, gruen = im Plan')) ?></small>
      </p>
    </div>
  <?php endif; ?>

  <p class="muted report-pro-teaser">
    <?= h(t('report.pro_teaser.intro', 'Budget-Spalte in €, Verzoegerungs-Report, PDF-Export und Heatmap sind Teil von')) ?>
    <a href="<?= h(route('pro_budget')) ?>"><strong><?= h(t('planning.pro_teaser.link', 'Timeminator Pro')) ?></strong></a>.
  </p>
</div>
