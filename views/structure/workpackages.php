<?php /** @var array $work_packages @var array $projects @var ?array $edit */ ?>
<div class="cols">
  <div class="card col-form">
    <h2><?= h($edit ? t('wp.edit', 'Arbeitspaket bearbeiten') : t('wp.new', 'Neues Arbeitspaket')) ?></h2>
    <p class="muted"><?= t('wp.intro', 'Arbeitspakete (en: <em>Workpackages</em>) sitzen zwischen Projekt und Aufgabe. Beim Upgrade bekommt jedes Projekt automatisch ein Paket <strong>Allgemein</strong>.') /* contains <em>/<strong>, intentional */ ?></p>
    <form method="post" action="<?= h(route('workpackage_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label><?= h(t('projects.col_project', 'Projekt')) ?>
        <select name="project_id" required>
          <option value=""><?= h(t('wp.pick_project', 'Projekt waehlen …')) ?></option>
          <?php foreach ($projects as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= ($edit['project_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= h($p['client_name'] . ' / ' . $p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><?= h(t('struct.name')) ?> <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required placeholder="<?= h(t('wp.name_placeholder', 'z. B. Phase 1 — Konzept')) ?>"></label>
      <label><?= h(t('struct.code_short', 'Kennung')) ?>
        <input type="text" name="code" value="<?= h($edit['code'] ?? '') ?>" placeholder="<?= h(t('struct.code_auto', 'auto')) ?>">
        <small class="muted"><?= h(t('wp.code_unique_hint', 'Eindeutig innerhalb des Projekts.')) ?></small>
      </label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> <?= h(t('struct.active')) ?></label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('workpackages')) ?>"><?= h(t('common.new')) ?></a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2><?= h(t('nav.workpackages')) ?></h2>
    <table class="table">
      <thead><tr><th><?= h(t('dash.col_client_project')) ?></th><th><?= h(t('struct.code_short', 'Kennung')) ?></th><th><?= h(t('struct.name')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($work_packages as $w): ?>
        <tr class="<?= $w['active'] ? '' : 'muted' ?>">
          <td><?= h($w['client_name'] . ' / ' . $w['project_name']) ?></td>
          <td><code><?= h($w['code']) ?></code></td>
          <td><?= h($w['name']) ?> <?= $w['active'] ? '' : '<span class="pill">' . h(t('struct.inactive')) . '</span>' ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('workpackages', ['edit' => $w['id']])) ?>"><?= h(t('common.edit_short')) ?></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$work_packages): ?><tr><td colspan="4" class="muted"><?= h(t('wp.empty', 'Noch keine Arbeitspakete.')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
