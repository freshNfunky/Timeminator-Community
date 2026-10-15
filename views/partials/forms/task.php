<?php /** @var ?array $edit @var array $projects @var array $work_packages */ ?>
<h3 class="form-inline-title"><?= h($edit ? t('tasks.edit', 'Aufgabe bearbeiten') : t('tasks.new', 'Neue Aufgabe')) ?></h3>
<form method="post" action="<?= h(route('task_save')) ?>" class="form-inline">
  <?= csrf_field() ?>
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
  <label><?= h(t('projects.col_project')) ?>
    <select name="project_id" required>
      <option value=""><?= h(t('wp.pick_project')) ?></option>
      <?php foreach (($projects ?? []) as $__p): ?>
        <option value="<?= (int) $__p['id'] ?>" <?= ($edit['project_id'] ?? 0) == $__p['id'] ? 'selected' : '' ?>><?= h($__p['client_name'] . ' / ' . $__p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= h(t('wp.single', 'Arbeitspaket')) ?>
    <select name="work_package_id">
      <option value=""><?= h(t('tasks.wp_none', '— ohne —')) ?></option>
      <?php foreach (($work_packages ?? []) as $__w): ?>
        <option value="<?= (int) $__w['id'] ?>" data-project="<?= (int) $__w['project_id'] ?>" <?= ($edit['work_package_id'] ?? 0) == $__w['id'] ? 'selected' : '' ?>><?= h($__w['client_name'] . ' / ' . $__w['project_name'] . ' / ' . $__w['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= h(t('struct.name')) ?> <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></label>
  <label><?= h(t('tasks.kind', 'Art')) ?>
    <input type="text" name="kind" list="kindlist-<?= $edit ? (int) $edit['id'] : 'new' ?>" value="<?= h($edit['kind'] ?? '') ?>" placeholder="<?= h(t('tasks.kind_placeholder', 'z. B. Entwicklung, Bearbeitung')) ?>">
    <datalist id="kindlist-<?= $edit ? (int) $edit['id'] : 'new' ?>"><option value="Entwicklung"><option value="Bearbeitung"></datalist>
  </label>
  <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> <?= h(t('struct.active')) ?></label>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
    <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('tasks')) ?>"><?= h(t('common.cancel', 'abbrechen')) ?></a><?php endif; ?>
  </div>
</form>
