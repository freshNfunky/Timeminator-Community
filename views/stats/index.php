<?php /** @var array $clients @var array $projects @var array $scopes @var array $tracks */
$from = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
$to = (new DateTimeImmutable('now'))->format('Y-m-d');
?>
<?php
  // Translations handed to stats.js so the client renders localised labels.
  // Done via data-attr instead of a global `window.TM_I18N` to keep the
  // strict CSP happy (no inline script needed) and the surface tiny.
  $__statsI18n = [
      'scope_all'  => t('stats.scope_all', 'Gesamt'),
      'by_project' => t('stats.by_project', 'nach Projekt'),
      'by_client'  => t('stats.by_client',  'nach Kunde'),
      'by_track'   => t('stats.by_track',   'nach Gleis'),
      'error'      => t('stats.error',      'Fehler'),
  ];
?>
<form id="statsForm" class="card filter-bar" data-endpoint="<?= h(route('stats_data')) ?>" data-i18n='<?= h(json_encode($__statsI18n, JSON_UNESCAPED_UNICODE)) ?>'>
  <label><?= h(t('entries.from')) ?> <input type="date" name="from" value="<?= h($from) ?>"></label>
  <label><?= h(t('entries.to')) ?> <input type="date" name="to" value="<?= h($to) ?>"></label>
  <label><?= h(t('stats.group_by', 'Gruppieren nach')) ?>
    <select name="group">
      <option value="project"><?= h(t('projects.col_project')) ?></option>
      <option value="client"><?= h(t('struct.client')) ?></option>
      <option value="track"><?= h(t('clients.track')) ?></option>
    </select>
  </label>
  <label><?= h(t('stats.series', 'Verlauf')) ?>
    <select name="granularity">
      <option value="day"><?= h(t('stats.gran.day', 'Tag')) ?></option>
      <option value="week"><?= h(t('stats.gran.week', 'Woche')) ?></option>
      <option value="month"><?= h(t('stats.gran.month', 'Monat')) ?></option>
    </select>
  </label>
  <label><?= h(t('stats.scope', 'Nachweis-Sicht')) ?>
    <select name="scope_id">
      <option value="0"><?= h(t('tasks.wp_none')) ?></option>
      <?php foreach ($scopes as $s): ?>
        <option value="<?= (int) $s['id'] ?>"><?= h($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php if (Auth::isAdmin()): ?>
    <label class="check"><input type="checkbox" name="all_users" value="1"> <?= h(t('stats.all_users', 'alle Benutzer')) ?></label>
  <?php endif; ?>
  <button class="btn btn-primary" type="submit"><?= h(t('stats.run', 'Auswerten')) ?></button>
</form>

<div class="grid-tiles">
  <div class="tile"><div class="tile-val" id="totalHours">–</div><div class="tile-lbl"><?= h(t('stats.total_hours', 'Summe Stunden')) ?></div></div>
  <div class="tile"><div class="tile-val" id="scopeName"><?= h(t('stats.scope_all', 'Gesamt')) ?></div><div class="tile-lbl"><?= h(t('stats.scope_tile', 'Sicht')) ?></div></div>
  <div class="tile"><div class="tile-val" id="rangeLbl">–</div><div class="tile-lbl"><?= h(t('stats.range_tile', 'Zeitraum')) ?></div></div>
</div>

<div class="chart-grid">
  <div class="card">
    <h2><?= h(t('stats.distribution_h2', 'Verteilung')) ?> <span class="muted" id="distGroupLbl"></span></h2>
    <div class="chart-box"><canvas id="chartDist"></canvas></div>
    <div id="distLegend"></div>
  </div>
  <div class="card">
    <h2><?= h(t('stats.series_h2', 'Zeitverlauf')) ?></h2>
    <div class="chart-box"><canvas id="chartSeries"></canvas></div>
  </div>
  <div class="card chart-wide">
    <h2><?= h(t('stats.tracks_h2', 'Gleis-Vergleich')) ?><?= tip('stats.tracks_hint') ?></h2>
    <div class="chart-box"><canvas id="chartTracks"></canvas></div>
  </div>
</div>

<script src="<?= h(asset('charts.js')) ?>"></script>
<script src="<?= h(asset('stats.js')) ?>"></script>
