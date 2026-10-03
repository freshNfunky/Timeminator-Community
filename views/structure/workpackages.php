<?php /** @var array $work_packages @var array $projects @var ?array $edit */ ?>
<div class="cols">
  <div class="card col-form">
    <h2><?= $edit ? 'Arbeitspaket bearbeiten' : 'Neues Arbeitspaket' ?></h2>
    <p class="muted">Arbeitspakete (en: <em>Workpackages</em>) sitzen zwischen Projekt und Aufgabe. Beim Upgrade bekommt jedes Projekt automatisch ein Paket <strong>Allgemein</strong>.</p>
    <form method="post" action="<?= h(route('workpackage_save')) ?>">
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
      <label>Name <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required placeholder="z. B. Phase 1 — Konzept"></label>
      <label>Kennung
        <input type="text" name="code" value="<?= h($edit['code'] ?? '') ?>" placeholder="auto">
        <small class="muted">Eindeutig innerhalb des Projekts.</small>
      </label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> aktiv</label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Speichern</button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('workpackages')) ?>">Neu</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2>Arbeitspakete</h2>
    <table class="table">
      <thead><tr><th>Kunde / Projekt</th><th>Kennung</th><th>Name</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($work_packages as $w): ?>
        <tr class="<?= $w['active'] ? '' : 'muted' ?>">
          <td><?= h($w['client_name'] . ' / ' . $w['project_name']) ?></td>
          <td><code><?= h($w['code']) ?></code></td>
          <td><?= h($w['name']) ?> <?= $w['active'] ? '' : '<span class="pill">inaktiv</span>' ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('workpackages', ['edit' => $w['id']])) ?>">bearb.</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$work_packages): ?><tr><td colspan="4" class="muted">Noch keine Arbeitspakete.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
