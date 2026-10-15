<?php /** @var array $clients @var ?array $edit @var array $tracks */ ?>

<?php
$list_items = $clients;
$list_edit  = $edit;
$list_headers = [
    t('struct.name', 'Name'),
    t('struct.code_short', 'Kennung'),
    t('clients.track_short', 'Gleis'),
    '',
];
$list_row_renderer = static function (array $c): string {
    $colorAttr = h(strtolower((string) ($c['color'] ?: '')));
    $out  = '<td><span class="dot" data-color="' . $colorAttr . '"></span>' . h($c['name']) . '</td>';
    $out .= '<td><code>' . h($c['code']) . '</code></td>';
    $out .= '<td>' . h($c['track'] ?? '') . '</td>';
    $out .= '<td>' . ($c['active'] ? '' : '<span class="pill">' . h(t('struct.inactive', 'inaktiv')) . '</span>') . '</td>';
    return $out;
};
$list_form_partial = APP_ROOT . '/views/partials/forms/client.php';
$list_new_route    = 'clients';
$list_empty_msg    = t('clients.empty', 'Noch keine Kunden.');
$list_show_new     = $edit === null;
require __DIR__ . '/../partials/list_editor.php';
