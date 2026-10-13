<?php /** @var array $users @var array $roles @var ?array $edit */ ?>
<div class="cols">
  <div class="card col-form">
    <h2><?= h($edit ? t('users.edit', 'Benutzer bearbeiten') : t('users.new', 'Neuer Benutzer')) ?></h2>
    <form method="post" action="<?= h(route('user_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label><?= h(t('login.username')) ?> <input type="text" name="username" value="<?= h($edit['username'] ?? '') ?>" required></label>
      <label><?= h(t('install.admin_name')) ?> <input type="text" name="display_name" value="<?= h($edit['display_name'] ?? '') ?>"></label>
      <label><?= h(t('users.role', 'Rolle')) ?>
        <select name="role_id">
          <option value=""><?= h(t('users.role_none', '— keine —')) ?></option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= ($edit['role_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>><?= h($r['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><?= h(t('login.password')) ?> <input type="password" name="password" autocomplete="new-password" placeholder="<?= h($edit ? t('users.pw_edit_hint', 'leer lassen = unveraendert') : t('users.pw_new_hint', 'min. 8 Zeichen')) ?>"></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> <?= h(t('struct.active')) ?></label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('admin_users')) ?>"><?= h(t('common.new')) ?></a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2><?= h(t('nav.users')) ?></h2>
    <table class="table">
      <thead><tr><th><?= h(t('users.col_user', 'Benutzer')) ?></th><th><?= h(t('users.role', 'Rolle')) ?></th><th><?= h(t('users.last_login', 'Letzter Login')) ?></th><th></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr class="<?= $u['active'] ? '' : 'muted' ?>">
          <td><strong><?= h($u['username']) ?></strong><?= $u['display_name'] ? '<br><span class="muted">' . h($u['display_name']) . '</span>' : '' ?></td>
          <td><?= h($u['role_label'] ?? '—') ?></td>
          <td><?= h(fmt_dt($u['last_login_at'])) ?></td>
          <td><?= $u['active'] ? '' : '<span class="pill">' . h(t('struct.inactive')) . '</span>' ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('admin_users', ['edit' => $u['id']])) ?>"><?= h(t('common.edit_short')) ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
