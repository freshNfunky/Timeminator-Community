<?php
/**
 * Week calendar — Community edition.
 *
 * Renders a 7-day grid with hourly resolution and the user's own
 * `time_entries` positioned as colored blocks. Click a block → edit form.
 * Entries that cross midnight are clipped to the visible day; a running
 * timer with NULL end_ts is drawn up to "now".
 *
 * External calendar sources (CalDAV, iCal subscriptions) are **not**
 * available in Community — a small inline teaser links to Pro.
 *
 * Per-event positioning (top/height) and client color is emitted into a
 * single nonce-authorized `<style>` block below so no inline `style=""`
 * attributes are needed and the strict CSP stays strict.
 *
 * @var array             $entries
 * @var DateTimeImmutable $week_start
 * @var string            $pro_url
 * @var array             $holidays  Keyed by ISO date; from Holidays::byDate()
 */
$holidays = $holidays ?? [];
$__weekEnd  = $week_start->modify('+6 days');
$__prevWeek = $week_start->modify('-7 days');
$__nextWeek = $week_start->modify('+7 days');
$__today    = new DateTimeImmutable('today');

$__dayHeaders = [
    t('cal.day_short.mon', 'Mo'),
    t('cal.day_short.tue', 'Di'),
    t('cal.day_short.wed', 'Mi'),
    t('cal.day_short.thu', 'Do'),
    t('cal.day_short.fri', 'Fr'),
    t('cal.day_short.sat', 'Sa'),
    t('cal.day_short.sun', 'So'),
];
$__days = [];
$__workingDays = Holidays::workingDays();
for ($i = 0; $i < 7; $i++) {
    $d = $week_start->modify('+' . $i . ' days');
    $__isoDow = (int) $d->format('N');
    $__days[] = [
        'date'        => $d,
        'ymd'         => $d->format('Y-m-d'),
        'label'       => $__dayHeaders[$i] . ' ' . $d->format('d.m.'),
        'isToday'     => $d->format('Y-m-d') === $__today->format('Y-m-d'),
        'isWorkDay'   => in_array($__isoDow, $__workingDays, true),
    ];
}

$__nowTs   = time();
$__weekMid = (int) $week_start->setTime(0, 0)->getTimestamp();

// Felix-Feedback 2026-11-09 (#138): rote "Jetzt"-Linie im heutigen
// Spalten-Grid, Pro-Parity. Nur rendern, wenn TODAY in der aktuell
// sichtbaren Woche liegt (sonst ist die Linie sinnlos).
$__nowDt       = new DateTimeImmutable();
$__isCurWeek   = ($__nowDt >= $week_start) && ($__nowDt < $week_start->modify('+7 days'));
$__nowPct      = null;
if ($__isCurWeek) {
    $__nowMin = (int) $__nowDt->format('G') * 60 + (int) $__nowDt->format('i');
    $__nowPct = round($__nowMin / (24 * 60) * 100, 3);
}
$__perDay  = [];
$__minTop  = 100.0;
foreach ($entries as $__e) {
    try {
        $__s = new DateTimeImmutable((string) $__e['start_ts']);
    } catch (Throwable) {
        continue;
    }
    try {
        $__eEnd = !empty($__e['end_ts'])
            ? new DateTimeImmutable((string) $__e['end_ts'])
            : (new DateTimeImmutable())->setTimestamp($__nowTs);
    } catch (Throwable) {
        $__eEnd = $__s->modify('+15 minutes');
    }
    if ($__eEnd <= $__s) {
        $__eEnd = $__s->modify('+15 minutes');
    }

    $__sTs = $__s->getTimestamp();
    $__dayOffset = (int) floor(($__sTs - $__weekMid) / 86400);
    if ($__dayOffset < 0 || $__dayOffset > 6) {
        continue;
    }
    $__dayStart = (int) $week_start->modify('+' . $__dayOffset . ' days')->setTime(0, 0)->getTimestamp();
    $__dayEnd   = $__dayStart + 86400;

    $__visibleStart = max($__sTs, $__dayStart);
    $__visibleEnd   = min($__eEnd->getTimestamp(), $__dayEnd);

    $__topPct = (($__visibleStart - $__dayStart) / 86400) * 100.0;
    $__hPct   = (($__visibleEnd - $__visibleStart) / 86400) * 100.0;
    if ($__hPct < 1.2) {
        $__hPct = 1.2;
    }
    if ($__topPct < $__minTop) {
        $__minTop = $__topPct;
    }

    $__color = strtolower((string) ($__e['client_color'] ?? ''));
    if ($__color !== '' && !preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $__color)) {
        $__color = '';
    }

    $__perDay[$__dayOffset][] = [
        'id'      => (int) $__e['id'],
        'top'     => round($__topPct, 3),
        'height'  => round($__hPct, 3),
        'start'   => $__s->format('H:i'),
        'end'     => !empty($__e['end_ts']) ? $__eEnd->format('H:i') : '…',
        'client'  => (string) ($__e['client_name'] ?? ''),
        'project' => (string) ($__e['project_name'] ?? ''),
        'task'    => (string) ($__e['task_name'] ?? ''),
        'note'    => (string) ($__e['note'] ?? ''),
        'running' => empty($__e['end_ts']),
        'color'   => $__color,
    ];
}

// v1.0 Kalender-Dialog: Interne Prozesse als farblich getrennte Bloecke
// im selben Grid rendern (gleiche top/height-Mathematik wie entries).
$__procPerDay = [];
foreach (($processes ?? []) as $__p) {
    try {
        $__s = new DateTimeImmutable((string) $__p['start_ts']);
        $__eEnd = new DateTimeImmutable((string) ($__p['end_ts'] ?? $__p['start_ts']));
    } catch (Throwable) { continue; }
    if ($__eEnd <= $__s) { $__eEnd = $__s->modify('+15 minutes'); }
    $__sTs = $__s->getTimestamp();
    $__dayOffset = (int) floor(($__sTs - $__weekMid) / 86400);
    if ($__dayOffset < 0 || $__dayOffset > 6) { continue; }
    $__dayStart = (int) $week_start->modify('+' . $__dayOffset . ' days')->setTime(0, 0)->getTimestamp();
    $__dayEnd = $__dayStart + 86400;
    $__vs = max($__sTs, $__dayStart);
    $__ve = min($__eEnd->getTimestamp(), $__dayEnd);
    $__topPct = (($__vs - $__dayStart) / 86400) * 100.0;
    $__hPct = (($__ve - $__vs) / 86400) * 100.0;
    if ($__hPct < 1.2) { $__hPct = 1.2; }
    if ($__topPct < $__minTop) { $__minTop = $__topPct; }
    $__procPerDay[$__dayOffset][] = [
        'id'     => (int) $__p['id'],
        'top'    => round($__topPct, 3),
        'height' => round($__hPct, 3),
        'start'  => $__s->format('H:i'),
        'end'    => $__eEnd->format('H:i'),
        'label'  => (string) ($__p['category_label'] ?? ''),
        'note'   => (string) ($__p['note'] ?? ''),
    ];
}

// Felix-Feedback 2026-11-08 (#99): Tagessoll-Klammer rechts der Buchungen.
// Pro Tag kumulierte Minuten aus Entries + Prozessen (Abwesenheiten zaehlen
// nicht fuer die Klammer); Grenze wird mitten in einer Buchung gesetzt,
// wenn das Tagessoll dort erreicht ist. Freelancer bekommen keine Klammer.
// Felix-Feedback 2026-11-09 (#119): Tagessoll pro Tag, nicht pauschal.
// Wochenenden + Feiertage haben Tagessoll 0 (freier Tag). Freelancer
// ohne Vertragsdaten haben Tagessoll null (kein Badge, keine Klammer).
// Felix-Feedback 2026-11-09 (Code-Review Fix #131): keine parallele
// $__dayTargetMin-Variable mehr - der per-Tag-Map ist die einzige Quelle.
$__dayTargetByOffset = [];
$__dayBrackets       = [];
$__uid = (int) Auth::id();
if ($__uid > 0) {
    foreach ($__days as $__i => $__day) {
        $__dayTargetByOffset[$__i] = EmployeeRepo::dailyTargetMinutes($__uid, $__day['ymd']);
    }
}
// Gate: nur rechnen, wenn mindestens ein Werktag mit Tagessoll > 0 im Grid.
$__hasAnyTarget = false;
foreach ($__dayTargetByOffset as $__t) {
    if ($__t !== null && $__t > 0) { $__hasAnyTarget = true; break; }
}
if ($__hasAnyTarget) {
    $__eventsByDay = [];
    foreach ($entries as $__e) {
        try {
            $__s = new DateTimeImmutable((string) $__e['start_ts']);
            $__eEnd = new DateTimeImmutable((string) ($__e['end_ts'] ?? $__e['start_ts']));
        } catch (Throwable) { continue; }
        if ($__eEnd <= $__s) { continue; }
        $__off = (int) floor(($__s->getTimestamp() - $__weekMid) / 86400);
        if ($__off < 0 || $__off > 6) { continue; }
        $__eventsByDay[$__off][] = [
            'start_min' => (int) $__s->format('H') * 60 + (int) $__s->format('i'),
            'end_min'   => (int) $__eEnd->format('H') * 60 + (int) $__eEnd->format('i'),
        ];
    }
    foreach (($processes ?? []) as $__p) {
        try {
            $__s = new DateTimeImmutable((string) $__p['start_ts']);
            $__eEnd = new DateTimeImmutable((string) ($__p['end_ts'] ?? $__p['start_ts']));
        } catch (Throwable) { continue; }
        if ($__eEnd <= $__s) { continue; }
        $__off = (int) floor(($__s->getTimestamp() - $__weekMid) / 86400);
        if ($__off < 0 || $__off > 6) { continue; }
        $__eventsByDay[$__off][] = [
            'start_min' => (int) $__s->format('H') * 60 + (int) $__s->format('i'),
            'end_min'   => (int) $__eEnd->format('H') * 60 + (int) $__eEnd->format('i'),
        ];
    }
    foreach ($__eventsByDay as $__off => $__evs) {
        // Pro Tag: eigenes Tagessoll. Wochenenden / Feiertage (Soll=0)
        // bekommen keine Klammer, weil Buchungen dort als Ueberstunden
        // zaehlen - die entsprechende Darstellung macht die Overtime-
        // Klammer selbst.
        $__dayTgt = $__dayTargetByOffset[$__off] ?? null;
        if ($__dayTgt === null || $__dayTgt <= 0) { continue; }
        usort($__evs, static fn($a, $b) => $a['start_min'] <=> $b['start_min']);
        $__b = DayBracket::compute($__evs, $__dayTgt);
        if ($__b !== null) {
            $__dayBrackets[$__off] = $__b;
        }
    }
}

// Abwesenheiten je Tag (ein Tag kann mehrere ueberlappende haben).
$__absByDay = [];
foreach (($absences ?? []) as $__a) {
    try {
        $__from = new DateTimeImmutable((string) $__a['start_date']);
        $__to   = new DateTimeImmutable((string) ($__a['end_date'] ?? $__a['start_date']));
    } catch (Throwable) { continue; }
    for ($__d = $__from; $__d <= $__to; $__d = $__d->modify('+1 day')) {
        $__ymd = $__d->format('Y-m-d');
        $__absByDay[$__ymd][] = [
            'id'       => (int) $__a['id'],
            'category' => (string) ($__a['category_label'] ?? ''),
            'account'  => (string) ($__a['category_account'] ?? ''),
            'hours'    => $__a['hours'] !== null ? (float) $__a['hours'] : null,
            'note'     => (string) ($__a['note'] ?? ''),
            'start'    => (string) $__a['start_date'],
            'end'      => (string) ($__a['end_date'] ?? $__a['start_date']),
            'cat_id'   => (int) $__a['category_id'],
        ];
    }
}

$__filterQp = array_filter($filter ?? [], static fn($v) => $v !== '' && $v !== 0 && $v !== null);
unset($__filterQp['from'], $__filterQp['to']);
$__weekQp = static function (DateTimeImmutable $m) use ($__filterQp): array {
    return $__filterQp + ['view' => 'calendar', 'week' => $m->format('o-\WW')];
};

$__earliestHour = $__minTop >= 99.0 ? 8 : max(0, (int) floor(($__minTop / 100.0) * 24));
?>
<?php
// Felix-Feedback 2026-11-06: helle Hintergrundfarben (gelb/orange) mit
// weissem Default-Text sind unlesbar. Luminanz pro Block berechnen und
// Textfarbe auf schwarz oder weiss setzen - WCAG-konforme relative
// Luminanz (approx. 0.299*R + 0.587*G + 0.114*B), Schwelle 150 passt fuer
// die typischen Block-Hintergruende (dunkles Blau -> weiss,
// Gelb/Orange/Hellgruen -> schwarz).
$__calInk = static function (string $hex): string {
    $h = ltrim(trim($hex), '#');
    if (strlen($h) === 3) { $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2]; }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $h)) { return '#ffffff'; }
    $r = hexdec(substr($h, 0, 2));
    $g = hexdec(substr($h, 2, 2));
    $b = hexdec(substr($h, 4, 2));
    $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
    return $lum > 150 ? '#1f2937' : '#ffffff';
};
?>
<style nonce="<?= h(csp_nonce()) ?>">
.cal-week { --cal-scroll: <?= $__earliestHour * 56 ?>px; }
<?php foreach ($__perDay as $__dayOffset => $__events): foreach ($__events as $__ev):
    $__ink = $__ev['color'] !== '' ? $__calInk((string) $__ev['color']) : '';
?>
.cal-event-<?= (int) $__ev['id'] ?>{top:<?= $__ev['top'] ?>%;height:<?= $__ev['height'] ?>%;<?= $__ev['color'] !== '' ? 'background-color:' . $__ev['color'] . ';color:' . $__ink . ';' : '' ?>}
<?php endforeach; endforeach; ?>
<?php foreach ($__procPerDay as $__dayOffset => $__events): foreach ($__events as $__ev): ?>
.cal-process-<?= (int) $__ev['id'] ?>{top:<?= $__ev['top'] ?>%;height:<?= $__ev['height'] ?>%}
<?php endforeach; endforeach; ?>
<?php /* Felix-Feedback 2026-11-08 (#99): Tagessoll-Klammer pro Day-Column.
   Top/Height in Prozent eines 24-h-Tags (konsistent zu den Events). */ ?>
<?php foreach ($__dayBrackets as $__off => $__br):
    $__tFrom = $__br['target']['from']; $__tTo = $__br['target']['to'];
    $__tTopPct = round($__tFrom / (24 * 60) * 100, 3);
    $__tHgtPct = round(($__tTo - $__tFrom) / (24 * 60) * 100, 3);
?>
.cal-day-bracket-target-<?= (int) $__off ?>{top:<?= $__tTopPct ?>%;height:<?= $__tHgtPct ?>%}
<?php if (!empty($__br['over'])):
    $__oFrom = $__br['over']['from']; $__oTo = $__br['over']['to'];
    $__oTopPct = round($__oFrom / (24 * 60) * 100, 3);
    $__oHgtPct = round(($__oTo - $__oFrom) / (24 * 60) * 100, 3);
?>
.cal-day-bracket-over-<?= (int) $__off ?>{top:<?= $__oTopPct ?>%;height:<?= $__oHgtPct ?>%}
<?php endif; endforeach; ?>
<?php /* Felix-Feedback 2026-11-09 (#138): Jetzt-Linie, Position in Prozent
   des 24h-Tages, nonce-authorized (CSP-strict). */ ?>
<?php if ($__nowPct !== null): ?>
.cal-now-line{top:<?= $__nowPct ?>%}
<?php endif; ?>
</style>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= h(sprintf(t('cal.week_heading', 'Woche %s'), $week_start->format('W / o'))) ?></h2>
      <p class="muted"><?= h($week_start->format('d.m.')) ?> – <?= h($__weekEnd->format('d.m.Y')) ?></p>
    </div>
    <div class="cal-nav">
      <?php /* Toggle Woche|Monat (Pro-Paritaet, Felix-Feedback 2026-11-04). */ ?>
      <span class="cal-span-toggle" role="group" aria-label="<?= h(t('cal.span_toggle', 'Ansicht')) ?>">
        <a class="btn btn-sm is-active" aria-current="page" href="<?= h(route('entries', $__filterQp + ['view' => 'calendar'])) ?>"><?= h(t('cal.span_week', 'Woche')) ?></a>
        <a class="btn btn-sm" href="<?= h(route('entries', $__filterQp + ['view' => 'calendar', 'span' => 'month'])) ?>"><?= h(t('cal.span_month', 'Monat')) ?></a>
      </span>
      <?php /* Felix-Feedback 2026-11-09 (#111): Rhythmus-Umschalter ist aus
         der Kalender-Oberflaeche ausgezogen - er liegt jetzt in den
         Benutzereinstellungen (?r=profile). Hier bleibt nur die Woche/Monat-
         Navigation. */ ?>
      <a class="btn btn-sm" href="<?= h(route('entries', $__weekQp($__prevWeek))) ?>"><?= h(t('cal.prev_week', '← Vorige Woche')) ?></a>
      <a class="btn btn-sm" href="<?= h(route('entries', $__filterQp + ['view' => 'calendar'])) ?>"><?= h(t('dash.today')) ?></a>
      <a class="btn btn-sm" href="<?= h(route('entries', $__weekQp($__nextWeek))) ?>"><?= h(t('cal.next_week', 'Naechste Woche →')) ?></a>
    </div>
  </div>

  <?php if (!empty($canRecordSentiment) && ($sentimentGranularity ?: 'week') === 'week'): ?>
    <?php /* Wochen-Picker (Pro-Style, Felix-Feedback 2026-11-04: "schau's
       dir in der pro variante an"): je Dimension nur ein Current-Emoji-
       Button, Klick oeffnet Popover mit 5 Optionen - identisch zum
       Daily-Picker, nur dass der Click alle leeren Tage der Woche fuellt.
       Prosa "Setzt die Woche …" sitzt als Tooltip am ⓘ-Badge. */ ?>
    <?php /* Default-Placeholder: das NEUTRAL-Emoji (Score 3), abgegraut durch
       is-empty. Pro-Vorbild (Felix-Feedback 2026-11-04: "in der pro ist ein
       ausgegrautes emojii.") - "—" war zu nichtssagend. */ ?>
    <div class="cal-sent-week" data-monday="<?= h($week_start->format('Y-m-d')) ?>" data-csrf="<?= h(csrf_token()) ?>">
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
      <?= tip('cal.sent_week_hint') ?>
    </div>
  <?php endif; ?>

  <div class="cal-week">
    <?php /* Felix-Feedback 2026-11-08 (#109): durchgehende Kopfzeile ueber
       Uhrzeiten UND Tagespalten, mit Corner-Zelle oben links. Der
       bisherige Pro-Tag-Head (cal-day-head in cal-day) war sticky, aber
       keine echte durchgehende Zeile. Kopfzeile ist ein eigener Grid-Row
       innerhalb von .cal-week und tritt horizontal mit den Day-Columns
       in den gleichen Raster. */ ?>
    <div class="cal-week-head" role="row">
      <div class="cal-week-corner" aria-hidden="true"></div>
      <?php foreach ($__days as $__i => $__day):
        $__hd = $holidays[$__day['ymd']] ?? null;
        $__hdCls = 'cal-week-dayhead cal-day-head';
        if ($__day['isToday'])  $__hdCls .= ' is-today';
        if ($__hd)              $__hdCls .= ' is-holiday';
        if (!$__day['isWorkDay']) $__hdCls .= ' is-nonworking';
      ?>
        <div class="<?= h($__hdCls) ?>" role="columnheader" data-day="<?= h($__day['ymd']) ?>">
          <div class="cal-week-dayhead-label"><?= h($__day['label']) ?></div>
          <?php /* Felix-Feedback 2026-11-09 (#118/#119): Pensum-Badge pro Tag
             in der Kopfzeile - gruen unter Soll, gelb genau drauf, rot
             ueber Soll. Freelancer (Tagessoll=null) + Wochenenden /
             Feiertage (Tagessoll=0) bekommen kein Badge. */ ?>
          <?php $__dayTgt = $__dayTargetByOffset[$__i] ?? null; ?>
          <?php if ($__dayTgt !== null && $__dayTgt > 0):
            $__sum = isset($__dayBrackets[$__i]) ? (int) $__dayBrackets[$__i]['sum'] : 0;
            $__diff = $__sum - $__dayTgt;
            $__toleranceMin = 5;
            if (abs($__diff) <= $__toleranceMin)      $__pensumCls = 'is-ok';
            elseif ($__diff < 0)                      $__pensumCls = 'is-under';
            else                                      $__pensumCls = 'is-over';
            $__fmtH = static fn(int $m): string => number_format($m / 60, 1, ',', '.');
          ?>
            <div class="cal-day-pensum <?= $__pensumCls ?>"
                 title="<?= h(t('cal.day_pensum_tip', 'Gebuchte Stunden heute / Tagessoll')) ?>">
              <?= h($__fmtH($__sum)) ?> / <?= h($__fmtH($__dayTgt)) ?> h
            </div>
          <?php endif; ?>
          <?php /* Felix-Feedback 2026-11-09 (#110): Daily-Mood-Picker sitzt
             in der STICKY Kopfzeile, nicht in der Uhrzeiten-Tabelle - sonst
             scrollt er mit dem Auto-Scroll zur ersten Buchungsstunde aus
             dem sichtbaren Bereich. */ ?>
          <?php if (!empty($canRecordSentiment) && ($sentimentGranularity ?? 'week') === 'day'):
            $__dayMood = $weekSentiment[$__day['ymd']]['mood'] ?? null;
            $__dayFlow = $weekSentiment[$__day['ymd']]['flow'] ?? null;
            $__isFuture = $__day['date'] > $__today;
            $__moodCur = Sentiment::moodEmoji($__dayMood !== null ? $__dayMood : 3);
            $__flowCur = Sentiment::flowEmoji($__dayFlow !== null ? $__dayFlow : 3);
          ?>
            <div class="cal-sent-day cal-sent-day-head<?= $__isFuture ? ' is-future' : '' ?>" data-day="<?= h($__day['ymd']) ?>" data-csrf="<?= h(csrf_token()) ?>" <?= $__isFuture ? 'title="' . h(t('cal.sent_future', 'Zukunft')) . '"' : '' ?>>
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
      <?php endforeach; ?>
    </div>
    <div class="cal-hours" aria-hidden="true">
      <?php for ($h = 0; $h < 24; $h++): ?>
        <div class="cal-hour-label"><?= sprintf('%02d:00', $h) ?></div>
      <?php endfor; ?>
    </div>
    <div class="cal-days">
      <?php foreach ($__days as $__i => $__day): ?>
        <?php $__holiday = $holidays[$__day['ymd']] ?? null; ?>
        <div class="cal-day<?= $__day['isToday'] ? ' cal-day-today' : '' ?><?= $__holiday ? ' cal-day-holiday' : '' ?><?= !$__day['isWorkDay'] ? ' cal-day-nonworking' : '' ?>" data-day="<?= h($__day['ymd']) ?>">
          <?php /* Felix-Feedback 2026-11-09 (#110): Daily-Mood-Picker ist in
             die sticky Kopfzeile oben gezogen; die cal-day-Column enthaelt
             ihn nicht mehr. */ ?>
          <?php if ($__holiday): ?>
            <div class="cal-day-holiday-badge" title="<?= h(t($__holiday['key'], $__holiday['key'])) ?>">
              <span class="cal-holiday-dot" aria-hidden="true">★</span>
              <span class="cal-holiday-name"><?= h(t($__holiday['key'], $__holiday['key'])) ?></span>
            </div>
          <?php endif; ?>
          <?php foreach ($__absByDay[$__day['ymd']] ?? [] as $__ab): ?>
            <button type="button" class="cal-day-absence"
                    data-absence-id="<?= (int) $__ab['id'] ?>"
                    data-cat-id="<?= (int) $__ab['cat_id'] ?>"
                    data-start="<?= h($__ab['start']) ?>"
                    data-end="<?= h($__ab['end']) ?>"
                    data-hours="<?= h($__ab['hours'] === null ? '' : (string) $__ab['hours']) ?>"
                    data-note="<?= h($__ab['note']) ?>"
                    title="<?= h($__ab['category'] . ($__ab['note'] !== '' ? ' · ' . $__ab['note'] : '')) ?>">
              <?= h($__ab['category']) ?>
            </button>
          <?php endforeach; ?>
          <div class="cal-day-grid" data-day="<?= h($__day['ymd']) ?>">
            <?php for ($h = 1; $h < 24; $h++): ?>
              <div class="cal-day-line"></div>
            <?php endfor; ?>
            <?php /* Felix-Feedback 2026-11-09 (#138): rote Jetzt-Linie nur im
               heutigen Tag, positioniert per nonce-Style oben im Block. */ ?>
            <?php if ($__day['isToday'] && $__nowPct !== null): ?>
              <div class="cal-now-line" aria-hidden="true" title="<?= h(t('cal.now_line', 'Jetzt')) ?>"></div>
            <?php endif; ?>
            <?php /* Felix-Feedback 2026-11-08 (#99): Tagessoll-Klammer rechts
               neben den Events. Zwei moegliche Klammern: Target (Standard)
               und Over (gestrichelt), sitzen am rechten Rand der Day-Column. */ ?>
            <?php if (isset($__dayBrackets[$__i])):
              $__b = $__dayBrackets[$__i];
              $__tHours = round(($__b['target']['to'] - $__b['target']['from']) / 60, 1);
              $__oHours = $__b['over'] ? round(($__b['over']['to'] - $__b['over']['from']) / 60, 1) : 0;
            ?>
              <div class="cal-day-bracket cal-day-bracket-target cal-day-bracket-target-<?= (int) $__i ?>"
                   aria-hidden="true"
                   title="<?= h(t('cal.day_bracket_target', 'Tagessoll')) ?>: <?= $__tHours ?> h"></div>
              <?php if ($__b['over']): ?>
                <div class="cal-day-bracket cal-day-bracket-over cal-day-bracket-over-<?= (int) $__i ?>"
                     aria-hidden="true"
                     title="<?= h(t('cal.day_bracket_over', 'Ueberstunden')) ?>: +<?= $__oHours ?> h"></div>
              <?php endif; ?>
            <?php endif; ?>
            <?php foreach ($__perDay[$__i] ?? [] as $__ev): ?>
              <button type="button" class="cal-event cal-event-<?= (int) $__ev['id'] ?><?= $__ev['running'] ? ' cal-event-running' : '' ?>"
                 data-entry-type="project"
                 data-entry-id="<?= (int) $__ev['id'] ?>"
                 title="<?= h($__ev['client'] . ' / ' . $__ev['project'] . ' · ' . $__ev['task']) ?>&#10;<?= h($__ev['start']) ?> – <?= h($__ev['end']) ?><?= $__ev['note'] !== '' ? '&#10;' . h($__ev['note']) : '' ?>">
                <span class="cal-event-time"><?= h($__ev['start']) ?></span>
                <span class="cal-event-label"><?= h($__ev['client']) ?><?= $__ev['project'] !== '' ? ' / ' . h($__ev['project']) : '' ?></span>
              </button>
            <?php endforeach; ?>
            <?php foreach ($__procPerDay[$__i] ?? [] as $__ev): ?>
              <button type="button" class="cal-event cal-process cal-process-<?= (int) $__ev['id'] ?>"
                 data-entry-type="process"
                 data-entry-id="<?= (int) $__ev['id'] ?>"
                 title="<?= h($__ev['label'] . ($__ev['note'] !== '' ? ' · ' . $__ev['note'] : '')) ?>&#10;<?= h($__ev['start']) ?> – <?= h($__ev['end']) ?>">
                <span class="cal-event-time"><?= h($__ev['start']) ?></span>
                <span class="cal-event-label"><?= h($__ev['label']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Dialog + JS werden zentral in views/entries/index.php eingebunden, damit
     sowohl Kalender- als auch Liste-View den gleichen Create/Edit-Flow bekommen. -->

<?php
  $__holidayRegion  = Holidays::currentRegion();
  $__holidayCatalog = Holidays::catalog();
  $__canEditHoliday = Auth::can('admin.system');
?>
<div class="card external-cal-teaser">
  <div class="card-head">
    <h2><?= h(t('cal.external_title', 'Externe Kalender')) ?><?= tip('cal.external_intro_tip') ?></h2>
  </div>

  <div class="calimport-item">
    <div class="calimport-item-head">
      <strong><?= h(t('calimport.holiday_h2', 'Feiertagskalender')) ?></strong>
      <?= tip('calimport.holiday_intro') ?>
    </div>
    <form method="post" action="<?= h(route('holiday_region_save')) ?>" class="calimport-form">
      <?= csrf_field() ?>
      <input type="hidden" name="_back" value="<?= h(route('entries') . '&week=' . $week_start->format('Y-\WW')) ?>">
      <label class="field">
        <span class="field-label"><?= h(t('calimport.region_label', 'Region')) ?></span>
        <select name="holiday_region" <?= $__canEditHoliday ? '' : 'disabled' ?>>
          <option value="__none__" <?= $__holidayRegion === '__none__' ? 'selected' : '' ?>>
            <?= h(t('calimport.region_none', '— Keine Feiertagsmarkierung —')) ?>
          </option>
          <?php foreach ($__holidayCatalog as $__grp): ?>
            <optgroup label="<?= h(t($__grp['label_key'], strtoupper($__grp['country']))) ?>">
              <?php foreach ($__grp['options'] as $__opt): ?>
                <option value="<?= h($__opt['code']) ?>" <?= $__holidayRegion === $__opt['code'] ? 'selected' : '' ?>>
                  <?= h(t($__opt['label_key'], $__opt['code'])) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </label>
      <?php if ($__canEditHoliday): ?>
        <div class="form-actions">
          <button class="btn btn-primary btn-sm" type="submit"><?= h(t('common.save', 'Speichern')) ?></button>
        </div>
      <?php else: ?>
        <p class="muted"><?= h(t('calimport.readonly_hint', 'Nur ein Administrator kann den Feiertagskalender aendern.')) ?></p>
      <?php endif; ?>
    </form>
  </div>

  <div class="external-cal-items">
    <a class="external-cal-item" href="<?= h($pro_url) ?>" target="_blank" rel="noopener">
      <strong><?= h(t('cal.ext_multi_title', 'Mehrere Feiertagskalender parallel')) ?></strong>
      <span class="muted"><?= h(t('cal.ext_multi_hint', 'DE-BY + AT + US-FED gleichzeitig anzeigen; einer zaehlt fuer die 40h-Berechnung.')) ?></span>
      <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span>
    </a>
    <a class="external-cal-item" href="<?= h($pro_url) ?>" target="_blank" rel="noopener">
      <strong><?= h(t('cal.ext_caldav_title', 'CalDAV-Kalender hinzufuegen')) ?></strong>
      <span class="muted"><?= h(t('cal.ext_caldav_hint', 'iCloud, Google (CalDAV), FastMail, Zimbra …')) ?></span>
      <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span>
    </a>
    <a class="external-cal-item" href="<?= h($pro_url) ?>" target="_blank" rel="noopener">
      <strong><?= h(t('cal.ext_ics_title', 'Kalender-Abo (iCal/Outlook-URL)')) ?></strong>
      <span class="muted"><?= t('cal.ext_ics_hint', 'z.&nbsp;B. Teamkalender als <code>.ics</code>-Abo') /* contains <code>, intentional */ ?></span>
      <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span>
    </a>
    <a class="external-cal-item" href="<?= h($pro_url) ?>" target="_blank" rel="noopener">
      <strong><?= h(t('cal.ext_book_title', 'Zeitbuchung direkt aus dem Kalender')) ?></strong>
      <span class="muted"><?= h(t('cal.ext_book_hint', 'Termin anklicken, Projekt zuordnen, Buchung fertig.')) ?></span>
      <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span>
    </a>
  </div>
</div>


<?php /* Sentiment-Picker JS in gemeinsames Partial ausgelagert, damit
   Monatsansicht die gleiche Mechanik bekommt (Felix-Feedback 2026-11-08). */ ?>
<?php require __DIR__ . '/_sentiment_picker_js.php'; ?>
