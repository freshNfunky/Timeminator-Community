<?php
/**
 * Calendar view teaser.
 *
 * The Community edition does not ship a working calendar — Timeminator Pro
 * does. This partial renders a dimmed static month-grid mockup behind an
 * explicit "Pro feature" overlay so the admin sees what they would get. All
 * styling is in `.calendar-teaser-*` classes in assets/app.css; the mockup
 * uses only deterministic data (today's month) and never reads real entries.
 *
 * @var string $pro_url
 */
$__today = new DateTimeImmutable('today');
$__first = $__today->modify('first day of this month');
$__dow   = (int) $__first->format('N'); // Mon=1..Sun=7
$__daysInMonth = (int) $__today->format('t');

// Precompute a 6x7 grid starting from the Monday before (or at) $__first.
$__gridStart = $__first->modify('-' . ($__dow - 1) . ' days');
$__cells = [];
for ($i = 0; $i < 42; $i++) {
    $d = $__gridStart->modify('+' . $i . ' days');
    $__cells[] = [
        'date'   => $d,
        'inMonth'=> $d->format('Y-m') === $__today->format('Y-m'),
        'isToday'=> $d->format('Y-m-d') === $__today->format('Y-m-d'),
    ];
}

// Deterministic "fake" booking spans — only there to hint at what Pro renders.
// They are visual decoration, not real data; nothing is read from Repo::entries.
$__mockEvents = [
    ['day' =>  2, 'label' => 'ACME Kickoff',    'colVar' => '--brand'],
    ['day' =>  5, 'label' => 'Deep work',       'colVar' => '--ok'],
    ['day' =>  9, 'label' => 'Review',          'colVar' => '--warn'],
    ['day' => 14, 'label' => 'Design sprint',   'colVar' => '--brand'],
    ['day' => 15, 'label' => 'Design sprint',   'colVar' => '--brand'],
    ['day' => 18, 'label' => 'Client call',     'colVar' => '--warn'],
    ['day' => 22, 'label' => 'Backfill',        'colVar' => '--ok'],
    ['day' => 25, 'label' => 'Reporting',       'colVar' => '--brand'],
];
$__eventsByDay = [];
foreach ($__mockEvents as $e) {
    $__eventsByDay[$e['day']][] = $e;
}
?>
<div class="card calendar-teaser-card">
  <div class="card-head">
    <h2><?= h($__today->format('F Y')) ?></h2>
    <span class="pill pill-pro">Pro-Feature</span>
  </div>

  <div class="calendar-teaser-wrap">
    <!-- The dimmed mockup grid. Deliberately NOT interactive. -->
    <div class="calendar-grid" aria-hidden="true">
      <div class="calendar-head">
        <?php foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $__dName): ?>
          <div class="calendar-dow"><?= $__dName ?></div>
        <?php endforeach; ?>
      </div>
      <div class="calendar-body">
        <?php foreach ($__cells as $__c): ?>
          <?php
            $__classes = ['calendar-cell'];
            if (!$__c['inMonth']) $__classes[] = 'calendar-cell-out';
            if ($__c['isToday'])  $__classes[] = 'calendar-cell-today';
            $__dayNum = (int) $__c['date']->format('j');
          ?>
          <div class="<?= h(implode(' ', $__classes)) ?>">
            <div class="calendar-cell-num"><?= $__dayNum ?></div>
            <?php if ($__c['inMonth'] && !empty($__eventsByDay[$__dayNum])): ?>
              <?php foreach ($__eventsByDay[$__dayNum] as $__ev): ?>
                <div class="calendar-event" data-color-var="<?= h($__ev['colVar']) ?>"><?= h($__ev['label']) ?></div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Upsell overlay sits ON TOP of the dimmed grid. -->
    <aside class="pro-upsell" role="dialog" aria-labelledby="proUpsellTitle">
      <h3 id="proUpsellTitle">Kalenderansicht ist ein Pro-Feature</h3>
      <p>
        Timeminator Pro bringt die hier gezeigte Monats-/Wochenansicht,
        Drag-&amp;-Drop-Buchungen und Kalender-Konnektoren
        (Google Calendar, iCloud, CalDAV, Outlook).
      </p>
      <p class="muted">
        Die Community-Edition zeigt die Funktion als Vorschau &mdash;
        ohne echte Daten, ohne Verbindungen.
      </p>
      <div class="pro-upsell-actions">
        <a class="btn btn-primary" href="<?= h($pro_url) ?>" target="_blank" rel="noopener">
          Mehr erfahren &nearr;
        </a>
        <a class="btn" href="<?= h(route('entries')) ?>">Zur Listen-Ansicht</a>
      </div>
    </aside>
  </div>
</div>
