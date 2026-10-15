<?php /** @var array $projects @var array $clients @var ?array $edit @var array $tracks */ ?>

<?php
$list_items = $projects;
$list_edit  = $edit;
$list_headers = [
    t('struct.client'),
    t('projects.col_project', 'Projekt'),
    t('clients.track_short'),
    t('planning.col_target', 'Soll (h)'),
];
$list_row_renderer = static function (array $p): string {
    $colorAttr = h(strtolower((string) ($p['color'] ?: ($p['client_color'] ?: ''))));
    $out  = '<td>' . h($p['client_name']) . '</td>';
    $out .= '<td><span class="dot" data-color="' . $colorAttr . '"></span>'
          . '<a href="' . h(route('project_show', ['id' => (int) $p['id']])) . '">' . h($p['name']) . '</a>'
          . ($p['active'] ? '' : ' <span class="pill">' . h(t('struct.inactive')) . '</span>')
          . '</td>';
    $out .= '<td>' . h($p['track'] ?: ($p['client_track'] ?? '')) . '</td>';
    $out .= '<td class="r">' . ($p['target_hours'] !== null
        ? h(number_format((float) $p['target_hours'], 2, ',', '.') . ' h')
        : '<span class="muted">—</span>') . '</td>';
    return $out;
};
$list_form_partial = APP_ROOT . '/views/partials/forms/project.php';
$list_new_route    = 'projects';
$list_empty_msg    = t('projects.empty', 'Noch keine Projekte.');
$list_show_new     = $edit === null;
require __DIR__ . '/../partials/list_editor.php';
