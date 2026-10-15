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
 * @var array             $holidays_upcoming  Flat list from Holidays::between()
 */
$holidays          = $holidays          ?? [];
$holidays_upcoming = $holidays_upcoming ?? [];
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
for ($i = 0; $i < 7; $i++) {
    $d = $week_start->modify('+' . $i . ' days');
    $__days[] = [
        'date'    => $d,
        'ymd'     => $d->format('Y-m-d'),
        'label'   => $__dayHeaders[$i] . ' ' . $d->format('d.m.'),
        'isToday' => $d->format('Y-m-d') === $__today->format('Y-m-d'),
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
</style>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= h(sprintf(t('cal.week_heading', 'Woche %s'), $week_start->format('W / o'))) ?></h2>
      <p class="muted"><?= h($week_start->format('d.m.')) ?> – <?= h($__weekEnd->format('d.m.Y')) ?></p>
    </div>
    <div class="cal-nav">
      <a class="btn btn-sm" href="<?= h(route('entries', $__weekQp($__prevWeek))) ?>"><?= h(t('cal.prev_week', '← Vorige Woche')) ?></a>
      <a class="btn btn-sm" href="<?= h(route('entries', $__filterQp + ['view' => 'calendar'])) ?>"><?= h(t('dash.today')) ?></a>
      <a class="btn btn-sm" href="<?= h(route('entries', $__weekQp($__nextWeek))) ?>"><?= h(t('cal.next_week', 'Naechste Woche →')) ?></a>
    </div>
  </div>

  <div class="cal-week">
    <div class="cal-hours" aria-hidden="true">
      <?php for ($h = 0; $h < 24; $h++): ?>
        <div class="cal-hour-label"><?= sprintf('%02d:00', $h) ?></div>
      <?php endfor; ?>
    </div>
    <div class="cal-days">
      <?php foreach ($__days as $__i => $__day): ?>
        <?php $__holiday = $holidays[$__day['ymd']] ?? null; ?>
        <div class="cal-day<?= $__day['isToday'] ? ' cal-day-today' : '' ?><?= $__holiday ? ' cal-day-holiday' : '' ?>" data-day="<?= h($__day['ymd']) ?>">
          <div class="cal-day-head"><?= h($__day['label']) ?></div>
          <?php if ($__holiday): ?>
            <div class="cal-day-holiday-badge" title="<?= h(t($__holiday['key'], $__holiday['key']) . ' — ' . $__holiday['scope']) ?>">
              <span class="cal-holiday-dot" aria-hidden="true">★</span>
              <span class="cal-holiday-name"><?= h(t($__holiday['key'], $__holiday['key'])) ?></span>
            </div>
          <?php endif; ?>
          <div class="cal-day-grid" data-day="<?= h($__day['ymd']) ?>">
            <?php for ($h = 1; $h < 24; $h++): ?>
              <div class="cal-day-line"></div>
            <?php endfor; ?>
            <?php foreach ($__perDay[$__i] ?? [] as $__ev): ?>
              <a class="cal-event cal-event-<?= (int) $__ev['id'] ?><?= $__ev['running'] ? ' cal-event-running' : '' ?>"
                 href="<?= h(route('entry_form', ['id' => $__ev['id']])) ?>"
                 title="<?= h($__ev['client'] . ' / ' . $__ev['project'] . ' · ' . $__ev['task']) ?>&#10;<?= h($__ev['start']) ?> – <?= h($__ev['end']) ?><?= $__ev['note'] !== '' ? '&#10;' . h($__ev['note']) : '' ?>">
                <span class="cal-event-time"><?= h($__ev['start']) ?></span>
                <span class="cal-event-label"><?= h($__ev['client']) ?><?= $__ev['project'] !== '' ? ' / ' . h($__ev['project']) : '' ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card holidays-card">
  <div class="card-head">
    <h2><?= h(t('cal.holidays_h2', 'Feiertage')) ?></h2>
    <span class="muted"><?= h(sprintf(t('cal.holidays_subtitle', 'DACH / US — aus der eingebauten Liste. Locale: %s'), strtoupper(I18n::current()))) ?></span>
  </div>
  <?php if (!$holidays_upcoming): ?>
    <p class="muted"><?= h(t('cal.holidays_empty', 'In den naechsten 90 Tagen keine Feiertage in der aktuellen Locale.')) ?></p>
  <?php else: ?>
    <ul class="holidays-list">
      <?php
        $__today = (new DateTimeImmutable('today'))->format('Y-m-d');
        foreach ($holidays_upcoming as $__h):
          $__dt   = new DateTimeImmutable($__h['date']);
          $__past = strcmp($__h['date'], $__today) < 0;
          $__name = t($__h['key'], $__h['key']);
      ?>
        <li class="holidays-list-item<?= $__past ? ' is-past' : '' ?>">
          <span class="holidays-list-date"><?= h($__dt->format('d.m.Y')) ?></span>
          <span class="holidays-list-dow muted"><?= h($__dt->format('D')) ?></span>
          <span class="holidays-list-name"><?= h($__name) ?></span>
          <span class="holidays-list-scope muted"><?= h($__h['scope']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="muted holidays-list-foot"><?= h(t('cal.holidays_hint', 'Als dezente Marker im Day-Head oben. Externe Kalender/CalDAV → Pro.')) ?></p>
  <?php endif; ?>
</div>

<div class="card external-cal-teaser">
  <div class="card-head">
    <h2><?= h(t('cal.external_title', 'Externe Kalender')) ?></h2>
    <span class="pill pill-pro"><?= h(t('nav.pro_feature_tooltip')) ?></span>
  </div>
  <p class="muted"><?= t('cal.external_intro', 'Externe Quellen laufen in <strong>Timeminator Pro</strong>: CalDAV-Konten (iCloud, Google via CalDAV, FastMail&nbsp;&hellip;) und Kalender-Abos per iCal-/Outlook-URL (z.&nbsp;B. Feiertags-Kalender). Read-only Overlays im Grid.') /* contains <strong>, intentional */ ?></p>
  <div class="external-cal-items">
    <a class="external-cal-item" href="<?= h($pro_url) ?>" target="_blank" rel="noopener">
      <strong><?= h(t('cal.ext_caldav_title', 'CalDAV-Kalender hinzufuegen')) ?></strong>
      <span class="muted"><?= h(t('cal.ext_caldav_hint', 'iCloud, Google (CalDAV), FastMail, Zimbra …')) ?></span>
      <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span>
    </a>
    <a class="external-cal-item" href="<?= h($pro_url) ?>" target="_blank" rel="noopener">
      <strong><?= h(t('cal.ext_ics_title', 'Kalender-Abo (iCal/Outlook-URL)')) ?></strong>
      <span class="muted"><?= t('cal.ext_ics_hint', 'z.&nbsp;B. Feiertags-URL als <code>.ics</code>-Abo') /* contains <code>, intentional */ ?></span>
      <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span>
    </a>
  </div>
</div>
