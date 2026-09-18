<?php /** @var array $clients @var array $projects @var array $scopes @var array $tracks */
$from = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
$to = (new DateTimeImmutable('now'))->format('Y-m-d');
?>
<form id="statsForm" class="card filter-bar" data-endpoint="<?= h(route('stats_data')) ?>">
  <label>Von <input type="date" name="from" value="<?= h($from) ?>"></label>
  <label>Bis <input type="date" name="to" value="<?= h($to) ?>"></label>
  <label>Gruppieren nach
    <select name="group">
      <option value="project">Projekt</option>
      <option value="client">Kunde</option>
      <option value="track">Gleis / Gruppe</option>
    </select>
  </label>
  <label>Verlauf
    <select name="granularity">
      <option value="day">Tag</option>
      <option value="week">Woche</option>
      <option value="month">Monat</option>
    </select>
  </label>
  <label>Nachweis-Sicht
    <select name="scope_id">
      <option value="0">— keine —</option>
      <?php foreach ($scopes as $s): ?>
        <option value="<?= (int) $s['id'] ?>"><?= h($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php if (Auth::isAdmin()): ?>
    <label class="check"><input type="checkbox" name="all_users" value="1"> alle Benutzer</label>
  <?php endif; ?>
  <button class="btn btn-primary" type="submit">Auswerten</button>
</form>

<div class="grid-tiles">
  <div class="tile"><div class="tile-val" id="totalHours">–</div><div class="tile-lbl">Summe Stunden</div></div>
  <div class="tile"><div class="tile-val" id="scopeName">Gesamt</div><div class="tile-lbl">Sicht</div></div>
  <div class="tile"><div class="tile-val" id="rangeLbl">–</div><div class="tile-lbl">Zeitraum</div></div>
</div>

<div class="chart-grid">
  <div class="card">
    <h2>Verteilung <span class="muted" id="distGroupLbl"></span></h2>
    <div class="chart-box"><canvas id="chartDist"></canvas></div>
    <div id="distLegend"></div>
  </div>
  <div class="card">
    <h2>Zeitverlauf</h2>
    <div class="chart-box"><canvas id="chartSeries"></canvas></div>
  </div>
  <div class="card chart-wide">
    <h2>Gleis-Vergleich <span class="muted">(Gruppen gegenuebergestellt)</span></h2>
    <div class="chart-box"><canvas id="chartTracks"></canvas></div>
  </div>
</div>

<script src="<?= h(asset('charts.js')) ?>"></script>
<script src="<?= h(asset('stats.js')) ?>"></script>
