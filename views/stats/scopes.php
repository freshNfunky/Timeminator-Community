<?php /** @var array $scopes @var ?array $edit @var array $clients @var array $projects @var array $tracks */
$example = [
    'include_project_ids' => [],
    'exclude_client_ids'  => [],
    'tracks'              => [],
    'cutover' => [
        ['date' => '2025-11-01', 'client_id' => 1, 'to_client_id' => 2, 'to_client_name' => 'Neuer Traeger'],
    ],
];
?>
<div class="cols">
  <div class="card col-form">
    <h2><?= $edit ? 'Sicht bearbeiten' : 'Neue Nachweis-Sicht' ?></h2>
    <p class="muted">Eine Nachweis-Sicht filtert und ordnet Eintraege generisch - ohne feste Projekte. Optionales Cutover-Datum ordnet Eintraege ab einem Stichtag einem anderen Kunden zu.</p>
    <form method="post" action="<?= h(route('scope_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label>Name <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></label>
      <label>Beschreibung <input type="text" name="description" value="<?= h($edit['description'] ?? '') ?>"></label>
      <label>Konfiguration (JSON)
        <textarea name="config" rows="12" spellcheck="false"><?= h($edit['config'] ?? json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></textarea>
      </label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Speichern</button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('scopes')) ?>">Neu</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2>Gespeicherte Sichten</h2>
    <table class="table">
      <thead><tr><th>Name</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($scopes as $s): ?>
        <tr>
          <td><strong><?= h($s['name']) ?></strong><?= $s['description'] ? '<br><span class="muted">' . h($s['description']) . '</span>' : '' ?></td>
          <td class="r nowrap">
            <a class="btn btn-sm" href="<?= h(route('scopes', ['edit' => $s['id']])) ?>">bearb.</a>
            <form method="post" action="<?= h(route('scope_delete')) ?>" class="inline" onsubmit="return confirm('Loeschen?')">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">x</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$scopes): ?><tr><td colspan="2" class="muted">Noch keine Sichten.</td></tr><?php endif; ?>
      </tbody>
    </table>

    <h3>Referenz-IDs</h3>
    <details><summary>Kunden</summary>
      <ul class="ref"><?php foreach ($clients as $c): ?><li><code><?= (int) $c['id'] ?></code> <?= h($c['name']) ?></li><?php endforeach; ?></ul>
    </details>
    <details><summary>Projekte</summary>
      <ul class="ref"><?php foreach ($projects as $p): ?><li><code><?= (int) $p['id'] ?></code> <?= h($p['client_name'] . ' / ' . $p['name']) ?></li><?php endforeach; ?></ul>
    </details>
    <?php if ($tracks): ?>
    <details><summary>Gleise</summary>
      <ul class="ref"><?php foreach ($tracks as $t): ?><li><?= h($t) ?></li><?php endforeach; ?></ul>
    </details>
    <?php endif; ?>
  </div>
</div>
