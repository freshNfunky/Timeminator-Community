<?php /** @var array $users @var array $roles @var ?array $edit @var array $terms_history @var array $carryover @var int $carryover_year @var array $holiday_catalog */ ?>

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

// v1.0 F1: Vertragsdaten + Jahresuebertrag unter der Haupt-Form, nur beim Edit.
if ($edit) {
    require APP_ROOT . '/views/partials/forms/employee_terms.php';
}
