<?php /** @var array $clients @var ?array $edit @var array $tracks */ ?>
<div class="cols">
  <div class="card col-form">
    <h2><?= h($edit ? t('clients.edit', 'Kunde bearbeiten') : t('clients.new', 'Neuer Kunde')) ?></h2>
    <form method="post" action="<?= h(route('client_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label><?= h(t('struct.name', 'Name')) ?> <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></label>
      <label><?= h(t('struct.code', 'Kennung (Code)')) ?> <input type="text" name="code" value="<?= h($edit['code'] ?? '') ?>" placeholder="<?= h(t('struct.code_placeholder', 'wird sonst automatisch erzeugt')) ?>"></label>
      <label><?= h(t('clients.track', 'Gleis / Gruppe')) ?>
        <input type="text" name="track" list="tracklist" value="<?= h($edit['track'] ?? '') ?>" placeholder="<?= h(t('clients.track_placeholder', 'z. B. formal, physical, …')) ?>">
        <datalist id="tracklist"><?php foreach ($tracks as $tr): ?><option value="<?= h($tr) ?>"><?php endforeach; ?></datalist>
      </label>
      <label><?= h(t('clients.color', 'Farbe')) ?> <input type="color" name="color" value="<?= h($edit['color'] ?? '#3b82f6') ?>"></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> <?= h(t('struct.active', 'aktiv')) ?></label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('clients')) ?>"><?= h(t('common.new', 'Neu')) ?></a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2><?= h(t('nav.clients')) ?></h2>
    <table class="table">
      <thead><tr><th><?= h(t('struct.name', 'Name')) ?></th><th><?= h(t('struct.code_short', 'Kennung')) ?></th><th><?= h(t('clients.track_short', 'Gleis')) ?></th><th></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($clients as $c): ?>
        <tr class="<?= $c['active'] ? '' : 'muted' ?>">
          <td><span class="dot" data-color="<?= h(strtolower((string) ($c['color'] ?: ''))) ?>"></span><?= h($c['name']) ?></td>
          <td><code><?= h($c['code']) ?></code></td>
          <td><?= h($c['track'] ?? '') ?></td>
          <td><?= $c['active'] ? '' : '<span class="pill">' . h(t('struct.inactive', 'inaktiv')) . '</span>' ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('clients', ['edit' => $c['id']])) ?>"><?= h(t('common.edit_short', 'bearb.')) ?></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clients): ?><tr><td colspan="5" class="muted"><?= h(t('clients.empty', 'Noch keine Kunden.')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
