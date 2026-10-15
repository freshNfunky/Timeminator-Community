<?php /** @var array $tasks @var array $projects @var array $work_packages @var ?array $edit */ ?>

<?php
$list_items = $tasks;
$list_edit  = $edit;
$list_headers = [
    t('dash.col_client_project'),
    t('wp.single', 'Arbeitspaket'),
    t('dash.col_task'),
    t('tasks.kind', 'Art'),
];
$list_row_renderer = static function (array $tsk): string {
    $out  = '<td>' . h($tsk['client_name'] . ' / ' . $tsk['project_name']) . '</td>';
    $out .= '<td class="muted">' . h($tsk['work_package_name'] ?? '—') . '</td>';
    $out .= '<td>' . h($tsk['name'])
          . ($tsk['active'] ? '' : ' <span class="pill">' . h(t('struct.inactive')) . '</span>')
          . '</td>';
    $out .= '<td>' . h($tsk['kind'] ?? '') . '</td>';
    return $out;
};
$list_form_partial = APP_ROOT . '/views/partials/forms/task.php';
$list_new_route    = 'tasks';
$list_empty_msg    = t('tasks.empty', 'Noch keine Aufgaben.');
$list_show_new     = $edit === null;
require __DIR__ . '/../partials/list_editor.php';
