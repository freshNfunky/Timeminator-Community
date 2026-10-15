<?php /** @var array $work_packages @var array $projects @var ?array $edit */ ?>

<?php
$list_items = $work_packages;
$list_edit  = $edit;
$list_headers = [
    t('dash.col_client_project'),
    t('struct.code_short', 'Kennung'),
    t('struct.name'),
];
$list_row_renderer = static function (array $w): string {
    $out  = '<td>' . h($w['client_name'] . ' / ' . $w['project_name']) . '</td>';
    $out .= '<td><code>' . h($w['code']) . '</code></td>';
    $out .= '<td>' . h($w['name'])
          . ($w['active'] ? '' : ' <span class="pill">' . h(t('struct.inactive')) . '</span>')
          . '</td>';
    return $out;
};
$list_form_partial = APP_ROOT . '/views/partials/forms/workpackage.php';
$list_new_route    = 'workpackages';
$list_empty_msg    = t('wp.empty', 'Noch keine Arbeitspakete.');
$list_show_new     = $edit === null;
require __DIR__ . '/../partials/list_editor.php';
