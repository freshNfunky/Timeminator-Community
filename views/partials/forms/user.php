<?php /** @var ?array $edit @var array $roles */ ?>
<h3 class="form-inline-title"><?= h($edit ? t('users.edit', 'Benutzer bearbeiten') : t('users.new', 'Neuer Benutzer')) ?></h3>
<form method="post" action="<?= h(route('user_save')) ?>" class="form-inline">
  <?= csrf_field() ?>
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
  <label><?= h(t('login.username')) ?> <input type="text" name="username" value="<?= h($edit['username'] ?? '') ?>" required></label>
  <label><?= h(t('install.admin_name')) ?> <input type="text" name="display_name" value="<?= h($edit['display_name'] ?? '') ?>"></label>
  <label><?= h(t('users.role', 'Rolle')) ?>
    <select name="role_id">
      <option value=""><?= h(t('users.role_none', '— keine —')) ?></option>
      <?php foreach (($roles ?? []) as $__r): ?>
        <option value="<?= (int) $__r['id'] ?>" <?= ($edit['role_id'] ?? 0) == $__r['id'] ? 'selected' : '' ?>><?= h($__r['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= h(t('login.password')) ?> <input type="password" name="password" autocomplete="new-password" placeholder="<?= h($edit ? t('users.pw_edit_hint', 'leer lassen = unveraendert') : t('users.pw_new_hint', 'min. 8 Zeichen')) ?>"></label>
  <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> <?= h(t('struct.active')) ?></label>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
    <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('admin_users')) ?>"><?= h(t('common.cancel', 'abbrechen')) ?></a><?php endif; ?>
  </div>
</form>
