<?php /** @var array $users @var array $roles @var ?array $edit */ ?>
<div class="cols">
  <div class="card col-form">
    <h2><?= $edit ? 'Benutzer bearbeiten' : 'Neuer Benutzer' ?></h2>
    <form method="post" action="<?= h(route('user_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label>Benutzername <input type="text" name="username" value="<?= h($edit['username'] ?? '') ?>" required></label>
      <label>Anzeigename <input type="text" name="display_name" value="<?= h($edit['display_name'] ?? '') ?>"></label>
      <label>Rolle
        <select name="role_id">
          <option value="">— keine —</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= ($edit['role_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>><?= h($r['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Passwort <input type="password" name="password" autocomplete="new-password" placeholder="<?= $edit ? 'leer lassen = unveraendert' : 'min. 8 Zeichen' ?>"></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> aktiv</label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Speichern</button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('admin_users')) ?>">Neu</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2>Benutzer</h2>
    <table class="table">
      <thead><tr><th>Benutzer</th><th>Rolle</th><th>Letzter Login</th><th></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr class="<?= $u['active'] ? '' : 'muted' ?>">
          <td><strong><?= h($u['username']) ?></strong><?= $u['display_name'] ? '<br><span class="muted">' . h($u['display_name']) . '</span>' : '' ?></td>
          <td><?= h($u['role_label'] ?? '—') ?></td>
          <td><?= h(fmt_dt($u['last_login_at'])) ?></td>
          <td><?= $u['active'] ? '' : '<span class="pill">inaktiv</span>' ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('admin_users', ['edit' => $u['id']])) ?>">bearb.</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
