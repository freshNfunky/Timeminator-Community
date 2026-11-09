<?php
/** @var ?array $running @var ?array $runningProcess
 *  @var array $recent @var array $tasks
 *  @var int $minToday @var int $minWeek @var int $minMonth
 *  @var array $planning   Repo::projectPlanningSnapshot(); may be empty
 *  @var array $processCategories  ProcessRepo::categories(true) */
?>
<div class="grid-tiles">
  <div class="tile"><div class="tile-val"><?= h(fmt_hours($minToday)) ?></div><div class="tile-lbl"><?= h(t('dash.today')) ?></div></div>
  <div class="tile"><div class="tile-val"><?= h(fmt_hours($minWeek)) ?></div><div class="tile-lbl"><?= h(t('dash.this_week')) ?></div></div>
  <div class="tile"><div class="tile-val"><?= h(fmt_hours($minMonth)) ?></div><div class="tile-lbl"><?= h(t('dash.this_month')) ?></div></div>
</div>

<div class="card timer-card">
  <h2 class="timer-card-title"><?= h(t('dash.timer_h2', 'Zeitaufzeichnung')) ?><?= tip('dash.timer_hint') ?></h2>
  <?php if ($running): ?>
    <div class="timer-running" data-start="<?= h($running['start_ts']) ?>">
      <div>
        <span class="badge badge-live"><?= h(t('dash.timer_running_badge', 'laeuft')) ?></span>
        <strong><?= h(entry_client_display($running)) ?> / <?= h($running['project_name']) ?></strong>
        <span class="muted">· <?= h($running['task_name']) ?></span>
      </div>
      <div class="timer-clock" id="timerClock">00:00:00</div>
      <form method="post" action="<?= h(route('timer_stop')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-danger" type="submit"><?= h(t('dash.timer_stop')) ?></button>
      </form>
    </div>
  <?php elseif ($runningProcess ?? null): ?>
    <?php /* Fix #186: laufender Interner-Prozess-Timer. Kein Kunde/Projekt,
       nur Kategorie-Label plus optionale Notiz. */ ?>
    <div class="timer-running" data-start="<?= h($runningProcess['start_ts']) ?>">
      <div>
        <span class="badge badge-live"><?= h(t('dash.timer_running_badge', 'laeuft')) ?></span>
        <strong><?= h(t('cal.group_processes', 'Interne Prozesse')) ?></strong>
        <span class="muted">· <?= h($runningProcess['category_label']) ?></span>
        <?php if (!empty($runningProcess['note'])): ?>
          <span class="muted">· <?= h($runningProcess['note']) ?></span>
        <?php endif; ?>
      </div>
      <div class="timer-clock" id="timerClock">00:00:00</div>
      <form method="post" action="<?= h(route('timer_stop')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-danger" type="submit"><?= h(t('dash.timer_stop')) ?></button>
      </form>
    </div>
  <?php else: ?>
    <form method="post" action="<?= h(route('timer_start')) ?>" class="timer-start" id="timerStartForm">
      <?= csrf_field() ?>
      <?php /* Felix-Feedback 2026-11-09 (Fix #182): Projektart-Combobox
         ueber der Kaskade - analog Entry-Dialog. Ohne diese Stufe stand
         im Kunden-Select "Zuerst Projektart waehlen", aber die Projektart
         war nirgends auswaehlbar. */ ?>
      <?php /* Fix #186: Typ-Switcher kombiniert Projektarten + Interne
         Prozesse in EINER Combobox. Projekt-Optionen tragen den Projekt-
         art-Wert als Zahl (1/2/3), Prozess-Optionen tragen "p:<id>"
         analog zum Entry-Dialog-Typ-Switcher. Das Script unterscheidet
         und blendet die Hierarchie bei Prozess-Wahl komplett aus. */ ?>
      <label class="timer-cat-label">
        <?= h(t('dash.timer_type', 'Typ')) ?>
        <select class="timer-cat" aria-label="<?= h(t('dash.timer_type', 'Typ')) ?>">
          <option value=""><?= h(t('projects.pick_category', 'Projektart waehlen …')) ?></option>
          <optgroup label="<?= h(t('cal.type_project', 'Projekt-Zeit')) ?>">
            <?php foreach (($projectCategories ?? []) as $__pc): ?>
              <option value="<?= (int) $__pc['id'] ?>"
                      data-kind="project"
                      data-ckey="<?= h((string) $__pc['ckey']) ?>"
                      data-needs-client="<?= (int) $__pc['needs_client'] ?>"
                      data-client-label="<?= h($__pc['client_label'] ?: t('project_cat.client_default', 'Kunde')) ?>">
                <?= h($__pc['label']) ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
          <?php if (!empty($processCategories)): ?>
            <optgroup label="<?= h(t('cal.group_processes', 'Interne Prozesse')) ?>">
              <?php foreach ($processCategories as $__pc): ?>
                <option value="p:<?= (int) $__pc['id'] ?>" data-kind="process">
                  <?= h($__pc['label']) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
        </select>
      </label>
      <?php /* Hidden Input: bei Prozess-Wahl wird hier die Kategorie-ID
         gesetzt; bei Projekt-Wahl bleibt es leer, dann uebernimmt
         task_picker das task_id-Feld. */ ?>
      <input type="hidden" name="process_category_id" value="" id="timerProcessCategoryId">
      <?php
      $selectedTaskId = 0;
      $wrapClass = 'tp-stack grow';
      require __DIR__ . '/partials/task_picker.php';
      ?>
      <label class="grow"><?= h(t('dash.note')) ?>
        <input type="text" name="note" placeholder="<?= h(t('dash.task_placeholder')) ?>">
      </label>
      <button class="btn btn-primary" type="submit"><?= h(t('dash.timer_start')) ?></button>
    </form>
    <script nonce="<?= h(csp_nonce()) ?>">
    (function () {
      var form   = document.getElementById('timerStartForm');
      if (!form) return;
      var catSel = form.querySelector('.timer-cat');
      var picker = form.querySelector('.task-picker');
      var procIdInput = document.getElementById('timerProcessCategoryId');
      if (!catSel || !picker) return;
      var clientLabel  = picker.querySelector('.tp-client-label');
      var clientText   = clientLabel ? clientLabel.querySelector('.tp-client-label-text') : null;
      var clientSel    = picker.querySelector('.tp-client');
      var projectSel   = picker.querySelector('.tp-project');
      var baseLabel    = clientLabel && clientText
        ? (clientLabel.getAttribute('data-base-label') || clientText.textContent)
        : '';
      function apply() {
        var opt  = catSel.selectedOptions[0];
        var kind = opt ? (opt.getAttribute('data-kind') || '') : '';
        // Fix #186: Interner Prozess - keine Hierarchie, task-picker weg,
        // process_category_id im hidden input setzen. task_id bleibt leer.
        if (kind === 'process') {
          picker.hidden = true;
          if (procIdInput) { procIdInput.value = (opt.value || '').replace(/^p:/, ''); }
          return;
        }
        picker.hidden = false;
        if (procIdInput) { procIdInput.value = ''; }
        var ckey        = opt ? (opt.getAttribute('data-ckey') || '')         : '';
        var needsClient = opt && opt.value ? opt.getAttribute('data-needs-client') === '1' : true;
        var customLabel = opt ? (opt.getAttribute('data-client-label') || '') : '';
        // Fix #157: data-cat-filter am Picker-Root treibt die Projekt-
        // Options-Filterung in enforceHierarchy/applyFilters.
        if (ckey) { picker.setAttribute('data-cat-filter', ckey); }
        else      { picker.removeAttribute('data-cat-filter'); }
        // Internes Projekt: Kunden-Feld ausblenden.
        if (clientLabel) { clientLabel.hidden = !needsClient; }
        if (clientSel) {
          if (!needsClient) { clientSel.value = ''; clientSel.disabled = true; }
          else              { clientSel.disabled = false; }
        }
        // Label-Swap bei Foerderprojekt.
        if (clientText) {
          clientText.textContent = (needsClient && customLabel) ? customLabel : baseLabel;
        }
        // Kaskade stossen (task_picker haengt an change).
        if (clientSel)  clientSel.dispatchEvent(new Event('change', {bubbles: true}));
        if (projectSel) projectSel.dispatchEvent(new Event('change', {bubbles: true}));
      }
      catSel.addEventListener('change', apply);
      apply();
    })();
    </script>
    <?php if (!$tasks): ?>
      <p class="muted"><?= h(t('dash.no_tasks')) ?>
        <a href="<?= h(route('clients')) ?>"><?= h(t('nav.clients')) ?></a>,
        <a href="<?= h(route('projects')) ?>"><?= h(t('nav.projects')) ?></a>
        <?= h(t('dash.no_tasks_and', 'und')) ?>
        <a href="<?= h(route('tasks')) ?>"><?= h(t('nav.tasks')) ?></a><?= h(t('dash.no_tasks_cont')) ?></p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php // Felix-Feedback 2026-11-08: persoenliches Sentiment-Widget (Pro-Parity).
      // Titel + Save-Scope folgen dem Granularity-Preset. Kein Scope-Dropdown
      // mehr, weil redundant: der Rhythmus ist bereits Admin-Einstellung.
      // Follow-up fuer Milestone 1.1 (optionaler Scope-Override) als Pro-Issue
      // vermerkt. ?>
<?php if (Auth::can('sentiment.record')):
  $__isWeekScope = ($sentimentGranularity ?? 'week') === 'week';
  $__title = $__isWeekScope
      ? t('dashboard.mood_question_week', 'Wie geht es dir diese Woche?')
      : t('dashboard.mood_question', 'Wie geht es dir heute?');
?>
<div class="card sentiment-widget">
  <div class="card-head">
    <h2><?= h($__title) ?><?= tip('dashboard.mood_help') ?></h2>
  </div>
  <?php foreach (Sentiment::DIMENSIONS as $__d):
      $__cur = $sentimentToday[$__d] ?? null;
      $__dimLabel = $__d === 'mood'
          ? t('sent.dim_mood_label', 'Stimmung')
          : t('sent.dim_flow_label', 'Fokus');
      $__dimIcon  = $__d === 'mood' ? '🙂' : '🎯';
  ?>
  <form method="post" action="<?= h(route('sentiment_quick')) ?>" class="sentiment-form">
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="dashboard">
    <input type="hidden" name="dim" value="<?= h($__d) ?>">
    <?php /* Scope folgt dem Granularity-Preset (Felix-Feedback 2026-11-08). */ ?>
    <input type="hidden" name="scope" value="<?= $__isWeekScope ? 'week' : 'day' ?>">
    <div class="sentiment-row">
      <span class="sentiment-kind">
        <span aria-hidden="true"><?= $__dimIcon ?></span>
        <?= h($__dimLabel) ?>
      </span>
      <div class="sentiment-scale">
        <?php foreach (range(1, 5) as $__val):
            $__emoji = $__d === 'mood' ? Sentiment::moodEmoji($__val) : Sentiment::flowEmoji($__val);
            $__label = $__d === 'mood' ? Sentiment::moodLabel($__val) : Sentiment::flowLabel($__val);
        ?>
          <button type="submit" name="score" value="<?= (int) $__val ?>"
                  class="sentiment-opt<?= ((int) $__cur) === $__val ? ' active' : '' ?>"
                  title="<?= h($__label) ?> (<?= (int) $__val ?>/5)"><?= $__emoji ?></button>
        <?php endforeach; ?>
      </div>
    </div>
  </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($planning)): ?>
  <?php
    // Precompute rows + percentages here so the <style> block below can
    // emit one width rule per row (strict CSP forbids inline style="…").
    $__planningRows = [];
    foreach ($planning as $__i => $__p) {
        $__targetMin = (int) round($__p['target_hours'] * 60);
        $__pct = $__targetMin > 0 ? (int) round(($__p['actual_minutes'] / $__targetMin) * 100) : 0;
        $__barClass = $__pct > 100 ? 'planning-bar-over' : ($__pct >= 80 ? 'planning-bar-warn' : 'planning-bar-ok');
        $__planningRows[] = [
            'i'        => (int) $__i,
            'id'       => (int) $__p['id'],
            'client'   => (string) $__p['client_name'],
            'name'     => (string) $__p['name'],
            'target_h' => (float) $__p['target_hours'],
            'actual_m' => (int) $__p['actual_minutes'],
            'pct'      => $__pct,
            'fill'     => min(100, max(0, $__pct)),
            'barcls'   => $__barClass,
        ];
    }
  ?>
  <style nonce="<?= h(csp_nonce()) ?>">
  <?php foreach ($__planningRows as $__r): ?>
    .planning-bar-fill-<?= $__r['i'] ?>{width:<?= $__r['fill'] ?>%}
  <?php endforeach; ?>
  </style>
  <div class="card">
    <div class="card-head">
      <h2><?= h(t('planning.dashboard_tile_title', 'Projekt-Planung')) ?><?= tip('planning.dashboard_tile_hint') ?></h2>
      <span class="pill"><?= (int) count($__planningRows) ?></span>
    </div>
    <table class="table">
      <thead>
        <tr>
          <th><?= h(t('planning.col_project', 'Projekt')) ?></th>
          <th class="r"><?= h(t('planning.col_target', 'Soll (h)')) ?></th>
          <th class="r"><?= h(t('planning.col_actual', 'Ist (h)')) ?></th>
          <th><?= h(t('planning.col_progress', 'Fortschritt')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($__planningRows as $__r): ?>
          <tr>
            <td><?= h($__r['client']) ?> / <a href="<?= h(route('project_show', ['id' => (int) $__r['id']])) ?>"><?= h($__r['name']) ?></a></td>
            <td class="r"><?= h(number_format($__r['target_h'], 2, ',', '.')) ?></td>
            <td class="r"><?= h(fmt_hours($__r['actual_m'])) ?></td>
            <td>
              <div class="planning-bar">
                <div class="planning-bar-fill planning-bar-fill-<?= $__r['i'] ?> <?= $__r['barcls'] ?>"></div>
              </div>
              <small class="muted"><?= (int) $__r['pct'] ?>%</small>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted planning-pro-teaser">
      <?= h(t('planning.pro_teaser.intro', 'Wochen-/Monats-/Person-Breakdown, Gantt-Ansicht und Budget-Report sind Teil von')) ?>
      <a href="<?= h(route('pro_budget')) ?>"><strong><?= h(t('planning.pro_teaser.link', 'Timeminator Pro')) ?></strong></a>.
    </p>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2><?= h(t('dash.recent')) ?></h2>
    <?php /* Felix-Feedback 2026-11-09 Hardening 6: Dashboard-Link fuehrt
       NUR auf die Kalender-Seite, OHNE den Deep-Link ?new=1. Fix #142
       hat zum "Dialog ueberschreibt Grid-Drag mit heute/09-10"-Bug
       gefuehrt, weil der Deep-Link bei jedem reload erneut 9.11. 09-10
       vorbelegte. Dialog oeffnet jetzt ueber Grid-Drag ODER den
       "+Eintrag"-Button in der Kalender-Toolbar. */ ?>
    <a class="btn btn-sm" href="<?= h(route('entries', ['view' => 'calendar'])) ?>"><?= h(t('dash.add_manual')) ?></a>
  </div>
  <?php if (!$recent): ?>
    <p class="muted"><?= h(t('dash.recent_empty')) ?></p>
  <?php else: ?>
  <table class="table">
    <thead><tr>
      <th><?= h(t('dash.col_start')) ?></th>
      <th><?= h(t('dash.col_client_project')) ?></th>
      <th><?= h(t('dash.col_task')) ?></th>
      <th class="r"><?= h(t('dash.col_duration')) ?></th>
    </tr></thead>
    <tbody>
      <?php foreach ($recent as $e): ?>
      <tr>
        <td><?= h(fmt_dt($e['start_ts'])) ?></td>
        <td><?= h(entry_client_display($e)) ?> / <?= h($e['project_name']) ?></td>
        <td><?= h($e['task_name']) ?><?= $e['note'] ? ' <span class="muted">· ' . h($e['note']) . '</span>' : '' ?></td>
        <td class="r"><?= h(fmt_hm($e['duration_min'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
