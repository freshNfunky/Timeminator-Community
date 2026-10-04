<?php /** @var array $tasks @var array $projects @var array $work_packages @var ?array $edit */ ?>
<div class="cols">
  <div class="card col-form">
    <h2><?= $edit ? 'Aufgabe bearbeiten' : 'Neue Aufgabe' ?></h2>
    <form method="post" action="<?= h(route('task_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label>Projekt
        <select name="project_id" required>
          <option value="">Projekt waehlen …</option>
          <?php foreach ($projects as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= ($edit['project_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= h($p['client_name'] . ' / ' . $p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Arbeitspaket
        <select name="work_package_id">
          <option value="">— ohne —</option>
          <?php foreach ($work_packages as $w): ?>
            <option value="<?= (int) $w['id'] ?>" data-project="<?= (int) $w['project_id'] ?>" <?= ($edit['work_package_id'] ?? 0) == $w['id'] ? 'selected' : '' ?>><?= h($w['client_name'] . ' / ' . $w['project_name'] . ' / ' . $w['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Name <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></label>
      <label>Art
        <input type="text" name="kind" list="kindlist" value="<?= h($edit['kind'] ?? '') ?>" placeholder="z. B. Entwicklung, Bearbeitung">
        <datalist id="kindlist"><option value="Entwicklung"><option value="Bearbeitung"></datalist>
      </label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> aktiv</label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Speichern</button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('tasks')) ?>">Neu</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2>Aufgaben</h2>
    <table class="table">
      <thead><tr><th>Kunde / Projekt</th><th>Arbeitspaket</th><th>Aufgabe</th><th>Art</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tasks as $t): ?>
        <tr class="<?= $t['active'] ? '' : 'muted' ?>">
          <td><?= h($t['client_name'] . ' / ' . $t['project_name']) ?></td>
          <td class="muted"><?= h($t['work_package_name'] ?? '—') ?></td>
          <td><?= h($t['name']) ?> <?= $t['active'] ? '' : '<span class="pill">inaktiv</span>' ?></td>
          <td><?= h($t['kind'] ?? '') ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('tasks', ['edit' => $t['id']])) ?>">bearb.</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tasks): ?><tr><td colspan="5" class="muted">Noch keine Aufgaben.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
