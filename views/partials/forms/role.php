<?php /** @var ?array $edit @var array $permissions @var array $rolePerms */
$__byGroup = [];
foreach (($permissions ?? []) as $__p) { $__byGroup[$__p['grp']][] = $__p; }
// When creating a NEW role the caller may not have `$rolePerms` set for
// that scope; empty array is the safe default (nothing checked).
$__rolePermsForForm = $edit ? (array) ($rolePerms ?? []) : [];
?>
<h3 class="form-inline-title"><?= h($edit ? t('roles.edit', 'Rolle bearbeiten') : t('roles.new', 'Neue Rolle')) ?></h3>
<form method="post" action="<?= h(route('role_save')) ?>" class="form-inline">
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
    <?php foreach ($__byGroup as $__grp => $__perms): ?>
      <div class="perm-grp"><strong><?= h($__grp) ?></strong>
        <?php foreach ($__perms as $__pm): ?>
          <label class="check">
            <input type="checkbox" name="perms[]" value="<?= (int) $__pm['id'] ?>" <?= in_array((int) $__pm['id'], $__rolePermsForForm, true) ? 'checked' : '' ?>>
            <?= h($__pm['label']) ?> <code><?= h($__pm['code']) ?></code>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </fieldset>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
    <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('admin_roles')) ?>"><?= h(t('common.cancel', 'abbrechen')) ?></a><?php endif; ?>
  </div>
</form>
