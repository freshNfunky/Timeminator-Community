<?php /** @var array $roles @var array $permissions @var ?array $edit @var array $rolePerms */
$byGroup = [];
foreach ($permissions as $p) { $byGroup[$p['grp']][] = $p; }
?>
<div class="cols">
  <div class="card col-form">
    <h2><?= $edit ? 'Rolle bearbeiten' : 'Neue Rolle' ?></h2>
    <form method="post" action="<?= h(route('role_save')) ?>">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label>Bezeichnung <input type="text" name="label" value="<?= h($edit['label'] ?? '') ?>" required></label>
      <label>Kennung (Code)
        <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" <?= ($edit['is_system'] ?? 0) ? 'readonly' : '' ?> placeholder="automatisch, falls leer">
      </label>
      <?php if (($edit['name'] ?? '') === 'admin'): ?>
        <p class="muted">Die Admin-Rolle besitzt grundsaetzlich alle Rechte (auch kuenftige).</p>
      <?php endif; ?>
      <fieldset class="perms">
        <legend>Rechte</legend>
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
        <button class="btn btn-primary" type="submit">Speichern</button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('admin_roles')) ?>">Neu</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card col-list">
    <h2>Rollen</h2>
    <table class="table">
      <thead><tr><th>Rolle</th><th>Kennung</th><th class="r">Benutzer</th><th></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($roles as $r): ?>
        <tr>
          <td><strong><?= h($r['label']) ?></strong> <?= $r['is_system'] ? '<span class="pill">System</span>' : '' ?></td>
          <td><code><?= h($r['name']) ?></code></td>
          <td class="r"><?= (int) $r['user_count'] ?></td>
          <td class="r"><a class="btn btn-sm" href="<?= h(route('admin_roles', ['edit' => $r['id']])) ?>">bearb.</a></td>
          <td class="r">
            <?php if (!$r['is_system']): ?>
              <form method="post" action="<?= h(route('role_delete')) ?>" class="inline" onsubmit="return confirm('Rolle loeschen?')">
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
