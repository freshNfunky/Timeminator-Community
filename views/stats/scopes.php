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
    <h2><?= h($edit ? t('scopes.edit', 'Sicht bearbeiten') : t('scopes.new', 'Neue Nachweis-Sicht')) ?></h2>
    <p class="muted"><?= t('scopes.intro_v2', 'Eine <strong>Nachweis-Sicht</strong> ist ein gespeichertes Filter- und Umbuchungs-Profil fuer die <a href="?r=stats">Statistik-Seite</a>. Dort erscheint sie im Dropdown „Nachweis-Sicht" und wendet ihre Regeln (Kunde-/Projekt-Filter, Traegerwechsel ab Stichtag) auf alle Diagramme und Tabellen an.') /* contains tags, intentional */ ?></p>
    <details class="muted" style="margin-bottom:12px">
      <summary><?= h(t('scopes.example_summary', 'Beispiel: Traegerwechsel-Umbuchung')) ?></summary>
      <p><?= t('scopes.example_body', 'Nordlicht Consulting uebergibt ein Projekt zum 2025-11-01 an Aurora Labs. Die bisherigen Zeitbuchungen bleiben physisch an Nordlicht (unveraenderter Audit-Trail). Die Sicht sagt: „zeige in der Auswertung alles ab 2025-11-01 unter Aurora". Original-Entries werden nicht angeruehrt — nur die Nachweis-Darstellung in der Statistik.') ?></p>
    </details>
    <form method="post" action="<?= h(route('scope_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label><?= h(t('struct.name')) ?> <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></label>
      <label><?= h(t('scopes.description', 'Beschreibung')) ?> <input type="text" name="description" value="<?= h($edit['description'] ?? '') ?>"></label>
      <label><?= h(t('scopes.config_json', 'Konfiguration (JSON)')) ?>
        <textarea name="config" rows="12" spellcheck="false"><?= h($edit['config'] ?? json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></textarea>
      </label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('scopes')) ?>"><?= h(t('common.new')) ?></a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2><?= h(t('scopes.saved_h2', 'Gespeicherte Sichten')) ?></h2>
    <table class="table">
      <thead><tr><th><?= h(t('struct.name')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($scopes as $s): ?>
        <tr>
          <td><strong><?= h($s['name']) ?></strong><?= $s['description'] ? '<br><span class="muted">' . h($s['description']) . '</span>' : '' ?></td>
          <td class="r nowrap">
            <a class="btn btn-sm" href="<?= h(route('scopes', ['edit' => $s['id']])) ?>"><?= h(t('common.edit_short')) ?></a>
            <form method="post" action="<?= h(route('scope_delete')) ?>" class="inline" data-confirm="<?= h(t('scopes.delete_confirm', 'Loeschen?')) ?>">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">x</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$scopes): ?><tr><td colspan="2" class="muted"><?= h(t('scopes.empty', 'Noch keine Sichten.')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>

    <h3><?= h(t('scopes.ref_ids', 'Referenz-IDs')) ?></h3>
    <details><summary><?= h(t('nav.clients')) ?></summary>
      <ul class="ref"><?php foreach ($clients as $c): ?><li><code><?= (int) $c['id'] ?></code> <?= h($c['name']) ?></li><?php endforeach; ?></ul>
    </details>
    <details><summary><?= h(t('nav.projects')) ?></summary>
      <ul class="ref"><?php foreach ($projects as $p): ?><li><code><?= (int) $p['id'] ?></code> <?= h($p['client_name'] . ' / ' . $p['name']) ?></li><?php endforeach; ?></ul>
    </details>
    <?php if ($tracks): ?>
    <details><summary><?= h(t('scopes.tracks_summary', 'Gleise')) ?></summary>
      <ul class="ref"><?php foreach ($tracks as $t): ?><li><?= h($t) ?></li><?php endforeach; ?></ul>
    </details>
    <?php endif; ?>
  </div>
</div>
