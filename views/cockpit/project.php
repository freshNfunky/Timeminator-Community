<?php
/**
 * v1.0 F2 — Projekt-Cockpit Detail (Shell).
 *
 * F2.2: Health-Flags + Review-Turnus.
 * F2.3 (folgt): Soll/Ist-Matrix hier eingehaengt.
 * F2.4 (folgt): Burn-Up-Chart + Tracker-Timeline hier eingehaengt.
 *
 * @var array $project
 * @var ?array $row
 * @var int $default_review_days
 */
$num = fn($n, int $dec = 1) => $n === null ? '—' : number_format((float) $n, $dec, ',', '.');
$f = $row['flags'] ?? ['kickoff' => false, 'closeout' => false, 'deadline_overdue' => false];
?>
<div class="card">
  <div class="card-head">
    <h2><?= h($project['name']) ?><?php if (!empty($project['code'])): ?> <span class="muted">(<?= h($project['code']) ?>)</span><?php endif; ?></h2>
    <div class="row" style="gap:8px;margin:0">
      <a class="btn btn-ghost btn-sm" href="<?= h(route('cockpit')) ?>">&larr; <?= h(t('cockpit.back_to_overview', 'zurueck zur Uebersicht')) ?></a>
      <?php if (Auth::can('structure.manage')): ?>
        <a class="btn btn-ghost btn-sm" href="<?= h(route('project_show', ['id' => $project['id']])) ?>"><?= h(t('cockpit.open_detail_edit', 'Projekt-Details &amp; Tracker &rarr;')) ?></a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($row): ?>
    <div class="row" style="gap:24px;flex-wrap:wrap">
      <div><span class="muted"><?= h(t('cockpit.col_ist', 'Ist')) ?>:</span> <strong><?= $num($row['ist_hours']) ?> h</strong></div>
      <div><span class="muted"><?= h(t('cockpit.col_target', 'Soll')) ?>:</span> <strong><?= $num($row['target_hours']) ?> h</strong></div>
      <div><span class="muted"><?= h(t('cockpit.col_time_pct', 'Zeit-%')) ?>:</span> <strong><?= $row['zeit_pct'] === null ? '—' : $num($row['zeit_pct'], 0) . '%' ?></strong></div>
      <div><span class="muted"><?= h(t('cockpit.col_progress', 'Fortschritt')) ?>:</span>
        <strong><?= $row['progress'] === null ? '—' : $num($row['progress'], 0) . '%' ?></strong>
        <?php if ($row['forecast'] !== null): ?><span class="muted">(F: <?= $num($row['forecast'], 0) ?>%)</span><?php endif; ?>
      </div>
      <div><span class="muted"><?= h(t('cockpit.col_planned_end', 'Ende')) ?>:</span>
        <strong><?= $row['planned_end'] ? h((new DateTimeImmutable($row['planned_end']))->format('d.m.Y')) : '—' ?></strong>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (Auth::can('structure.manage')): ?>
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.health_h3', 'Projekt-Health')) ?><?= tip('cockpit.health_subtitle') ?></h3>
  </div>

  <?php
    $renderHealthRow = function (string $field, string $label, ?string $currentIso) use ($project) {
      $dateVal = $currentIso ? (new DateTimeImmutable($currentIso))->format('Y-m-d') : '';
  ?>
    <form method="post" action="<?= h(route('cockpit_health_save')) ?>" class="health-row">
      <?= csrf_field() ?>
      <input type="hidden" name="id"    value="<?= (int) $project['id'] ?>">
      <input type="hidden" name="field" value="<?= h($field) ?>">
      <span class="health-row-label"><?= h($label) ?></span>
      <input type="date" name="date" value="<?= h($dateVal) ?>" class="health-row-date">
      <button class="btn btn-ghost btn-sm" type="submit"><?= h(t('common.save', 'Speichern')) ?></button>
      <?php if ($currentIso): ?>
        <button class="btn btn-ghost btn-sm" type="submit" name="clear" value="1"><?= h(t('cockpit.health_clear', 'Zuruecksetzen')) ?></button>
      <?php endif; ?>
    </form>
  <?php }; ?>

  <?php $renderHealthRow('kickoff_at',  t('cockpit.health_kickoff',  'Kickoff'),  $project['kickoff_at']  ?? null); ?>
  <?php $renderHealthRow('closeout_at', t('cockpit.health_closeout', 'Abschluss'), $project['closeout_at'] ?? null); ?>

  <form method="post" action="<?= h(route('cockpit_review_interval_save')) ?>" class="health-row health-row-review">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
    <span class="health-row-label">
      <?= h(t('cockpit.health_review_days', 'Review-Turnus in Tagen (leer = Default')) ?> <?= (int) $default_review_days ?>)
    </span>
    <input type="number" name="review_interval_days" class="health-row-days" value="<?= h((string) ($project['review_interval_days'] ?? '')) ?>" min="1">
    <button class="btn btn-ghost btn-sm" type="submit"><?= h(t('common.save')) ?></button>
  </form>
</div>
<?php endif; ?>

<?php
/** @var list<array{id:int,display_name:?string,username:string}> $matrix_members */
/** @var list<string> $matrix_weeks */
/** @var array<int,array<string,int>> $matrix_ist */
/** @var array<int,array<string,int>> $matrix_soll */
/** @var int $plan_total_minutes */
$weekMonday = static function (string $wk): string {
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $wk, $m)) { return $wk; }
    return (new DateTimeImmutable('today'))->setISODate((int) $m[1], (int) $m[2], 1)->format('Y-m-d');
};
$minToHr = fn(int $m) => $m === 0 ? '' : number_format($m / 60, 1, ',', '.');
?>
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.plan_h3', 'Soll / Ist-Matrix')) ?><?= tip('cockpit.plan_subtitle') ?></h3>
  </div>

  <?php if (Auth::can('structure.manage')): ?>
    <form method="post" action="<?= h(route('cockpit_plan_total_save')) ?>" class="form-inline" style="margin-bottom:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
      <label><?= h(t('cockpit.plan_total', 'Projekt-Gesamt-Soll (Stunden)')) ?>
        <input type="number" name="planned_hours" step="0.5" min="0" value="<?= h($plan_total_minutes > 0 ? number_format($plan_total_minutes / 60, 1, '.', '') : '') ?>" style="width:6em">
      </label>
      <button class="btn btn-ghost btn-sm" type="submit"><?= h(t('common.save')) ?></button>
    </form>
  <?php endif; ?>

  <?php if (!$matrix_members): ?>
    <p class="muted"><?= h(t('cockpit.plan_no_members', 'Noch keine Buchungen oder Planungen auf diesem Projekt. Mitarbeiter erscheinen hier nach der ersten Buchung bzw. Wochen-Planung.')) ?></p>
  <?php else: ?>
    <?php $todayWeekKey = (new DateTimeImmutable('today'))->format('o-\WW'); ?>
    <div class="plan-matrix-scroll">
      <table class="table" id="planMatrix" data-project-id="<?= (int) $project['id'] ?>"
             data-save-url="<?= h(route('cockpit_plan_cell_save')) ?>"
             data-csrf="<?= h(csrf_token()) ?>">
        <thead>
          <tr>
            <th class="plan-col-name"><?= h(t('cockpit.plan_col_member', 'Mitarbeiter')) ?></th>
            <?php foreach ($matrix_weeks as $wk):
              $wkStart = $weekMonday($wk);
              $isCurrent = $wk === $todayWeekKey;
              $thClass = 'r plan-wk' . ($isCurrent ? ' is-current-week' : '');
            ?>
              <th class="<?= $thClass ?>" title="<?= h(t('cockpit.plan_wk_tooltip', 'ab')) ?> <?= h($wkStart) ?>">
                KW<?= h(substr($wk, -2)) ?>
                <br><span class="plan-wk-date"><?= h((new DateTimeImmutable($wkStart))->format('d.m.')) ?></span>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($matrix_members as $u):
          $uid = (int) $u['id'];
          $name = $u['display_name'] ?: $u['username'];
        ?>
          <tr data-user-id="<?= $uid ?>">
            <td class="plan-col-name"><strong><?= h($name) ?></strong></td>
            <?php foreach ($matrix_weeks as $wk):
              $ist  = $matrix_ist[$uid][$wk]  ?? 0;
              $soll = $matrix_soll[$uid][$wk] ?? 0;
              $wkStart = $weekMonday($wk);
              $isCurrent = $wk === $todayWeekKey;
              // Farbkodierung analog Pro-Variante (Felix-Feedback 2026-11-03):
              //   Ist > 1.15 * Soll → rot     (deutlich ueberbucht)
              //   Ist > 1.00 * Soll → orange  (leichte Ueberbuchung)
              //   Ist <= Soll (Soll > 0) → gruen (im Plan)
              //   Soll=0 & Ist>0 → orange     (ungeplanter Aufwand)
              //   Beides 0 → keine Farbe
              $cellClass = '';
              if ($soll > 0) {
                  $ratio = $ist / $soll;
                  $cellClass = $ratio > 1.15 ? 'cell-red'
                             : ($ratio > 1.0 ? 'cell-orange' : 'cell-green');
              } elseif ($ist > 0) {
                  $cellClass = 'cell-orange';
              }
              $tdClass = 'r plan-cell' . ($cellClass ? ' ' . $cellClass : '') . ($isCurrent ? ' is-current-week' : '');
            ?>
              <td class="<?= $tdClass ?>" data-week="<?= h($wkStart) ?>" data-ist="<?= (int) $ist ?>" data-soll="<?= (int) $soll ?>">
                <div class="plan-ist"><?= h($minToHr((int) $ist)) ?: '—' ?></div>
                <?php if (Auth::can('structure.manage')): ?>
                  <input class="plan-soll-input" type="text" inputmode="decimal"
                         value="<?= h($minToHr((int) $soll)) ?>"
                         placeholder="—">
                <?php else: ?>
                  <div class="plan-soll"><?= h($minToHr((int) $soll)) ?: '—' ?></div>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <script nonce="<?= h(csp_nonce()) ?>">
    (function () {
      // Felix-Feedback 2026-11-06: die Matrix zeigt viele Wochen; die aktuelle
      // KW ist meistens irgendwo in der Mitte. Beim Laden dorthin scrollen,
      // damit der senkrechte Rahmen (brand-farbig) im Sichtfeld landet.
      var scroll = document.querySelector('.plan-matrix-scroll');
      var cur = scroll && scroll.querySelector('thead th.is-current-week');
      if (!scroll || !cur) return;
      var left = cur.offsetLeft - (scroll.clientWidth - cur.offsetWidth) / 2;
      scroll.scrollLeft = Math.max(0, left);
    })();
    </script>
  <?php endif; ?>
</div>

<?php if ($matrix_members && Auth::can('structure.manage')): ?>
<script nonce="<?= h(csp_nonce()) ?>">
(function () {
  var table = document.getElementById('planMatrix');
  if (!table) return;
  var url    = table.getAttribute('data-save-url');
  var pid    = table.getAttribute('data-project-id');
  var token  = table.getAttribute('data-csrf');
  var inputs = table.querySelectorAll('.plan-soll-input');
  inputs.forEach(function (inp) {
    var cell = inp.closest('td');
    var row  = inp.closest('tr');
    var orig = inp.value;
    inp.addEventListener('focus', function () { orig = inp.value; });
    inp.addEventListener('blur',  function () {
      if (inp.value === orig) return;
      inp.disabled = true;
      var body = new URLSearchParams();
      // CSRF-Feldname: Server (csrf_check) erwartet "_csrf" - frueher stand hier
      // "csrf_token", was still zu 419 fuehrte und die Matrix nicht persistieren
      // liess (Felix-Feedback 2026-11-03).
      body.set('_csrf', token);
      body.set('project_id', pid);
      body.set('user_id', row.getAttribute('data-user-id'));
      body.set('week', cell.getAttribute('data-week'));
      body.set('hours', inp.value.replace(',', '.'));
      fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          inp.disabled = false;
          if (!d.ok) {
            alert(d.error || 'Fehler');
            inp.value = orig;
            return;
          }
          var mins = d.minutes || 0;
          var hrs = mins ? (mins / 60).toFixed(1).replace('.', ',') : '';
          inp.value = hrs;
          orig = inp.value;
          // Zellen-Farbe nach neuem Soll live aktualisieren (Pro-Vorbild,
          // Felix-Feedback 2026-11-03): gruen/orange/rot je nach Ist-Soll-
          // Verhaeltnis, sonst keine Markierung.
          var ist  = parseInt(cell.getAttribute('data-ist')  || '0', 10);
          cell.setAttribute('data-soll', String(mins));
          cell.classList.remove('cell-green', 'cell-orange', 'cell-red');
          if (mins > 0) {
            var ratio = ist / mins;
            cell.classList.add(ratio > 1.15 ? 'cell-red'
                             : (ratio > 1.0 ? 'cell-orange' : 'cell-green'));
          } else if (ist > 0) {
            cell.classList.add('cell-orange');
          }
        })
        .catch(function (e) { inp.disabled = false; alert(String(e)); inp.value = orig; });
    });
    inp.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') { ev.preventDefault(); inp.blur(); }
      if (ev.key === 'Escape') { inp.value = orig; inp.blur(); }
    });
  });
})();
</script>
<?php endif; ?>

<?php
/** @var list<array<string,mixed>> $timeline @var list<array<string,mixed>> $date_shifts */
?>

<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.burnup_h3', 'Burn-Up (kumulative Stunden)')) ?><?= tip('cockpit.burnup_subtitle') ?></h3>
  </div>
  <div class="chart-box" style="height:260px"><canvas id="cockpitBurnup"></canvas></div>
  <div id="cockpitBurnupLegend"></div>
</div>

<?php // ============== Projekt-Sentiment (Mood-Trend) ================ ?>
<?php /* Felix-Feedback 2026-11-03/04: komplettes Sentiment-Tracking (Mood-
        Trend-Chart aus den Fortschrittsmeldungen). Eigene Card, damit die
        zwei Mood-Skalen (Projekt + Kunde) ueber Zeit sichtbar werden - sonst
        verstecken sich die Werte nur in der Timeline-Textzeile. */ ?>
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.sentiment_h3', 'Projekt-Sentiment')) ?><?= tip('cockpit.sentiment_subtitle') ?></h3>
  </div>
  <p class="muted" id="cockpitSentimentEmpty" hidden><?= h(t('cockpit.sentiment_empty', 'Noch keine Stimmung erfasst. Beim Buchen einer Fortschrittsmeldung Projekt-/Kunden-Stimmung mit angeben.')) ?></p>
  <div class="chart-box" style="height:220px"><canvas id="cockpitSentiment"></canvas></div>
  <div id="cockpitSentimentLegend"></div>
  <div class="sentiment-latest" id="cockpitSentimentLatest" hidden style="margin-top:8px;display:flex;gap:16px;flex-wrap:wrap;font-size:14px">
    <span><strong><?= h(t('cockpit.sentiment_latest', 'Zuletzt')) ?>:</strong></span>
    <span id="cockpitSentimentPm" class="tt-mood"></span>
    <span id="cockpitSentimentCm" class="tt-mood"></span>
  </div>
</div>

<script src="<?= h(asset('charts.js')) ?>"></script>
<script nonce="<?= h(csp_nonce()) ?>">
(function () {
  var canvas = document.getElementById('cockpitSentiment');
  if (!canvas || !window.TmCharts || !TmCharts.lines) return;
  var legend = document.getElementById('cockpitSentimentLegend');
  var empty  = document.getElementById('cockpitSentimentEmpty');
  var latest = document.getElementById('cockpitSentimentLatest');
  var pmEl   = document.getElementById('cockpitSentimentPm');
  var cmEl   = document.getElementById('cockpitSentimentCm');
  var url = <?= json_encode(route('cockpit_sentiment_data', ['id' => $project['id']])) ?>;
  var i18n = <?= json_encode([
      'pm'    => t('cockpit.tt_proj_mood', 'Projekt-Mood'),
      'cm'    => t('cockpit.tt_cust_mood', 'Kunden-Mood'),
  ], JSON_UNESCAPED_UNICODE) ?>;
  // Mood-Emoji-Lookup (muss mit Sentiment::moodEmoji synchron bleiben).
  var moodEmoji = {1:'\u{1F61E}',2:'\u{1F615}',3:'\u{1F610}',4:'\u{1F642}',5:'\u{1F604}'};
  fetch(url, {credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (d) {
    if (!d.ok || !d.points || !d.points.length) {
      empty.hidden = false;
      canvas.parentNode.style.display = 'none';
      return;
    }
    var labels = d.points.map(function (p) { return p.d; });
    var pmData = d.points.map(function (p) { return p.pm === null || p.pm === undefined ? null : p.pm; });
    var cmData = d.points.map(function (p) { return p.cm === null || p.cm === undefined ? null : p.cm; });
    var series = [
      {label: i18n.pm, color: '#8b5cf6', data: pmData},
      {label: i18n.cm, color: '#06b6d4', data: cmData, dashed: true},
    ];
    var pts = [];
    d.points.forEach(function (p, i) {
      if (p.pm !== null && p.pm !== undefined) pts.push({x: i, y: p.pm, color: '#8b5cf6'});
      if (p.cm !== null && p.cm !== undefined) pts.push({x: i, y: p.cm, color: '#06b6d4'});
    });
    TmCharts.lines(canvas, {xLabels: labels, series: series, points: pts, unit: '/5'}, legend);
    if (d.latest) {
      latest.hidden = false;
      var pmV = d.latest.pm, cmV = d.latest.cm;
      pmEl.textContent = (pmV !== null && pmV !== undefined) ? (moodEmoji[pmV] + ' ' + i18n.pm + ' ' + pmV + '/5') : '';
      cmEl.textContent = (cmV !== null && cmV !== undefined) ? (moodEmoji[cmV] + ' ' + i18n.cm + ' ' + cmV + '/5') : '';
    }
  });
})();
</script>

<?php if (Auth::can('structure.manage')): ?>
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.tracker_entry_h3', 'Tracker-Eintrag')) ?><?= tip('cockpit.tracker_entry_subtitle') ?></h3>
  </div>

  <?php
    // Pro-UI-Paritaet (Felix-Feedback 2026-11-03: "wieder pro variante als
    // vorlage"): ein Typ-Switcher statt stapelweise Einzel-Cards. Community hat
    // zwei aktive Typen (Fortschritt + Terminverschiebung), sieben weitere sind
    // als Pro-Teaser disabled - markiert mit dem Suffix (Pro). So wird die
    // vollstaendige Palette sichtbar, ohne die kaufmaennischen Backend-Pfade zu
    // benoetigen.
    $todayIso = (new DateTimeImmutable('today'))->format('Y-m-d');
    $moodOptions = function (): string {
        // Emoji-Paritaet zur Pro-Variante: 1=😞 bis 5=😄 (Sentiment::moodEmoji).
        $labels = [
            1 => t('cockpit.mood_1', 'sehr schlecht'),
            2 => t('cockpit.mood_2', 'schlecht'),
            3 => t('cockpit.mood_3', 'neutral'),
            4 => t('cockpit.mood_4', 'gut'),
            5 => t('cockpit.mood_5', 'sehr gut'),
        ];
        $out = '';
        foreach ($labels as $v => $lbl) {
            $out .= '<option value="' . $v . '">' . Sentiment::moodEmoji($v) . ' ' . $v . ' — ' . h($lbl) . '</option>';
        }
        return $out;
    };
    // Pro-Teaser-Typen (deaktiviert in Community, aktiv in Pro).
    // Meeting-Protokolle gehoeren NICHT dazu (Felix-Feedback 2026-11-03:
    // "kundenmeeting ist nicht pro") - Projektmeetings mit Kunde/Team sind
    // eine Grundfunktion und bleiben in Community aktiv (s.u.).
    $proTypes = [
        'risk'             => t('cockpit.tt_risk',             'Risiko'),
        'decision'         => t('cockpit.tt_decision',         'Entscheidung'),
        'cost'             => t('cockpit.tt_cost',             'Kosten'),
        'budget_increase'  => t('cockpit.tt_budget_increase',  'Budget-Erhoehung'),
        'invoice_sent'     => t('cockpit.tt_invoice_sent',     'Rechnung versendet'),
        'invoice_payment'  => t('cockpit.tt_invoice_payment',  'Zahlung erhalten'),
    ];
    $proSuffix = ' (' . t('cockpit.tt_pro_suffix', 'Pro') . ')';
  ?>

  <div class="row tracker-entry-row" style="align-items:flex-end;margin-bottom:.6em">
    <label>
      <?= h(t('cockpit.booking_type', 'Buchungs-Typ')) ?>
      <?php /* Tooltip statt Prosa-Absatz (Felix-Feedback 2026-11-03: "prosa-text.
              hinter tooltip verstecken."): der Pro-Hinweis sitzt als ⓘ-Badge
              neben dem Label und oeffnet den gleichen Text als Popover. */ ?>
      <?= tip('cockpit.tracker_pro_hint') ?>
      <select id="trackerType" class="tracker-type-select">
        <option value="progress"><?= h(t('cockpit.tt_progress', 'Fortschritt')) ?></option>
        <option value="date_shift"><?= h(t('cockpit.tt_date_shift', 'Terminverschiebung')) ?></option>
        <option value="meeting"><?= h(t('cockpit.tt_meeting', 'Meeting-Protokoll')) ?></option>
        <?php foreach ($proTypes as $key => $label): ?>
          <option value="<?= h($key) ?>" disabled data-pro="1"><?= h($label . $proSuffix) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <?php // === Fortschritts-Meldung (Community: progress) ================= ?>
  <form method="post" action="<?= h(route('project_tracker_save')) ?>" class="row tracker-form" data-tracker-type="progress" style="flex-wrap:wrap;align-items:flex-end;gap:10px">
    <?= csrf_field() ?>
    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
    <input type="hidden" name="type" value="progress">
    <input type="hidden" name="return_to" value="cockpit_project">
    <label><?= h(t('cockpit.f_date', 'Stand')) ?>
      <input type="date" name="date_value" value="<?= h($todayIso) ?>">
    </label>
    <label><?= h(t('cockpit.f_progress_pct', 'Fortschritt (%)')) ?>
      <input type="number" name="value" min="0" max="100" step="1" required style="width:6em">
    </label>
    <label><?= h(t('cockpit.f_forecast_pct', 'Prognose (%)')) ?>
      <input type="number" name="value_forecast" min="0" max="100" step="1" style="width:6em" placeholder="<?= h(t('cockpit.f_optional', 'optional')) ?>">
    </label>
    <label><?= h(t('cockpit.f_project_mood', 'Projekt-Stimmung')) ?>
      <select name="project_mood">
        <option value="">—</option>
        <?= $moodOptions() ?>
      </select>
    </label>
    <label><?= h(t('cockpit.f_customer_mood', 'Kunden-Stimmung')) ?>
      <select name="customer_mood">
        <option value="">—</option>
        <?= $moodOptions() ?>
      </select>
    </label>
    <label style="flex:1 1 20em"><?= h(t('cockpit.f_comment', 'Kommentar')) ?>
      <input type="text" name="note" maxlength="4000" placeholder="<?= h(t('cockpit.f_comment_ph', 'optional, kurze Notiz')) ?>">
    </label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= h(t('cockpit.book_progress', 'Fortschritt buchen')) ?></button>
    </div>
  </form>

  <?php // === Terminverschiebung (Community: date_shift) =================== ?>
  <form method="post" action="<?= h(route('cockpit_date_shift_save')) ?>" class="row tracker-form" data-tracker-type="date_shift" hidden style="flex-wrap:wrap;align-items:flex-end;gap:10px">
    <?= csrf_field() ?>
    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
    <label><?= h(t('cockpit.date_shift_current', 'Aktuell')) ?>:
      <strong><?= $project['planned_end'] ? h((new DateTimeImmutable($project['planned_end']))->format('d.m.Y')) : '—' ?></strong>
    </label>
    <label><?= h(t('cockpit.date_shift_new', 'Neues Enddatum'))
      ?> <input type="date" name="new_end" value="<?= h((string) ($project['planned_end'] ?? '')) ?>" required></label>
    <label style="flex:1 1 20em"><?= h(t('cockpit.date_shift_reason', 'Begruendung'))
      ?> <input type="text" name="reason" maxlength="500" required></label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= h(t('cockpit.date_shift_save', 'Verschiebung speichern')) ?></button>
    </div>
  </form>

  <?php // === Meeting-Protokoll (Community: meeting) =================== ?>
  <?php /* Felix-Feedback 2026-11-03: "kundenmeeting ist nicht pro / fehlt zudem
          hier in der community variante" - Projektmeetings bleiben Grund-
          funktion. Kompakte 2-Spalten-Form: Datum + Titel, dann frei-text
          Teilnehmer + Notizen/Beschluesse (als Note-Feld). */ ?>
  <form method="post" action="<?= h(route('project_tracker_save')) ?>" class="row tracker-form" data-tracker-type="meeting" hidden style="flex-wrap:wrap;align-items:flex-end;gap:10px">
    <?= csrf_field() ?>
    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
    <input type="hidden" name="type" value="meeting">
    <input type="hidden" name="return_to" value="cockpit_project">
    <label><?= h(t('cockpit.f_meeting_date', 'Meeting-Datum')) ?>
      <input type="date" name="date_value" value="<?= h($todayIso) ?>" required>
    </label>
    <label><?= h(t('cockpit.f_meeting_title', 'Titel')) ?>
      <input type="text" name="meeting_title" maxlength="255" placeholder="<?= h(t('cockpit.f_meeting_title_ph', 'z. B. Kundentermin KW 45')) ?>">
    </label>
    <label class="grow"><?= h(t('cockpit.f_meeting_participants', 'Teilnehmer')) ?>
      <input type="text" name="meeting_participants" maxlength="500" placeholder="<?= h(t('cockpit.f_meeting_participants_ph', 'optional, z. B. Max Mustermann, Erika Beispiel')) ?>">
    </label>
    <label class="grow"><?= h(t('cockpit.f_meeting_notes', 'Notizen / Beschluesse')) ?>
      <textarea name="note" rows="4" required placeholder="<?= h(t('cockpit.f_meeting_notes_ph', 'Wesentliche Punkte, Beschluesse, Action Items …')) ?>"></textarea>
    </label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= h(t('cockpit.book_meeting', 'Meeting protokollieren')) ?></button>
    </div>
  </form>

  <?php // Pro-only disabled Platzhalter-Panel (nur sichtbar, wenn im Select angewaehlt). ?>
  <?php foreach ($proTypes as $key => $label): ?>
    <div class="tracker-form tracker-pro-teaser" data-tracker-type="<?= h($key) ?>" hidden>
      <p class="muted"><?= h(t('cockpit.tracker_pro_teaser', 'Dieser Buchungs-Typ ist Teil der Pro-Variante und hier nicht verfuegbar.')) ?></p>
    </div>
  <?php endforeach; ?>

  <script nonce="<?= h(csp_nonce()) ?>">
  (function () {
    var sel = document.getElementById('trackerType');
    if (!sel) return;
    var forms = Array.prototype.slice.call(document.querySelectorAll('.tracker-form[data-tracker-type]'));
    var key = 'tm_tracker_type_<?= (int) $project['id'] ?>';
    function show(type) {
      forms.forEach(function (f) { f.hidden = f.getAttribute('data-tracker-type') !== type; });
    }
    try {
      var last = sessionStorage.getItem(key);
      if (last) {
        var opt = sel.querySelector('option[value="' + last + '"]');
        if (opt && !opt.disabled) sel.value = last;
      }
    } catch (e) { /* storage unavailable */ }
    show(sel.value);
    sel.addEventListener('change', function () {
      show(sel.value);
      try { sessionStorage.setItem(key, sel.value); } catch (e) { /* storage unavailable */ }
    });
  })();
  </script>

  <?php if ($date_shifts): ?>
    <h4 style="margin:1.2em 0 .4em"><?= h(t('cockpit.date_shift_history', 'Terminverschiebungen (Historie)')) ?></h4>
    <table class="table" style="margin-top:10px">
      <thead>
        <tr>
          <th><?= h(t('cockpit.ds_col_when',   'Erfasst')) ?></th>
          <th><?= h(t('cockpit.ds_col_from',   'Von')) ?></th>
          <th><?= h(t('cockpit.ds_col_to',     'Nach')) ?></th>
          <th class="r"><?= h(t('cockpit.ds_col_days', 'Tage')) ?></th>
          <th><?= h(t('cockpit.ds_col_reason', 'Begruendung')) ?></th>
          <th><?= h(t('cockpit.ds_col_user',   'Von wem')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($date_shifts as $s):
        $days = DateShiftRepo::diffDays($s['old_end'], $s['new_end']);
      ?>
        <tr>
          <td><?= h((new DateTimeImmutable($s['created_at']))->format('d.m.Y')) ?></td>
          <td><?= $s['old_end'] ? h((new DateTimeImmutable($s['old_end']))->format('d.m.Y')) : '—' ?></td>
          <td><?= $s['new_end'] ? h((new DateTimeImmutable($s['new_end']))->format('d.m.Y')) : '—' ?></td>
          <td class="r <?= $days > 0 ? 'cell-orange' : '' ?>"><?= ($days > 0 ? '+' : '') . (int) $days ?></td>
          <td><?= h((string) $s['reason']) ?></td>
          <td><?= h((string) ($s['display_name'] ?: $s['username'] ?: '—')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.timeline_h3', 'Tracker-Timeline')) ?><?= tip('cockpit.timeline_subtitle') ?></h3>
  </div>

  <?php if (!$timeline): ?>
    <p class="muted"><?= h(t('cockpit.timeline_empty', 'Noch keine Tracker-Eintraege.')) ?></p>
  <?php else: ?>
    <?php $canEditTracker = Auth::can('structure.manage'); ?>
    <ul class="tracker-timeline">
    <?php foreach ($timeline as $t):
      $when   = (new DateTimeImmutable($t['created_at']))->format('d.m.Y');
      $tid    = (int) $t['id'];
      $ttype  = (string) $t['type'];
      $editable = $canEditTracker && in_array($ttype, ['progress', 'date_shift', 'meeting', 'status_report'], true);
      $createdLocal = (new DateTimeImmutable($t['created_at']))->format('Y-m-d\TH:i');
    ?>
      <li class="tt-item tt-<?= h($ttype) ?>">
        <div class="tt-head">
          <strong><?= h($when) ?></strong>
          <span class="muted"><?= h((string) ($t['user_username'] ?? '')) ?></span>
          <span class="pill pill-<?= h($ttype) ?>">
            <?php switch ($ttype) {
              case 'progress':      echo h(t('cockpit.tt_progress',   'Fortschritt')); break;
              case 'date_shift':    echo h(t('cockpit.tt_date_shift', 'Terminverschiebung')); break;
              case 'meeting':       echo h(t('cockpit.tt_meeting',    'Meeting-Protokoll')); break;
              case 'status_report': echo h(t('cockpit.tt_status',     'Status')); break;
              default:              echo h($ttype);
            } ?>
          </span>
          <?php if ($editable): ?>
            <form method="post" action="<?= h(route('project_tracker_delete')) ?>" class="tt-del-form"
                  onsubmit="return confirm('<?= h(t('cockpit.delete_entry_confirm', 'Eintrag wirklich loeschen?')) ?>')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $tid ?>">
              <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
              <input type="hidden" name="return_to" value="cockpit_project">
              <button class="tt-del" type="submit" title="<?= h(t('cockpit.delete', 'Loeschen')) ?>" aria-label="<?= h(t('cockpit.delete', 'Loeschen')) ?>">&times;</button>
            </form>
          <?php endif; ?>
        </div>
        <div class="tt-body">
          <?php if ($ttype === 'progress'): ?>
            <strong><?= h(number_format((float) $t['value'], 0, ',', '.')) ?>%</strong>
            <?php if ($t['value_forecast'] !== null): ?>
              <span class="muted">(<?= h(t('cockpit.tt_forecast', 'Prognose')) ?>: <?= h(number_format((float) $t['value_forecast'], 0, ',', '.')) ?>%)</span>
            <?php endif; ?>
            <?php if ($t['date_value']): ?><span class="muted"> · <?= h(t('cockpit.tt_as_of', 'Stand')) ?> <?= h((new DateTimeImmutable($t['date_value']))->format('d.m.Y')) ?></span><?php endif; ?>
            <?php if (!empty($t['project_mood'])):  ?><span class="tt-mood" title="<?= h(t('cockpit.tt_proj_mood', 'Projekt-Mood')) ?> <?= (int) $t['project_mood'] ?>/5 (<?= h(Sentiment::moodLabel((int) $t['project_mood'])) ?>)"> · <?= Sentiment::moodEmoji((int) $t['project_mood']) ?> <span class="muted"><?= h(t('cockpit.tt_proj_mood', 'Projekt-Mood')) ?> <?= (int) $t['project_mood'] ?>/5</span></span><?php endif; ?>
            <?php if (!empty($t['customer_mood'])): ?><span class="tt-mood" title="<?= h(t('cockpit.tt_cust_mood', 'Kunde')) ?> <?= (int) $t['customer_mood'] ?>/5 (<?= h(Sentiment::moodLabel((int) $t['customer_mood'])) ?>)"> · <?= Sentiment::moodEmoji((int) $t['customer_mood']) ?> <span class="muted"><?= h(t('cockpit.tt_cust_mood', 'Kunde')) ?> <?= (int) $t['customer_mood'] ?>/5</span></span><?php endif; ?>
          <?php elseif ($ttype === 'date_shift'): ?>
            <?= h(t('cockpit.tt_shift_to', 'Neues Enddatum')) ?>:
            <strong><?= $t['date_value'] ? h((new DateTimeImmutable($t['date_value']))->format('d.m.Y')) : '—' ?></strong>
          <?php else: ?>
            <?php if ($t['status']): ?><span class="pill pill-<?= h((string) $t['status']) ?>"><?= h((string) $t['status']) ?></span><?php endif; ?>
            <?php if ($t['date_value']): ?><span class="muted"> · <?= h((new DateTimeImmutable($t['date_value']))->format('d.m.Y')) ?></span><?php endif; ?>
          <?php endif; ?>
          <?php if (!empty($t['note'])): ?>
            <div class="muted tt-note"><?= nl2br(h((string) $t['note'])) ?></div>
          <?php endif; ?>
        </div>
        <?php if ($editable): ?>
          <details class="tt-edit">
            <summary class="btn btn-ghost btn-sm"><?= h(t('cockpit.edit', 'Bearbeiten')) ?></summary>
            <form method="post" action="<?= h(route('project_tracker_update')) ?>" class="tt-edit-form">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $tid ?>">
              <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
              <input type="hidden" name="return_to" value="cockpit_project">
              <label><?= h(t('cockpit.f_entry_date', 'Eintrags-Datum')) ?>
                <input type="datetime-local" name="created_at" value="<?= h($createdLocal) ?>">
              </label>
              <?php if ($ttype === 'progress'): ?>
                <label><?= h(t('cockpit.f_as_of', 'Stand')) ?>
                  <input type="date" name="date_value" value="<?= h((string) ($t['date_value'] ?? '')) ?>">
                </label>
                <label><?= h(t('cockpit.f_progress_pct', 'Fortschritt (%)')) ?>
                  <input type="number" name="value" min="0" max="100" step="1" value="<?= h((string) (int) $t['value']) ?>">
                </label>
                <label><?= h(t('cockpit.f_forecast_pct', 'Prognose (%)')) ?>
                  <input type="number" name="value_forecast" min="0" max="100" step="1" value="<?= $t['value_forecast'] !== null ? h((string) (int) $t['value_forecast']) : '' ?>">
                </label>
                <?php $pm = (int) ($t['project_mood'] ?? 0); $cm = (int) ($t['customer_mood'] ?? 0); ?>
                <label><?= h(t('cockpit.tt_proj_mood', 'Projekt-Mood')) ?>
                  <select name="project_mood">
                    <option value="">–</option>
                    <?php foreach (range(1, 5) as $i): ?>
                      <option value="<?= $i ?>"<?= $pm === $i ? ' selected' : '' ?>><?= Sentiment::moodEmoji($i) ?> <?= $i ?>/5</option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label><?= h(t('cockpit.tt_cust_mood', 'Kunde')) ?>
                  <select name="customer_mood">
                    <option value="">–</option>
                    <?php foreach (range(1, 5) as $i): ?>
                      <option value="<?= $i ?>"<?= $cm === $i ? ' selected' : '' ?>><?= Sentiment::moodEmoji($i) ?> <?= $i ?>/5</option>
                    <?php endforeach; ?>
                  </select>
                </label>
              <?php elseif ($ttype === 'date_shift'): ?>
                <label><?= h(t('cockpit.tt_shift_to', 'Neues Enddatum')) ?>
                  <input type="date" name="date_value" value="<?= h((string) ($t['date_value'] ?? '')) ?>">
                </label>
              <?php elseif ($ttype === 'status_report'): ?>
                <label><?= h(t('cockpit.f_status', 'Status')) ?>
                  <input type="text" name="status" maxlength="32" value="<?= h((string) ($t['status'] ?? '')) ?>">
                </label>
                <label><?= h(t('cockpit.f_date', 'Datum')) ?>
                  <input type="date" name="date_value" value="<?= h((string) ($t['date_value'] ?? '')) ?>">
                </label>
              <?php endif; ?>
              <label class="tt-edit-note"><?= h(t('cockpit.f_note', 'Notiz')) ?>
                <textarea name="note" rows="3"><?= h((string) ($t['note'] ?? '')) ?></textarea>
              </label>
              <div class="tt-edit-actions">
                <button class="btn btn-primary btn-sm" type="submit"><?= h(t('common.save', 'Speichern')) ?></button>
                <button class="btn btn-ghost btn-sm tt-edit-cancel" type="button"><?= h(t('common.cancel', 'Abbrechen')) ?></button>
              </div>
            </form>
          </details>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ul>
    <?php if ($canEditTracker): ?>
    <script nonce="<?= h(csp_nonce()) ?>">
    (function () {
      // Abbrechen-Button im Tracker-Edit-Form: schliesst das umgebende
      // <details> ohne POST. Felix-Feedback 2026-11-06: "wir muessen auch
      // immer die Moeglichkeit haben, die Bearbeitung abbrechen zu koennen".
      document.querySelectorAll('.tracker-timeline .tt-edit-cancel').forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
          ev.preventDefault();
          var det = btn.closest('details.tt-edit');
          if (det) { det.open = false; }
        });
      });
    })();
    </script>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script src="<?= h(asset('charts.js')) ?>"></script>
<script nonce="<?= h(csp_nonce()) ?>">
(function () {
  var canvas = document.getElementById('cockpitBurnup');
  if (!canvas || !window.TmCharts || !TmCharts.lines) return;
  var legend = document.getElementById('cockpitBurnupLegend');
  var url = <?= json_encode(route('cockpit_burnup_data', ['id' => $project['id']])) ?>;
  var i18n = <?= json_encode([
      'cum_hours'    => t('cockpit.burnup_cum_hours', 'Ist (Stunden)'),
      'target'       => t('cockpit.burnup_target',    'Budget'),
      'soll'         => t('cockpit.burnup_soll',      'Soll (Plan-Matrix)'),
      'none'         => t('cockpit.burnup_none',      'Noch keine Buchungen auf diesem Projekt.'),
      'progress'     => t('cockpit.burnup_progress',  'Fortschritt'),
      'forecast'     => t('cockpit.burnup_forecast',  'Prognose'),
      'deadline'     => t('cockpit.burnup_deadline',  'Planende'),
      'delay'        => t('cockpit.burnup_delay',     'Verzoegerung'),
      'ideal'        => t('cockpit.burnup_ideal',     'Ideal'),
      'within'       => t('cockpit.burnup_within',    'im Budget'),
      'over'         => t('cockpit.burnup_over',      'Budget ueberschritten'),
  ], JSON_UNESCAPED_UNICODE) ?>;
  fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
    if (!d.ok || !d.points.length) {
      var ctx = canvas.getContext('2d');
      ctx.font = '13px sans-serif';
      ctx.fillStyle = '#888';
      ctx.textAlign = 'center';
      ctx.fillText(i18n.none, canvas.width / 2, canvas.height / 2);
      return;
    }
    var labels = d.points.map(function (p) { return p.d; });
    var series = [{ label: i18n.cum_hours, color: '#2563eb', data: d.points.map(function (p) { return p.h; }) }];
    // Felix-Feedback 2026-11-06: Soll-Kurve = Integral der Plan-Matrix. Steigt
    // je Woche um die geplanten Stunden - ermoeglicht Woche-fuer-Woche-Vergleich
    // mit der Ist-Linie und endet rechts auf dem Projekt-Gesamt-Soll (target_h).
    // Farbe cyan (#06b6d4) statt orange, weil orange schon fuer die Prognose-
    // Strahlen belegt ist (Felix-Feedback 2026-11-06: "nicht orange wie die
    // prognose, sondern eher gruen oder cyan").
    var sollCurve = d.soll_curve || [];
    var hasSoll = sollCurve.some(function (v) { return v !== null && v !== undefined && v > 0; });
    if (hasSoll) {
      series.push({ label: i18n.soll, color: '#06b6d4', dashed: true, data: sollCurve });
    }
    if (d.target_h) {
      series.push({ label: i18n.target + ' ' + d.target_h + ' h', color: '#94a3b8', dashed: true,
        data: labels.map(function () { return d.target_h; }) });
    }
    // Fortschritts-Linie: linear verbunden zwischen den Meldeterminen (Pro-Vorbild).
    // Der Backend liefert progress_line step-forward-gefuellt (jeder Timestep traegt
    // den letzten gemeldeten Wert bis zum naechsten Report) - das zeichnet optisch
    // Stufen. Fuer die Pro-Optik interpolieren wir hier linear zwischen den
    // progress_markers: vor dem ersten und nach dem letzten Report bleibt null
    // (keine Linie), zwischen zwei Reports steigt bzw. faellt die Linie gerade.
    var marks = (d.progress_markers || []).slice().sort(function (a, b) { return a.x - b.x; });
    var hasProgress = marks.length > 0;
    if (hasProgress) {
      var progressLinear = labels.map(function (_, i) {
        for (var k = 0; k < marks.length - 1; k++) {
          if (i >= marks[k].x && i <= marks[k + 1].x) {
            var span = marks[k + 1].x - marks[k].x;
            if (span <= 0) return marks[k].y;
            var t = (i - marks[k].x) / span;
            return marks[k].y + t * (marks[k + 1].y - marks[k].y);
          }
        }
        if (marks.length === 1 && i === marks[0].x) return marks[0].y;
        if (marks.length >= 1 && i === marks[marks.length - 1].x) return marks[marks.length - 1].y;
        return null;
      });
      series.push({ label: i18n.progress + ' (%)', color: '#8b5cf6', right: true, marker: 'dot', data: progressLinear });
    }
    var pts = marks.map(function (m) {
      return { x: m.x, y: m.y, color: '#8b5cf6', right: true };
    });
    // Prognose-Strahlen vom Meldepunkt im Review-Turnus-Abstand zum Forecast-Wert.
    // Pro-Vorbild: hohler Kreis am Ray-Ende (nur Umriss, kein Fill).
    var rays = (d.forecast_rays || []).map(function (r) {
      return { x0: r.x0, y0: r.y0, x1: r.x1, y1: r.y1, color: '#d97706', dashed: true, right: true };
    });
    // Legende: Prognose-Zeile als gestrichelter Linien-Swatch + hohler Kreis-Marker
    // am Ende (visuell identisch zum tatsaechlich gezeichneten Strahl).
    var pointLegend = [];
    if (rays.length) {
      pointLegend.push({ label: i18n.forecast + ' (%, +' + (d.review_interval || 30) + ' d)', color: '#d97706', dashed: true, marker: 'ring' });
    }
    // Ideal-Linie (0-100%): als rechte-Achse-Serie mit grau-gestrichelter Linie.
    var idealLine = d.ideal_line || [];
    var hasIdeal = idealLine.some(function (v) { return v !== null && v !== undefined; });
    if (hasIdeal) {
      series.push({ label: i18n.ideal + ' (%)', color: '#64748b', right: true, dashed: true, data: idealLine });
    }
    // Deadline (urspruengliches Enddatum, grau) + Delay (aktuelles nach Shift, orange).
    var vlines = [];
    if (d.deadline && d.deadline.x !== null && d.deadline.x !== undefined) {
      vlines.push({
        index: d.deadline.x, label: '', color: '#94a3b8',
        legend: i18n.deadline + ' ' + d.deadline.date,
      });
    }
    if (d.delay && d.delay.x !== null && d.delay.x !== undefined) {
      vlines.push({
        index: d.delay.x, label: '', color: '#d97706',
        legend: i18n.delay + ' ' + d.delay.date,
      });
    }
    // Flaechen-Integral zwischen Ist und BUDGET (target_h): gruen wo Ist
    // <= Budget, rot wo Ist > Budget. Felix-Feedback 2026-11-06: "rot nur
    // wenn ueber budget" - die Plan-Matrix-Soll-Kurve ist als eigene cyan-
    // Linie sichtbar, aber der Ampel-Fill vergleicht ausschliesslich gegen
    // das Projekt-Gesamt-Soll (target_h). Kein Fill, wenn target_h fehlt.
    var fillBetween = null;
    if (d.target_h) {
      var istSeries  = d.points.map(function (p) { return p.h; });
      var sollSeries = labels.map(function () { return d.target_h; });
      fillBetween = {
        a: istSeries, b: sollSeries,
        belowColor: '#16a34a', aboveColor: '#ef4444',
        belowLabel: i18n.within, aboveLabel: i18n.over,
      };
    }
    TmCharts.lines(canvas, {
      xLabels: labels, series: series, points: pts, rays: rays, vlines: vlines,
      unit: 'h', rightUnit: '%', pointLegend: pointLegend, fillBetween: fillBetween,
    }, legend);
  });
})();
</script>

