<?php /** @var array $users @var array $roles @var ?array $edit @var array $terms_history @var array $carryover @var int $carryover_year @var array $holiday_catalog */ ?>

<?php
// Felix-Feedback 2026-11-08 (#98): HR + GL ohne admin.users sehen hier NUR
// die Vertragsdaten-Verwaltung (employee_terms + carryover), nicht die
// Benutzer-/Rollen-Verwaltung. Admins bekommen die Vollsicht.
$__canUsers     = Auth::can('admin.users');
$__canEmployees = Auth::can('employees.manage');

if ($__canUsers) {
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
} elseif ($__canEmployees) {
    // HR-Ansicht: knappe Liste der Mitarbeiter als Linkstarter fuer den
    // Vertragsdaten-Edit; keine Rolle/Passwort-Felder.
    ?>
    <h1 class="page-title"><?= h(t('cockpit.emp_edit_terms', 'Vertragsdaten bearbeiten')) ?></h1>
    <?php if (!$edit): ?>
      <div class="card">
        <table class="table">
          <thead><tr>
            <th><?= h(t('users.col_user', 'Benutzer')) ?></th>
            <th class="r"></th>
          </tr></thead>
          <tbody>
            <?php foreach ($users as $__u): ?>
              <tr>
                <td><strong><?= h($__u['username']) ?></strong><?= $__u['display_name'] ? '<br><span class="muted">' . h($__u['display_name']) . '</span>' : '' ?></td>
                <td class="r"><a class="btn btn-sm" href="<?= h(route('admin_users', ['edit' => (int) $__u['id']])) ?>"><?= h(t('cockpit.emp_edit_terms', 'Vertragsdaten bearbeiten')) ?></a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <?php
}

// v1.0 F1: Vertragsdaten + Jahresuebertrag unter der Haupt-Form, nur beim Edit.
// Felix-Feedback 2026-11-08: fuer employees.manage verfuegbar, auch ohne admin.users.
if ($edit && $__canEmployees) {
    require APP_ROOT . '/views/partials/forms/employee_terms.php';
}
