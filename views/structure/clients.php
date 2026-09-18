<?php /** @var array $clients @var ?array $edit @var array $tracks */ ?>
<div class="cols">
  <div class="card col-form">
    <h2><?= $edit ? 'Kunde bearbeiten' : 'Neuer Kunde' ?></h2>
    <form method="post" action="<?= h(route('client_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label>Name <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></label>
      <label>Kennung (Code) <input type="text" name="code" value="<?= h($edit['code'] ?? '') ?>" placeholder="wird sonst automatisch erzeugt"></label>
      <label>Gleis / Gruppe
        <input type="text" name="track" list="tracklist" value="<?= h($edit['track'] ?? '') ?>" placeholder="z. B. formal, physical, …">
        <datalist id="tracklist"><?php foreach ($tracks as $tr): ?><option value="<?= h($tr) ?>"><?php endforeach; ?></datalist>
      </label>
      <label>Farbe <input type="color" name="color" value="<?= h($edit['color'] ?? '#3b82f6') ?>"></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> aktiv</label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Speichern</button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('clients')) ?>">Neu</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2>Kunden</h2>
    <table class="table">
      <thead><tr><th>Name</th><th>Kennung</th><th>Gleis</th><th></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($clients as $c): ?>
        <tr class="<?= $c['active'] ? '' : 'muted' ?>">
          <td><span class="dot" style="background:<?= h($c['color'] ?: '#888') ?>"></span><?= h($c['name']) ?></td>
          <td><code><?= h($c['code']) ?></code></td>
          <td><?= h($c['track'] ?? '') ?></td>
          <td><?= $c['active'] ? '' : '<span class="pill">inaktiv</span>' ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('clients', ['edit' => $c['id']])) ?>">bearb.</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clients): ?><tr><td colspan="5" class="muted">Noch keine Kunden.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
