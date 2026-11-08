<?php
/**
 * Month calendar — Community edition.
 *
 * 7-Spalten-Grid (Mo–So) ueber den sichtbaren Monat. Pro Tag: Datum oben,
 * dann bis zu drei Projekt-Zeilen (Projektname + Stunden). Click auf eine
 * Tageszelle wechselt zurueck in die Wochenansicht dieser Woche.
 *
 * Mood-/Flow-Tracking nach demselben Rhythmus wie die Wochenansicht
 * (Felix-Feedback 2026-11-08): Default ist Woche, dann liegt links vom
 * Montag eine Extra-Spalte mit einem Picker fuer die ganze Woche. Nur wenn
 * die Granularitaet auf "Taeglich" steht, erscheint am Fuss jeder Fliese
 * ein Picker fuer genau diesen Tag.
 *
 * @var array             $entries
 * @var DateTimeImmutable $month_start
 * @var array             $holidays Keyed by ISO date.
 * @var string            $pro_url
 * @var ?string           $calendarSpan
 * @var array             $weekSentiment  Keyed by Y-m-d ⇒ ['mood'=>int|null,'flow'=>int|null]
 * @var ?string           $sentimentGranularity 'day' or 'week'
 * @var bool              $canRecordSentiment
 * @var bool              $canAdminSentiment
 */
$monthStart = $month_start ?? new DateTimeImmutable('first day of this month');
$monthEnd   = $monthStart->modify('last day of this month');
$prevMonth  = $monthStart->modify('-1 month');
$nextMonth  = $monthStart->modify('+1 month');
$today      = new DateTimeImmutable('today');

// Wochentags-Labels + Grid-Grenzen folgen dem konfigurierten Wochenanfang
// (heute Montag, Milestone-1.1-ready fuer abweichende Locale-Einstellungen).
$dayHeaders = CalendarLocale::weekdayShortLabels();
$gridStart  = CalendarLocale::weekStart($monthStart);
$gridEnd    = CalendarLocale::weekEnd($monthEnd);

// Buchungen gruppiert pro Tag + Projekt.
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

$sentGranularity = ($sentimentGranularity ?? 'week') === 'day' ? 'day' : 'week';
$weekMode = $sentGranularity === 'week' && !empty($canRecordSentiment);
$dayMode  = $sentGranularity === 'day'  && !empty($canRecordSentiment);
$gridClass = 'cal-month-grid' . ($weekMode ? ' cal-month-grid-with-sent' : '');
?>
<div class="card cal-month-card">
  <div class="card-head">
    <div>
      <h2><?= h(sprintf(t('cal.month_heading', 'Monat %s'), $monthStart->format('m / Y'))) ?></h2>
      <p class="muted"><?= h($monthStart->format('d.m.')) ?> – <?= h($monthEnd->format('d.m.Y')) ?></p>
    </div>
    <div class="cal-nav">
      <span class="cal-span-toggle" role="group" aria-label="<?= h(t('cal.span_toggle', 'Ansicht')) ?>">
        <a class="btn btn-sm" href="<?= h(route('entries', $filterQp + ['view' => 'calendar'])) ?>"><?= h(t('cal.span_week', 'Woche')) ?></a>
        <a class="btn btn-sm is-active" aria-current="page" href="<?= h($monthLink($monthStart)) ?>"><?= h(t('cal.span_month', 'Monat')) ?></a>
      </span>
      <?php if (!empty($canAdminSentiment)): ?>
        <span class="cal-sent-toggle" data-current="<?= h($sentGranularity) ?>" data-csrf="<?= h(csrf_token()) ?>" role="group" aria-label="<?= h(t('sent.granularity', 'Stimmungs-Rhythmus')) ?>">
          <button type="button" class="btn btn-sm<?= $sentGranularity === 'week' ? ' is-active' : '' ?>" data-g="week"><?= h(t('sent.granularity_week', 'Woechentlich')) ?></button>
          <button type="button" class="btn btn-sm<?= $sentGranularity === 'day'  ? ' is-active' : '' ?>" data-g="day"><?= h(t('sent.granularity_day',  'Taeglich')) ?></button>
        </span>
      <?php endif; ?>
      <a class="btn btn-sm" href="<?= h($monthLink($prevMonth)) ?>"><?= h(t('cal.prev_month', '← Vormonat')) ?></a>
      <a class="btn btn-sm" href="<?= h(route('entries', $filterQp + ['view' => 'calendar', 'span' => 'month'])) ?>"><?= h(t('dash.today', 'Heute')) ?></a>
      <a class="btn btn-sm" href="<?= h($monthLink($nextMonth)) ?>"><?= h(t('cal.next_month', 'Folgemonat →')) ?></a>
    </div>
  </div>

  <div class="<?= h($gridClass) ?>" role="grid" aria-label="<?= h(t('cal.month_grid_aria', 'Monatsraster')) ?>">
    <?php if ($weekMode): ?>
      <div class="cal-month-dow cal-month-dow-sent" role="columnheader" title="<?= h(t('sent.mood_short', 'Mood')) . ' / ' . h(t('sent.flow_short', 'Flow')) ?>"><?= h(t('sent.mood_short', 'Mood')) ?></div>
    <?php endif; ?>
    <?php foreach ($dayHeaders as $__dh): ?>
      <div class="cal-month-dow" role="columnheader"><?= h($__dh) ?></div>
    <?php endforeach; ?>
    <?php for ($d = $gridStart; $d <= $gridEnd; $d = $d->modify('+1 day')):
      $ymd      = $d->format('Y-m-d');
      $isWeekStart = CalendarLocale::isWeekStart($d);
      if ($weekMode && $isWeekStart): ?>
        <?php /* Wochen-Mood-Zelle links vom konfigurierten Wochenanfang
           (Montag per Default). Placeholder: Neutrales Emoji (Score 3),
           abgegraut durch is-empty. */ ?>
        <div class="cal-month-sent-col" role="gridcell">
          <div class="cal-sent-week cal-sent-week-stack" data-monday="<?= h($ymd) ?>" data-csrf="<?= h(csrf_token()) ?>">
            <div class="cal-sent-week-row cal-sent-cell" data-dim="mood">
              <span class="cal-sent-label"><?= h(t('sent.mood_short', 'Mood')) ?></span>
              <button type="button" class="cal-sent-current is-empty" aria-label="<?= h(t('sent.mood_short', 'Mood')) ?>"><?= Sentiment::moodEmoji(3) ?></button>
              <div class="cal-sent-popover" hidden role="listbox" aria-label="<?= h(t('sent.mood_short', 'Mood')) ?>">
                <?php foreach ([1,2,3,4,5] as $__s): ?>
                  <button type="button" class="cal-sent-opt" data-score="<?= $__s ?>" role="option" title="<?= h(Sentiment::moodLabel($__s)) ?>"><?= Sentiment::moodEmoji($__s) ?></button>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="cal-sent-week-row cal-sent-cell" data-dim="flow">
              <span class="cal-sent-label"><?= h(t('sent.flow_short', 'Flow')) ?></span>
              <button type="button" class="cal-sent-current is-empty" aria-label="<?= h(t('sent.flow_short', 'Flow')) ?>"><?= Sentiment::flowEmoji(3) ?></button>
              <div class="cal-sent-popover" hidden role="listbox" aria-label="<?= h(t('sent.flow_short', 'Flow')) ?>">
                <?php foreach ([1,2,3,4,5] as $__s): ?>
                  <button type="button" class="cal-sent-opt" data-score="<?= $__s ?>" role="option" title="<?= h(Sentiment::flowLabel($__s)) ?>"><?= Sentiment::flowEmoji($__s) ?></button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endif;

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
      <div class="<?= h($cls) ?>" data-day="<?= h($ymd) ?>" role="gridcell">
        <a class="cal-month-link" href="<?= h($weekLink($d)) ?>" aria-label="<?= h($d->format('d.m.Y')) ?>">
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
        <?php if ($dayMode && $inMonth):
          $__dayMood = $weekSentiment[$ymd]['mood'] ?? null;
          $__dayFlow = $weekSentiment[$ymd]['flow'] ?? null;
          $__isFuture = $d > $today;
          $__moodCur = Sentiment::moodEmoji($__dayMood !== null ? $__dayMood : 3);
          $__flowCur = Sentiment::flowEmoji($__dayFlow !== null ? $__dayFlow : 3);
        ?>
          <div class="cal-sent-day cal-sent-day-month<?= $__isFuture ? ' is-future' : '' ?>" data-day="<?= h($ymd) ?>" data-csrf="<?= h(csrf_token()) ?>" <?= $__isFuture ? 'title="' . h(t('cal.sent_future', 'Zukunft')) . '"' : '' ?>>
            <div class="cal-sent-cell" data-dim="mood" data-current="<?= $__dayMood ?? '' ?>">
              <button type="button" class="cal-sent-current<?= $__dayMood === null ? ' is-empty' : '' ?>" <?= $__isFuture ? 'disabled' : '' ?> title="<?= h(t('sent.mood_short', 'Mood')) . ($__dayMood !== null ? ': ' . h(Sentiment::moodLabel($__dayMood)) : '') ?>" aria-label="<?= h(t('sent.mood_short', 'Mood')) ?>"><?= $__moodCur ?></button>
              <div class="cal-sent-popover" hidden role="listbox" aria-label="<?= h(t('sent.mood_short', 'Mood')) ?>">
                <?php foreach ([1,2,3,4,5] as $__s): ?>
                  <button type="button" class="cal-sent-opt<?= $__dayMood === $__s ? ' is-selected' : '' ?>" data-score="<?= $__s ?>" role="option" aria-selected="<?= $__dayMood === $__s ? 'true' : 'false' ?>" title="<?= h(Sentiment::moodLabel($__s)) ?>"><?= Sentiment::moodEmoji($__s) ?></button>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="cal-sent-cell" data-dim="flow" data-current="<?= $__dayFlow ?? '' ?>">
              <button type="button" class="cal-sent-current<?= $__dayFlow === null ? ' is-empty' : '' ?>" <?= $__isFuture ? 'disabled' : '' ?> title="<?= h(t('sent.flow_short', 'Flow')) . ($__dayFlow !== null ? ': ' . h(Sentiment::flowLabel($__dayFlow)) : '') ?>" aria-label="<?= h(t('sent.flow_short', 'Flow')) ?>"><?= $__flowCur ?></button>
              <div class="cal-sent-popover" hidden role="listbox" aria-label="<?= h(t('sent.flow_short', 'Flow')) ?>">
                <?php foreach ([1,2,3,4,5] as $__s): ?>
                  <button type="button" class="cal-sent-opt<?= $__dayFlow === $__s ? ' is-selected' : '' ?>" data-score="<?= $__s ?>" role="option" aria-selected="<?= $__dayFlow === $__s ? 'true' : 'false' ?>" title="<?= h(Sentiment::flowLabel($__s)) ?>"><?= Sentiment::flowEmoji($__s) ?></button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<?php require __DIR__ . '/_sentiment_picker_js.php'; ?>
