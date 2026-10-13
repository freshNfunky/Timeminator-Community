<?php /** @var array $roles @var array $permissions @var ?array $edit @var array $rolePerms */
$byGroup = [];
foreach ($permissions as $p) { $byGroup[$p['grp']][] = $p; }
?>
<div class="cols">
  <div class="card col-form">
    <h2><?= h($edit ? t('roles.edit', 'Rolle bearbeiten') : t('roles.new', 'Neue Rolle')) ?></h2>
    <form method="post" action="<?= h(route('role_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label><?= h(t('roles.label', 'Bezeichnung')) ?> <input type="text" name="label" value="<?= h($edit['label'] ?? '') ?>" required></label>
      <label><?= h(t('struct.code', 'Kennung (Code)')) ?>
        <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" <?= ($edit['is_system'] ?? 0) ? 'readonly' : '' ?> placeholder="<?= h(t('struct.code_placeholder_short', 'automatisch, falls leer')) ?>">
      </label>
      <?php if (($edit['name'] ?? '') === 'admin'): ?>
        <p class="muted"><?= h(t('roles.admin_note', 'Die Admin-Rolle besitzt grundsaetzlich alle Rechte (auch kuenftige).')) ?></p>
      <?php endif; ?>
      <fieldset class="perms">
        <legend><?= h(t('roles.permissions', 'Rechte')) ?></legend>
        <?php foreach ($byGroup as $grp => $perms): ?>
          <div class="perm-grp"><strong><?= h($grp) ?></strong>
            <?php foreach ($perms as $p): ?>
              <label class="check">
                <input type="checkbox" name="perms[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $rolePerms, true) ? 'checked' : '' ?>>
                <?= h($p['label']) ?> <code><?= h($p['code']) ?></code>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </fieldset>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('admin_roles')) ?>"><?= h(t('common.new')) ?></a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2><?= h(t('nav.roles')) ?></h2>
    <table class="table">
      <thead><tr><th><?= h(t('roles.col_role', 'Rolle')) ?></th><th><?= h(t('struct.code_short', 'Kennung')) ?></th><th class="r"><?= h(t('nav.users')) ?></th><th></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($roles as $r): ?>
        <tr>
          <td><strong><?= h($r['label']) ?></strong> <?= $r['is_system'] ? '<span class="pill">' . h(t('roles.system_tag', 'System')) . '</span>' : '' ?></td>
          <td><code><?= h($r['name']) ?></code></td>
          <td class="r"><?= (int) $r['user_count'] ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('admin_roles', ['edit' => $r['id']])) ?>"><?= h(t('common.edit_short')) ?></a></td>
          <td class="r">
            <?php if (!$r['is_system']): ?>
              <form method="post" action="<?= h(route('role_delete')) ?>" class="inline" data-confirm="<?= h(t('roles.delete_confirm', 'Rolle loeschen?')) ?>">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">x</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
