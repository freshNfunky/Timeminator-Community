<?php /** @var array $projects @var array $clients @var ?array $edit @var array $tracks */ ?>
<div class="cols">
  <div class="card col-form">
    <h2><?= $edit ? 'Projekt bearbeiten' : 'Neues Projekt' ?></h2>
    <form method="post" action="<?= h(route('project_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label>Kunde
        <select name="client_id" required>
          <option value="">Kunde waehlen …</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= ($edit['client_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Name <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></label>
      <label>Kennung (Code) <input type="text" name="code" value="<?= h($edit['code'] ?? '') ?>" placeholder="automatisch, falls leer"></label>
      <label>Gleis / Gruppe (ueberschreibt Kunde)
        <input type="text" name="track" list="tracklist" value="<?= h($edit['track'] ?? '') ?>">
        <datalist id="tracklist"><?php foreach ($tracks as $tr): ?><option value="<?= h($tr) ?>"><?php endforeach; ?></datalist>
      </label>
      <label>Farbe <input type="color" name="color" value="<?= h($edit['color'] ?? '#10b981') ?>"></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> aktiv</label>

      <p class="muted" style="font-size:.85em">Zeit-/Geldbudget, Planung und Fortschritts-Report sind Teil von <b>Timeminator Pro</b>.</p>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Speichern</button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('projects')) ?>">Neu</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2>Projekte</h2>
    <table class="table">
      <thead><tr><th>Kunde</th><th>Projekt</th><th>Gleis</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($projects as $p): ?>
        <tr class="<?= $p['active'] ? '' : 'muted' ?>">
          <td><?= h($p['client_name']) ?></td>
          <td><span class="dot" style="background:<?= h($p['color'] ?: ($p['client_color'] ?: '#888')) ?>"></span><?= h($p['name']) ?> <?= $p['active'] ? '' : '<span class="pill">inaktiv</span>' ?></td>
          <td><?= h($p['track'] ?: ($p['client_track'] ?? '')) ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('projects', ['edit' => $p['id']])) ?>">bearb.</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$projects): ?><tr><td colspan="4" class="muted">Noch keine Projekte.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
