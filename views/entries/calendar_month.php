<?php
/**
 * Month calendar — Community edition.
 *
 * 7-Spalten-Grid (Mo-So) ueber den sichtbaren Monat. Pro Tag: Datum oben,
 * dann bis zu drei Projekt-Zeilen (Projektname + Stunden). Click auf eine
 * Tageszelle wechselt zurueck in die Wochenansicht dieser Woche.
 *
 * Portiert aus der Pro-Variante (Felix-Feedback 2026-11-04: "warum gibt es
 * keine monats-sicht mehr?"), auf Community-Scope reduziert: keine Mood-
 * Smileys im Monat, keine Abwesenheits-Pillen, kein Jahres-Pager.
 *
 * @var array             $entries
 * @var DateTimeImmutable $month_start
 * @var array             $holidays Keyed by ISO date.
 * @var string            $pro_url
 * @var ?string           $calendarSpan
 */
$monthStart = $month_start ?? new DateTimeImmutable('first day of this month');
$monthEnd   = $monthStart->modify('last day of this month');
$prevMonth  = $monthStart->modify('-1 month');
$nextMonth  = $monthStart->modify('+1 month');
$today      = new DateTimeImmutable('today');

$dayHeaders = [
    t('cal.day_short.mon', 'Mo'),
    t('cal.day_short.tue', 'Di'),
    t('cal.day_short.wed', 'Mi'),
    t('cal.day_short.thu', 'Do'),
    t('cal.day_short.fri', 'Fr'),
    t('cal.day_short.sat', 'Sa'),
    t('cal.day_short.sun', 'So'),
];

// Tage im Grid: vom Montag der 1.-Monatswoche bis Sonntag der letzten
// Monatswoche (= 6 Wochen * 7 Tage in >99 % der Faelle, sonst 4 oder 5).
$gridStart = $monthStart->modify('monday this week');
$gridEnd   = $monthEnd->modify('sunday this week');

// Buchungen gruppiert pro Tag + Projekt: $byDayProject[ymd][clientname/projectname] = minutes
$byDayProject = [];
foreach ($entries as $e) {
    try {
        $s = new DateTimeImmutable((string) $e['start_ts']);
    } catch (Throwable) {
        continue;
    }
    $ymd = $s->format('Y-m-d');
    $label = ((string) ($e['client_name'] ?? ''))
        . (($e['project_name'] ?? '') !== '' ? ' / ' . (string) $e['project_name'] : '');
    if ($label === ' / ' || $label === '') {
        $label = (string) ($e['project_name'] ?? '—');
    }
    $byDayProject[$ymd][$label] = ($byDayProject[$ymd][$label] ?? 0) + (int) ($e['duration_min'] ?? 0);
}

$filterQp = array_filter($filter ?? [], static fn($v) => $v !== '' && $v !== 0 && $v !== null);
unset($filterQp['from'], $filterQp['to']);
$monthLink = static fn(DateTimeImmutable $m): string => route('entries', $filterQp + [
    'view'  => 'calendar',
    'span'  => 'month',
    'month' => $m->format('Y-m'),
]);
$weekLink = static fn(DateTimeImmutable $d): string => route('entries', $filterQp + [
    'view' => 'calendar',
    'week' => $d->format('o-\WW'),
]);

$fmtHours = static fn(int $min): string => number_format($min / 60, 1, ',', '.');
$maxProjectsPerCell = 3;

$monthName = $monthStart->format('F Y');
// Besseren Monats-Namen via Datumsformat (DE i18n ueber t-Keys optional).
?>
<div class="card cal-month-card">
  <div class="card-head">
    <div>
      <h2><?= h(sprintf(t('cal.month_heading', 'Monat %s'), $monthStart->format('m / Y'))) ?></h2>
      <p class="muted"><?= h($monthStart->format('d.m.')) ?> – <?= h($monthEnd->format('d.m.Y')) ?></p>
    </div>
    <div class="cal-nav">
      <?php /* Toggle Woche|Monat (Pro-Paritaet). */ ?>
      <span class="cal-span-toggle" role="group" aria-label="<?= h(t('cal.span_toggle', 'Ansicht')) ?>">
        <a class="btn btn-sm" href="<?= h(route('entries', $filterQp + ['view' => 'calendar'])) ?>"><?= h(t('cal.span_week', 'Woche')) ?></a>
        <a class="btn btn-sm is-active" aria-current="page" href="<?= h($monthLink($monthStart)) ?>"><?= h(t('cal.span_month', 'Monat')) ?></a>
      </span>
      <a class="btn btn-sm" href="<?= h($monthLink($prevMonth)) ?>"><?= h(t('cal.prev_month', '← Vormonat')) ?></a>
      <a class="btn btn-sm" href="<?= h(route('entries', $filterQp + ['view' => 'calendar', 'span' => 'month'])) ?>"><?= h(t('dash.today', 'Heute')) ?></a>
      <a class="btn btn-sm" href="<?= h($monthLink($nextMonth)) ?>"><?= h(t('cal.next_month', 'Folgemonat →')) ?></a>
    </div>
  </div>

  <div class="cal-month-grid" role="grid" aria-label="<?= h(t('cal.month_grid_aria', 'Monatsraster')) ?>">
    <?php foreach ($dayHeaders as $__dh): ?>
      <div class="cal-month-dow" role="columnheader"><?= h($__dh) ?></div>
    <?php endforeach; ?>
    <?php for ($d = $gridStart; $d <= $gridEnd; $d = $d->modify('+1 day')):
      $ymd      = $d->format('Y-m-d');
      $inMonth  = (int) $d->format('n') === (int) $monthStart->format('n');
      $isToday  = $ymd === $today->format('Y-m-d');
      $holiday  = $holidays[$ymd] ?? null;
      $projects = $byDayProject[$ymd] ?? [];
      arsort($projects);
      $daySum   = array_sum($projects);
      $cls = 'cal-month-cell';
      if (!$inMonth) $cls .= ' is-out';
      if ($isToday)  $cls .= ' is-today';
      if ($holiday)  $cls .= ' is-holiday';
      if ($projects) $cls .= ' has-entries';
    ?>
      <a class="<?= h($cls) ?>" href="<?= h($weekLink($d)) ?>" data-day="<?= h($ymd) ?>" role="gridcell" aria-label="<?= h($d->format('d.m.Y')) ?>">
        <div class="cal-month-cell-head">
          <span class="cal-month-day-num"><?= (int) $d->format('j') ?></span>
          <?php if ($holiday): ?>
            <span class="cal-month-hol" title="<?= h(t($holiday['key'], $holiday['key'])) ?>">★</span>
          <?php endif; ?>
          <?php if ($daySum > 0): ?>
            <span class="cal-month-sum"><?= h($fmtHours($daySum)) ?> h</span>
          <?php endif; ?>
        </div>
        <?php if ($projects): ?>
          <div class="cal-month-projs">
            <?php $__shown = 0; foreach ($projects as $__name => $__min):
              if ($__shown >= $maxProjectsPerCell) break;
              $__shown++;
            ?>
              <span class="cal-month-proj" title="<?= h($__name . ' · ' . $fmtHours($__min) . ' h') ?>">
                <span class="cal-month-proj-name"><?= h($__name) ?></span>
                <span class="cal-month-proj-h"><?= h($fmtHours($__min)) ?> h</span>
              </span>
            <?php endforeach; ?>
            <?php $__extra = count($projects) - $maxProjectsPerCell; if ($__extra > 0): ?>
              <span class="cal-month-proj cal-month-more">+ <?= (int) $__extra ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </a>
    <?php endfor; ?>
  </div>
</div>
