<?php /** @var array $projects @var array $clients @var ?array $edit @var array $tracks */ ?>
<div class="cols">
  <div class="card col-form">
    <h2><?= h($edit ? t('projects.edit', 'Projekt bearbeiten') : t('projects.new', 'Neues Projekt')) ?></h2>
    <form method="post" action="<?= h(route('project_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label><?= h(t('struct.client', 'Kunde')) ?>
        <select name="client_id" required>
          <option value=""><?= h(t('projects.pick_client', 'Kunde waehlen …')) ?></option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= ($edit['client_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><?= h(t('struct.name', 'Name')) ?> <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></label>
      <label><?= h(t('struct.code', 'Kennung (Code)')) ?> <input type="text" name="code" value="<?= h($edit['code'] ?? '') ?>" placeholder="<?= h(t('struct.code_placeholder_short', 'automatisch, falls leer')) ?>"></label>
      <label><?= h(t('projects.track_override', 'Gleis / Gruppe (ueberschreibt Kunde)')) ?>
        <input type="text" name="track" list="tracklist" value="<?= h($edit['track'] ?? '') ?>">
        <datalist id="tracklist"><?php foreach ($tracks as $tr): ?><option value="<?= h($tr) ?>"><?php endforeach; ?></datalist>
      </label>
      <label><?= h(t('clients.color', 'Farbe')) ?> <input type="color" name="color" value="<?= h($edit['color'] ?? '#10b981') ?>"></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> <?= h(t('struct.active')) ?></label>

      <p class="muted note-pro"><?= t('projects.budget_note_pro', 'Zeit-/Geldbudget, Planung und Fortschritts-Report sind Teil von <b>Timeminator Pro</b>.') /* contains <b>, intentional */ ?></p>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('projects')) ?>"><?= h(t('common.new')) ?></a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2><?= h(t('nav.projects')) ?></h2>
    <table class="table">
      <thead><tr><th><?= h(t('struct.client')) ?></th><th><?= h(t('projects.col_project', 'Projekt')) ?></th><th><?= h(t('clients.track_short')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($projects as $p): ?>
        <tr class="<?= $p['active'] ? '' : 'muted' ?>">
          <td><?= h($p['client_name']) ?></td>
          <td><span class="dot" data-color="<?= h(strtolower((string) ($p['color'] ?: ($p['client_color'] ?: '')))) ?>"></span><?= h($p['name']) ?> <?= $p['active'] ? '' : '<span class="pill">' . h(t('struct.inactive')) . '</span>' ?></td>
          <td><?= h($p['track'] ?: ($p['client_track'] ?? '')) ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('projects', ['edit' => $p['id']])) ?>"><?= h(t('common.edit_short')) ?></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$projects): ?><tr><td colspan="4" class="muted"><?= h(t('projects.empty', 'Noch keine Projekte.')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
