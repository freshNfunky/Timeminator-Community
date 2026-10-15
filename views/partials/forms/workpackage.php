<?php /** @var ?array $edit @var array $projects */ ?>
<h3 class="form-inline-title"><?= h($edit ? t('wp.edit', 'Arbeitspaket bearbeiten') : t('wp.new', 'Neues Arbeitspaket')) ?></h3>
<p class="muted"><?= t('wp.intro', 'Arbeitspakete (en: <em>Workpackages</em>) sitzen zwischen Projekt und Aufgabe. Beim Upgrade bekommt jedes Projekt automatisch ein Paket <strong>Allgemein</strong>.') /* contains <em>/<strong>, intentional */ ?></p>
<form method="post" action="<?= h(route('workpackage_save')) ?>" class="form-inline">
  <?= csrf_field() ?>
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
  <label><?= h(t('projects.col_project', 'Projekt')) ?>
    <select name="project_id" required>
      <option value=""><?= h(t('wp.pick_project', 'Projekt waehlen …')) ?></option>
      <?php foreach (($projects ?? []) as $__p): ?>
        <option value="<?= (int) $__p['id'] ?>" <?= ($edit['project_id'] ?? 0) == $__p['id'] ? 'selected' : '' ?>><?= h($__p['client_name'] . ' / ' . $__p['name']) ?></option>
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
    <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('workpackages')) ?>"><?= h(t('common.cancel', 'abbrechen')) ?></a><?php endif; ?>
  </div>
</form>
