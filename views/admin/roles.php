<?php /** @var array $roles @var array $permissions @var ?array $edit @var array $rolePerms */ ?>

<?php
$list_items = $roles;
$list_edit  = $edit;
$list_headers = [
    t('roles.col_role', 'Rolle'),
    t('struct.code_short', 'Kennung'),
    t('nav.users'),
    '',
];
$list_row_renderer = static function (array $r): string {
    $out  = '<td><strong>' . h($r['label']) . '</strong>'
          . ($r['is_system'] ? ' <span class="pill">' . h(t('roles.system_tag', 'System')) . '</span>' : '')
          . '</td>';
    $out .= '<td><code>' . h($r['name']) . '</code></td>';
    $out .= '<td class="r">' . (int) $r['user_count'] . '</td>';
    $out .= '<td class="r">';
    if (!$r['is_system']) {
        $out .= '<form method="post" action="' . h(route('role_delete')) . '" class="inline" data-confirm="'
             . h(t('roles.delete_confirm', 'Rolle loeschen?')) . '">'
             . csrf_field() . '<input type="hidden" name="id" value="' . (int) $r['id'] . '">'
             . '<button class="btn btn-sm btn-danger" type="submit">x</button></form>';
    }
    $out .= '</td>';
    return $out;
};
$list_form_partial = APP_ROOT . '/views/partials/forms/role.php';
$list_new_route    = 'admin_roles';
$list_empty_msg    = t('roles.empty', 'Noch keine Rollen.');
$list_show_new     = $edit === null;
require __DIR__ . '/../partials/list_editor.php';
