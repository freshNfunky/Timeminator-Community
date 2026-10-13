<?php /** @var array $tasks @var array $projects @var array $work_packages @var ?array $edit */ ?>
<div class="cols">
  <div class="card col-form">
    <h2><?= h($edit ? t('tasks.edit', 'Aufgabe bearbeiten') : t('tasks.new', 'Neue Aufgabe')) ?></h2>
    <form method="post" action="<?= h(route('task_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label><?= h(t('projects.col_project')) ?>
        <select name="project_id" required>
          <option value=""><?= h(t('wp.pick_project')) ?></option>
          <?php foreach ($projects as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= ($edit['project_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= h($p['client_name'] . ' / ' . $p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><?= h(t('wp.single', 'Arbeitspaket')) ?>
        <select name="work_package_id">
          <option value=""><?= h(t('tasks.wp_none', '— ohne —')) ?></option>
          <?php foreach ($work_packages as $w): ?>
            <option value="<?= (int) $w['id'] ?>" data-project="<?= (int) $w['project_id'] ?>" <?= ($edit['work_package_id'] ?? 0) == $w['id'] ? 'selected' : '' ?>><?= h($w['client_name'] . ' / ' . $w['project_name'] . ' / ' . $w['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><?= h(t('struct.name')) ?> <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></label>
      <label><?= h(t('tasks.kind', 'Art')) ?>
        <input type="text" name="kind" list="kindlist" value="<?= h($edit['kind'] ?? '') ?>" placeholder="<?= h(t('tasks.kind_placeholder', 'z. B. Entwicklung, Bearbeitung')) ?>">
        <datalist id="kindlist"><option value="Entwicklung"><option value="Bearbeitung"></datalist>
      </label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> <?= h(t('struct.active')) ?></label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('tasks')) ?>"><?= h(t('common.new')) ?></a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2><?= h(t('nav.tasks')) ?></h2>
    <table class="table">
      <thead><tr><th><?= h(t('dash.col_client_project')) ?></th><th><?= h(t('wp.single', 'Arbeitspaket')) ?></th><th><?= h(t('dash.col_task')) ?></th><th><?= h(t('tasks.kind', 'Art')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tasks as $tsk): ?>
        <tr class="<?= $tsk['active'] ? '' : 'muted' ?>">
          <td><?= h($tsk['client_name'] . ' / ' . $tsk['project_name']) ?></td>
          <td class="muted"><?= h($tsk['work_package_name'] ?? '—') ?></td>
          <td><?= h($tsk['name']) ?> <?= $tsk['active'] ? '' : '<span class="pill">' . h(t('struct.inactive')) . '</span>' ?></td>
          <td><?= h($tsk['kind'] ?? '') ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('tasks', ['edit' => $tsk['id']])) ?>"><?= h(t('common.edit_short')) ?></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tasks): ?><tr><td colspan="5" class="muted"><?= h(t('tasks.empty', 'Noch keine Aufgaben.')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
