<?php
/** @var array $batches @var ?array $preview */
?>
<h1 class="page-title">CSV-Import</h1>

<?php if ($preview): ?>
  <div class="card">
    <div class="card-head">
      <h2>Vorschau: <?= h($preview['label']) ?></h2>
      <span class="pill"><?= (int) $preview['stats']['ok'] ?> gueltig / <?= (int) $preview['stats']['bad'] ?> uebersprungen</span>
    </div>
    <p class="muted">Trennzeichen erkannt: <code><?= h($preview['stats']['delim']) ?></code>. Uebersprungen wird jede Zeile, deren Kunde/Projekt/Aufgabe nicht schon existiert &mdash; die Import-UI erzeugt bewusst keine Struktur.</p>

    <table class="table">
      <thead>
        <tr>
          <th>Status</th>
          <th>Start</th>
          <th>Ende</th>
          <th>Kunde</th>
          <th>Projekt</th>
          <th>Aufgabe</th>
          <th>Benutzer</th>
          <th>Notiz</th>
          <th>Fehler</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($preview['rows'], 0, 200) as $r): ?>
          <tr class="<?= $r['ok'] ? '' : 'muted' ?>">
            <td><?= $r['ok'] ? '<span class="ok-mark">OK</span>' : '<span class="bad-mark">skip</span>' ?></td>
            <td class="nowrap"><?= h($r['start_ts'] ?? ($r['raw']['start_ts'] ?? '')) ?></td>
            <td class="nowrap"><?= h($r['end_ts'] ?? ($r['raw']['end_ts'] ?? '')) ?></td>
            <td><?= h($r['raw']['client_code'] ?? $r['raw']['client_name'] ?? '') ?></td>
            <td><?= h($r['raw']['project_code'] ?? $r['raw']['project_name'] ?? '') ?></td>
            <td><?= h($r['raw']['task_name'] ?? '') ?></td>
            <td><?= h($r['raw']['user_username'] ?? '') ?></td>
            <td><?= h($r['raw']['note'] ?? '') ?></td>
            <td class="muted"><?= h($r['error'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (count($preview['rows']) > 200): ?>
      <p class="muted">Es werden nur die ersten 200 Zeilen der Vorschau angezeigt. Alle als OK markierten Zeilen aus der Datei werden beim Bestaetigen importiert.</p>
    <?php endif; ?>

    <div class="form-actions">
      <form method="post" action="<?= h(route('imports_confirm')) ?>" class="inline">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit">Import bestaetigen (<?= (int) $preview['stats']['ok'] ?> Buchungen)</button>
      </form>
      <form method="post" action="<?= h(route('imports_discard')) ?>" class="inline">
        <?= csrf_field() ?>
        <button class="btn" type="submit">Verwerfen</button>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="card narrow">
    <h2>Neue CSV hochladen</h2>
    <p class="muted">Erwartete Spalten (Header, deutsch/englisch egal, Reihenfolge egal):
      <code>start_ts</code>, <code>end_ts</code>, entweder <code>client_code</code> oder <code>client_name</code>,
      entweder <code>project_code</code> oder <code>project_name</code>, <code>task_name</code>. Optional:
      <code>user_username</code>, <code>note</code>, <code>evidence</code>.
      Kunde, Projekt und Aufgabe muessen vorher existieren.</p>
    <form method="post" action="<?= h(route('imports_upload')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label>Bezeichnung <input type="text" name="label" placeholder="Q3 2026 Backfill"></label>
      <label>Datei <input type="file" name="csv" accept=".csv,text/csv" required></label>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Hochladen und Vorschau</button></div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Vergangene Importe</h2>
  <?php if (!$batches): ?>
    <p class="muted">Noch keine Importe.</p>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr><th>ID</th><th>Bezeichnung</th><th>Quelle</th><th class="r">Buchungen</th><th>Angelegt</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td>#<?= (int) $b['id'] ?></td>
          <td><?= h($b['label']) ?></td>
          <td class="muted"><?= h($b['source']) ?></td>
          <td class="r"><?= (int) $b['entry_count'] ?></td>
          <td class="nowrap"><?= h(fmt_dt($b['created_at'])) ?></td>
          <td class="r">
            <form method="post" action="<?= h(route('imports_delete')) ?>" class="inline" onsubmit="return confirm('Batch #<?= (int) $b['id'] ?> mit <?= (int) $b['entry_count'] ?> Buchungen unwiderruflich loeschen?')">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Batch loeschen</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
