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

// Felix-Feedback 2026-11-09 (#148/#153): Monatsansicht - ganztaegige
// Abwesenheiten als Pillen ueber Monats-Kacheln. Werktage-only
// (Wochenende + Feiertag erzeugen Luecken, Pille bricht ab). Ein Span,
// der ueber die Wochengrenze geht, hat per Definition eine Wochenend-
// Luecke und wird als mehrere Spans zurueckkommen - also eine Pille pro
// Zeile.
// Fix #153: Pillen werden NICHT mehr via grid-row/column als Grid-Items
// platziert - das verdrangte den auto-flow der Monats-Kacheln (1. Nov
// landete auf Dienstag statt Sonntag). Stattdessen gruppieren wir die
// Pillen nach ihrem from-YMD und rendern sie INNERHALB der entsprechenden
// Monats-Kachel als position:absolute-Overlays mit span-abhaengiger Breite.
$__gridStartYmd = $gridStart->format('Y-m-d');
$__gridEndYmd   = $gridEnd->format('Y-m-d');
$__workingDows  = Holidays::workingDays();
$__holidayFlags = [];
foreach (($holidays ?? []) as $__k => $__v) { $__holidayFlags[(string) $__k] = true; }
$__monthAbsPills = []; // list<array{abs_id,cat,cat_ckey,span,from,to,note}>
$__monthPillsByDay = []; // ymd => list<pill-index>
foreach (($absences ?? []) as $__a) {
    $__aFrom = max((string) $__a['start_date'], $__gridStartYmd);
    $__aTo   = min((string) ($__a['end_date'] ?? $__a['start_date']), $__gridEndYmd);
    if ($__aFrom > $__aTo) { continue; }
    $__spans = WorkdaySpans::workdaySpans($__aFrom, $__aTo, $__workingDows, $__holidayFlags);
    foreach ($__spans as $__span) {
        $__idx = count($__monthAbsPills);
        $__monthAbsPills[] = [
            'abs_id'   => (int) $__a['id'],
            'cat'      => (string) ($__a['category_label'] ?? ''),
            'cat_ckey' => (string) ($__a['category_ckey'] ?? ''),
            'span'     => (int) $__span['days'],
            'from'     => $__span['from'],
            'to'       => $__span['to'],
            'note'     => (string) ($__a['note'] ?? ''),
        ];
        $__monthPillsByDay[$__span['from']][] = $__idx;
    }
}
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
      <?php /* Felix-Feedback 2026-11-09 (#111): Rhythmus-Umschalter ist im
         Benutzerbereich (Profil), nicht mehr im Kalender-Head. */ ?>
      <a class="btn btn-sm" href="<?= h($monthLink($prevMonth)) ?>"><?= h(t('cal.prev_month', '← Vormonat')) ?></a>
      <a class="btn btn-sm" href="<?= h(route('entries', $filterQp + ['view' => 'calendar', 'span' => 'month'])) ?>"><?= h(t('dash.today', 'Heute')) ?></a>
      <a class="btn btn-sm" href="<?= h($monthLink($nextMonth)) ?>"><?= h(t('cal.next_month', 'Folgemonat →')) ?></a>
    </div>
  </div>

  <div class="<?= h($gridClass) ?>" role="grid" aria-label="<?= h(t('cal.month_grid_aria', 'Monatsraster')) ?>">
    <?php if ($weekMode): ?>
      <div class="cal-month-dow cal-month-dow-sent" role="columnheader"><?= h(t('sent.mood_short', 'Mood')) ?><?= tip('cal.mood_column_hint') ?></div>
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
      // Felix-Feedback 2026-11-09 (#122): Wochenenden farblich wie in der
      // Wochenansicht hervorheben - Werktag-Erkennung ueber das gleiche
      // working_days-Preset.
      $isWorkDay = in_array((int) $d->format('N'), Holidays::workingDays(), true);
      $projects = $byDayProject[$ymd] ?? [];
      arsort($projects);
      $daySum   = array_sum($projects);
      $cls = 'cal-month-cell';
      if (!$inMonth)    $cls .= ' is-out';
      if ($isToday)     $cls .= ' is-today';
      if ($holiday)     $cls .= ' is-holiday';
      if (!$isWorkDay)  $cls .= ' is-weekend';
      if ($projects)    $cls .= ' has-entries';
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
        <?php /* Felix-Feedback 2026-11-09 (Fix #153): Abwesenheits-Pillen
           sitzen jetzt INNERHALB der jeweiligen ersten Monats-Kachel
           ihres Spans als position:absolute-Overlays. Grid-Auto-Flow
           der Kacheln bleibt damit intakt - 1. November landet wieder
           korrekt auf Sonntag. */ ?>
        <?php foreach ($__monthPillsByDay[$ymd] ?? [] as $__pIdx):
            $__p = $__monthAbsPills[$__pIdx];
            $__ckeyCss = preg_replace('~[^a-z0-9_-]~', '_', strtolower($__p['cat_ckey'] ?: 'absence'));
        ?>
          <button type="button"
                  class="cal-month-abs-pill cal-month-abs-pill-<?= h($__ckeyCss) ?> cal-month-abs-slot-<?= (int) $__pIdx ?>"
                  data-abs-id="<?= $__p['abs_id'] ?>"
                  title="<?= h($__p['cat'] . ' (' . $__p['from'] . ' – ' . $__p['to'] . ')' . ($__p['note'] !== '' ? ' · ' . $__p['note'] : '')) ?>">
            <?= h($__p['cat']) ?>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<style nonce="<?= h(csp_nonce()) ?>">
<?php /* Fix #153: Pro-Pill nur noch Width setzen (Span * 100% bezogen auf
   die erste Zelle). CSS .cal-month-abs-pill traegt bereits
   position:absolute + top/left. */ ?>
<?php foreach ($__monthAbsPills as $__k => $__p):
    $__wPct = (int) $__p['span'] * 100;
?>
.cal-month-abs-slot-<?= (int) $__k ?>{width:calc(<?= $__wPct ?>% - 8px)}
<?php endforeach; ?>
</style>

<?php require __DIR__ . '/_sentiment_picker_js.php'; ?>
