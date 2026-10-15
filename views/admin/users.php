<?php /** @var array $users @var array $roles @var ?array $edit */ ?>

<?php
$list_items = $users;
$list_edit  = $edit;
$list_headers = [
    t('users.col_user', 'Benutzer'),
    t('users.role', 'Rolle'),
    t('users.last_login', 'Letzter Login'),
    '',
];
$list_row_renderer = static function (array $u): string {
    $out  = '<td><strong>' . h($u['username']) . '</strong>'
          . ($u['display_name'] ? '<br><span class="muted">' . h($u['display_name']) . '</span>' : '')
          . '</td>';
    $out .= '<td>' . h($u['role_label'] ?? '—') . '</td>';
    $out .= '<td>' . h(fmt_dt($u['last_login_at'])) . '</td>';
    $out .= '<td>' . ($u['active'] ? '' : '<span class="pill">' . h(t('struct.inactive')) . '</span>') . '</td>';
    return $out;
};
$list_form_partial = APP_ROOT . '/views/partials/forms/user.php';
$list_new_route    = 'admin_users';
$list_empty_msg    = t('users.empty', 'Noch keine Benutzer.');
$list_show_new     = $edit === null;
require __DIR__ . '/../partials/list_editor.php';
