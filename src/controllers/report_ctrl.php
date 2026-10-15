<?php
declare(strict_types=1);

/**
 * Projekt-Report controllers (Pro-Sync B.3, #112).
 */

function ctrl_report_projects(): void
{
    require_perm('structure.manage');
    [$clientFilter, $from, $to] = report_projects_filter_from_request();
    $rows = ProjectReport::rows($clientFilter, $from, $to);
    // Empty-state nudge: when the user has active projects but none of
    // them carry a target yet, link straight to each project's edit form
    // instead of making them hunt around.
    $plannable = [];
    if (empty($rows)) {
        $plannable = array_values(array_filter(
            Repo::projects(true),
            static fn(array $p) => $p['target_hours'] === null
        ));
    }
    view('report/projects', [
        'rows'         => $rows,
        'clients'      => Repo::clients(true),
        'filter_client' => $clientFilter,
        'filter_from'  => $from ?? '',
        'filter_to'    => $to ?? '',
        'plannable'    => $plannable,
    ], t('report.projects_title', 'Projekt-Report'));
}

function ctrl_report_projects_export(): void
{
    require_perm('structure.manage');
    [$clientFilter, $from, $to] = report_projects_filter_from_request();
    $rows = ProjectReport::rows($clientFilter, $from, $to);

    $filename = 'timeminator-project-report-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'project_id', 'project_name', 'client_name',
        'target_hours', 'actual_hours', 'diff_hours',
        'percent', 'status',
    ], ',', '"', '\\');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['name'], $r['client_name'],
            number_format($r['target_hours'], 2, '.', ''),
            number_format($r['actual_minutes'] / 60, 2, '.', ''),
            number_format($r['diff_minutes']   / 60, 2, '.', ''),
            $r['percent'], $r['status'],
        ], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

/**
 * Normalize ?client / ?from / ?to from the request. Invalid dates
 * collapse to null so the service can leave the clause out of the SQL.
 *
 * @return array{0:?int,1:?string,2:?string}
 */
function report_projects_filter_from_request(): array
{
    $clientFilter = (int) get('client', 0);
    if ($clientFilter <= 0) { $clientFilter = null; }

    $from = trim((string) get('from', ''));
    $to   = trim((string) get('to',   ''));
    if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = ''; }
    if ($to   !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = ''; }
    return [
        $clientFilter,
        $from === '' ? null : $from,
        $to   === '' ? null : $to,
    ];
}
