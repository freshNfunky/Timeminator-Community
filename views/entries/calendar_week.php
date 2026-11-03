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
<style nonce="<?= h(csp_nonce()) ?>">
.cal-week { --cal-scroll: <?= $__earliestHour * 56 ?>px; }
<?php foreach ($__perDay as $__dayOffset => $__events): foreach ($__events as $__ev): ?>
.cal-event-<?= (int) $__ev['id'] ?>{top:<?= $__ev['top'] ?>%;height:<?= $__ev['height'] ?>%;<?= $__ev['color'] !== '' ? 'background-color:' . $__ev['color'] . ';' : '' ?>}
<?php endforeach; endforeach; ?>
<?php foreach ($__procPerDay as $__dayOffset => $__events): foreach ($__events as $__ev): ?>
.cal-process-<?= (int) $__ev['id'] ?>{top:<?= $__ev['top'] ?>%;height:<?= $__ev['height'] ?>%}
<?php endforeach; endforeach; ?>
</style>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= h(sprintf(t('cal.week_heading', 'Woche %s'), $week_start->format('W / o'))) ?></h2>
      <p class="muted"><?= h($week_start->format('d.m.')) ?> – <?= h($__weekEnd->format('d.m.Y')) ?></p>
    </div>
    <div class="cal-nav">
      <?php if (!empty($canAdminSentiment)): ?>
        <?php /* Granularitaets-Toggle (Felix-Feedback 2026-11-04): Admin-only,
           Default = woechentlich (Felix-Feedback "default habe ich gesagt ist
           nicht die tägliche meldung. die bleibt im hintergrund."). */ ?>
        <?php $__g = $sentimentGranularity ?: 'week'; ?>
        <span class="cal-sent-toggle" data-current="<?= h($__g) ?>" data-csrf="<?= h(csrf_token()) ?>" role="group" aria-label="<?= h(t('sent.granularity', 'Stimmungs-Rhythmus')) ?>">
          <button type="button" class="btn btn-sm<?= $__g === 'week' ? ' is-active' : '' ?>" data-g="week"><?= h(t('sent.granularity_week', 'Woechentlich')) ?></button>
          <button type="button" class="btn btn-sm<?= $__g === 'day'  ? ' is-active' : '' ?>" data-g="day"><?= h(t('sent.granularity_day',  'Taeglich')) ?></button>
        </span>
      <?php endif; ?>
      <a class="btn btn-sm" href="<?= h(route('entries', $__weekQp($__prevWeek))) ?>"><?= h(t('cal.prev_week', '← Vorige Woche')) ?></a>
      <a class="btn btn-sm" href="<?= h(route('entries', $__filterQp + ['view' => 'calendar'])) ?>"><?= h(t('dash.today')) ?></a>
      <a class="btn btn-sm" href="<?= h(route('entries', $__weekQp($__nextWeek))) ?>"><?= h(t('cal.next_week', 'Naechste Woche →')) ?></a>
    </div>
  </div>

  <?php if (!empty($canRecordSentiment) && ($sentimentGranularity ?: 'week') === 'week'): ?>
    <?php /* Wochen-Picker (eine Zeile fuer die ganze Woche; schreibt alle 7
       Tage mit Sentiment::fillRange - respektiert bereits gesetzte Tage). */ ?>
    <div class="cal-sent-week" data-monday="<?= h($week_start->format('Y-m-d')) ?>" data-csrf="<?= h(csrf_token()) ?>">
      <div class="cal-sent-week-row" data-dim="mood">
        <span class="cal-sent-label"><?= h(t('sent.mood_short', 'Mood')) ?></span>
        <?php foreach ([1,2,3,4,5] as $__s): ?>
          <button type="button" class="cal-sent-btn" data-score="<?= $__s ?>" title="<?= h(Sentiment::moodLabel($__s)) ?>"><?= Sentiment::moodEmoji($__s) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="cal-sent-week-row" data-dim="flow">
        <span class="cal-sent-label"><?= h(t('sent.flow_short', 'Flow')) ?></span>
        <?php foreach ([1,2,3,4,5] as $__s): ?>
          <button type="button" class="cal-sent-btn" data-score="<?= $__s ?>" title="<?= h(Sentiment::flowLabel($__s)) ?>"><?= Sentiment::flowEmoji($__s) ?></button>
        <?php endforeach; ?>
      </div>
      <p class="muted cal-sent-hint"><?= h(t('cal.sent_week_hint', 'Setzt die Woche; bereits gesetzte Tage bleiben erhalten.')) ?></p>
    </div>
  <?php endif; ?>

  <div class="cal-week">
    <div class="cal-hours" aria-hidden="true">
      <?php for ($h = 0; $h < 24; $h++): ?>
        <div class="cal-hour-label"><?= sprintf('%02d:00', $h) ?></div>
      <?php endfor; ?>
    </div>
    <div class="cal-days">
      <?php foreach ($__days as $__i => $__day): ?>
        <?php $__holiday = $holidays[$__day['ymd']] ?? null; ?>
        <div class="cal-day<?= $__day['isToday'] ? ' cal-day-today' : '' ?><?= $__holiday ? ' cal-day-holiday' : '' ?><?= !$__day['isWorkDay'] ? ' cal-day-nonworking' : '' ?>" data-day="<?= h($__day['ymd']) ?>">
          <div class="cal-day-head"><?= h($__day['label']) ?></div>
          <?php if (!empty($canRecordSentiment) && ($sentimentGranularity ?? 'week') === 'day'):
            $__dayMood = $weekSentiment[$__day['ymd']]['mood'] ?? null;
            $__dayFlow = $weekSentiment[$__day['ymd']]['flow'] ?? null;
            $__isFuture = $__day['date'] > $__today;
            // Default-Emojis, wenn noch nichts gesetzt: neutrales "—" als Placeholder.
            $__moodCur = $__dayMood !== null ? Sentiment::moodEmoji($__dayMood) : '—';
            $__flowCur = $__dayFlow !== null ? Sentiment::flowEmoji($__dayFlow) : '—';
          ?>
            <div class="cal-sent-day<?= $__isFuture ? ' is-future' : '' ?>" data-day="<?= h($__day['ymd']) ?>" data-csrf="<?= h(csrf_token()) ?>" <?= $__isFuture ? 'title="' . h(t('cal.sent_future', 'Zukunft')) . '"' : '' ?>>
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
    <h2><?= h(t('cal.external_title', 'Externe Kalender')) ?></h2>
  </div>

  <div class="calimport-item">
    <div class="calimport-item-head">
      <strong><?= h(t('calimport.holiday_h2', 'Feiertagskalender')) ?></strong>
    </div>
    <p class="muted"><?= h(t('calimport.holiday_intro', 'Genau ein gesetzlicher Feiertagskalender aus einer festen Liste. Liefert die Markierungen im Day-Head oben und ist Grundlage fuer die Soll-Reduktion in v1.1.')) ?></p>
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

  <p class="muted calimport-pro-intro"><?= t('cal.external_intro', 'Externe Quellen laufen in <strong>Timeminator Pro</strong>: CalDAV-Konten (iCloud, Google via CalDAV, FastMail&nbsp;&hellip;) und Kalender-Abos per iCal-/Outlook-URL. Read-only Overlays im Grid, mehrere parallel.') /* contains <strong>, intentional */ ?></p>
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


<?php /* Sentiment-Picker JS: per-day und per-week Save via JSON-API, plus
   Granularitaets-Toggle (admin). CSP-strict: nonce + keine inline styles. */ ?>
<?php if (!empty($canRecordSentiment)): ?>
<script nonce="<?= h(csp_nonce()) ?>">
(function () {
  var daySaveUrl = <?= json_encode(route('sentiment_api_save'))      ?>;
  var weekSaveUrl= <?= json_encode(route('sentiment_api_fill_week')) ?>;
  var granUrl    = <?= json_encode(route('sentiment_granularity_api'))?>;

  function postForm(url, body) {
    return fetch(url, {method: 'POST', body: body, credentials: 'same-origin'})
      .then(function (r) { return r.json().catch(function(){ return {ok:false}; }); });
  }

  // === Daily picker: Pro-Style. Current-Button oeffnet ein Popover mit 5
  // horizontalen Optionen; Klick auf eine Option speichert, schliesst, und
  // ersetzt das angezeigte Emoji. Re-Klick auf die aktuell gewaehlte Option
  // leert den Wert.
  function closeOpenPopover(except) {
    document.querySelectorAll('.cal-sent-popover').forEach(function (p) {
      if (except && p === except) return;
      p.hidden = true;
    });
    document.querySelectorAll('.cal-sent-current.is-open').forEach(function (b) {
      b.classList.remove('is-open');
    });
  }
  // Emoji-Lookup synchron zu Sentiment::moodEmoji / flowEmoji.
  var EMOJI = {
    mood: {1:'\u{1F61E}',2:'\u{1F615}',3:'\u{1F610}',4:'\u{1F642}',5:'\u{1F604}'},
    flow: {1:'\u{1F6D1}',2:'\u{1F422}',3:'\u{1F610}',4:'\u{1F3C3}',5:'\u{1F680}'}
  };
  document.querySelectorAll('.cal-sent-day').forEach(function (cell) {
    var day  = cell.getAttribute('data-day');
    var csrf = cell.getAttribute('data-csrf');
    cell.querySelectorAll('.cal-sent-cell').forEach(function (dimCell) {
      var dim  = dimCell.getAttribute('data-dim');
      var cur  = dimCell.querySelector('.cal-sent-current');
      var pop  = dimCell.querySelector('.cal-sent-popover');
      if (!cur || !pop) return;
      cur.addEventListener('click', function (ev) {
        ev.stopPropagation();
        if (cur.disabled) return;
        var open = !pop.hidden;
        closeOpenPopover();
        if (!open) { pop.hidden = false; cur.classList.add('is-open'); }
      });
      pop.querySelectorAll('.cal-sent-opt').forEach(function (opt) {
        opt.addEventListener('click', function (ev) {
          ev.stopPropagation();
          var score = opt.getAttribute('data-score');
          var current = dimCell.getAttribute('data-current');
          var isClear = String(current) === String(score);
          var body = new URLSearchParams();
          body.set('_csrf', csrf);
          body.set('day', day);
          body.set('dim', dim);
          body.set('score', isClear ? '' : score);
          pop.querySelectorAll('.cal-sent-opt').forEach(function (b) { b.disabled = true; });
          postForm(daySaveUrl, body).then(function (res) {
            pop.querySelectorAll('.cal-sent-opt').forEach(function (b) { b.disabled = false; });
            if (!res.ok) return;
            var next = isClear ? '' : score;
            dimCell.setAttribute('data-current', next);
            pop.querySelectorAll('.cal-sent-opt').forEach(function (b) {
              var match = String(b.getAttribute('data-score')) === String(next);
              b.classList.toggle('is-selected', match);
              b.setAttribute('aria-selected', match ? 'true' : 'false');
            });
            // Current-Emoji aktualisieren: Wert = "—" wenn leer.
            cur.textContent = next ? (EMOJI[dim][next] || '—') : '—';
            cur.classList.toggle('is-empty', !next);
            closeOpenPopover();
          });
        });
      });
    });
  });
  // Klick ausserhalb schliesst offenes Popover.
  document.addEventListener('click', function () { closeOpenPopover(); });

  // === Weekly picker (only visible when granularity = week): bulk-fill the week.
  document.querySelectorAll('.cal-sent-week').forEach(function (block) {
    var monday = block.getAttribute('data-monday');
    var csrf   = block.getAttribute('data-csrf');
    block.querySelectorAll('.cal-sent-week-row').forEach(function (row) {
      var dim = row.getAttribute('data-dim');
      row.querySelectorAll('.cal-sent-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var score = btn.getAttribute('data-score');
          var body = new URLSearchParams();
          body.set('_csrf', csrf);
          body.set('monday', monday);
          body.set('dim', dim);
          body.set('score', score);
          row.querySelectorAll('.cal-sent-btn').forEach(function (b) { b.disabled = true; });
          postForm(weekSaveUrl, body).then(function (res) {
            row.querySelectorAll('.cal-sent-btn').forEach(function (b) { b.disabled = false; });
            if (!res.ok) return;
            // Visual confirmation: pulse the clicked button briefly.
            btn.classList.add('is-selected');
            setTimeout(function () { btn.classList.remove('is-selected'); }, 900);
          });
        });
      });
    });
  });

  // === Granularity toggle (admin only): switch day<->week globally.
  document.querySelectorAll('.cal-sent-toggle').forEach(function (grp) {
    var csrf = grp.getAttribute('data-csrf');
    grp.querySelectorAll('button[data-g]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var g = btn.getAttribute('data-g');
        var body = new URLSearchParams();
        body.set('_csrf', csrf);
        body.set('granularity', g);
        postForm(granUrl, body).then(function (res) {
          if (!res.ok) return;
          // Reload so the calendar renders the matching picker layout.
          window.location.reload();
        });
      });
    });
  });
})();
</script>
<?php endif; ?>
