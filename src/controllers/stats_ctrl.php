<?php
declare(strict_types=1);

function ctrl_stats_index(): void
{
    require_perm('stats.view');
    view('stats/index', [
        'clients'  => Repo::clients(),
        'projects' => Repo::projects(),
        'scopes'   => Repo::scopes(),
        'tracks'   => Repo::distinctTracks(),
    ], 'Statistik');
}

function ctrl_stats_data(): void
{
    require_perm('stats.view');

    $from = (string) get('from', (new DateTimeImmutable('first day of this month'))->format('Y-m-d'));
    $to   = (string) get('to', (new DateTimeImmutable('now'))->format('Y-m-d'));
    $group = (string) get('group', 'project');       // client | project | track
    $gran  = (string) get('granularity', 'day');     // day | week | month
    $scopeId = (int) get('scope_id', 0);
    $allUsers = Auth::isAdmin() && get('all_users');

    $f = ['from' => $from, 'to' => $to];
    if (!$allUsers) {
        $f['user_id'] = Auth::id();
    }
    $entries = Repo::entries($f);

    $scopeInfo = null;
    if ($scopeId) {
        $scope = Repo::scope($scopeId);
        if ($scope) {
            $config = json_decode((string) $scope['config'], true) ?: [];
            $entries = Stats::applyScope($entries, $config);
            $scopeInfo = ['name' => $scope['name']];
        }
    }

    $dist = match ($group) {
        'client' => Stats::byClient($entries),
        'track'  => Stats::byTrack($entries),
        default  => Stats::byProject($entries),
    };
    $series = Stats::series($entries, $gran);

    $toHours = fn(int $m) => round($m / 60, 2);

    json_out([
        'from'  => $from,
        'to'    => $to,
        'group' => $group,
        'scope' => $scopeInfo,
        'total_hours' => $toHours(Stats::totalMinutes($entries)),
        'distribution' => [
            'labels' => array_keys($dist),
            'hours'  => array_map($toHours, array_values($dist)),
        ],
        'series' => [
            'labels' => $series['labels'],
            'hours'  => array_map($toHours, $series['minutes']),
        ],
        'tracks' => (function () use ($entries, $toHours) {
            $t = Stats::byTrack($entries);
            return ['labels' => array_keys($t), 'hours' => array_map($toHours, array_values($t))];
        })(),
    ]);
}

// ---------- Scopes (saved proof views) ----------

function ctrl_scopes_index(): void
{
    require_perm('scopes.manage');
    $editId = (int) get('edit', 0);
    view('stats/scopes', [
        'scopes'   => Repo::scopes(),
        'edit'     => $editId ? Repo::scope($editId) : null,
        'clients'  => Repo::clients(),
        'projects' => Repo::projects(),
        'tracks'   => Repo::distinctTracks(),
    ], 'Nachweis-Sichten');
}

function ctrl_scope_save(): void
{
    require_perm('scopes.manage');
    csrf_check();
    $id = post_int('id');
    $name = trim((string) post('name'));
    if ($name === '') {
        flash('Name ist erforderlich.', 'err');
        redirect_route('scopes');
    }
    $configRaw = trim((string) post('config'));
    // Validate JSON; fall back to empty object.
    $decoded = json_decode($configRaw ?: '{}', true);
    if (!is_array($decoded)) {
        flash('Konfiguration ist kein gueltiges JSON.', 'err');
        redirect_route('scopes', $id ? ['edit' => $id] : []);
    }
    Repo::saveScope([
        'name'        => $name,
        'description' => trim((string) post('description')) ?: null,
        'config'      => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
    ], $id ?: null);
    flash('Nachweis-Sicht gespeichert.');
    redirect_route('scopes');
}

function ctrl_scope_delete(): void
{
    require_perm('scopes.manage');
    csrf_check();
    $id = post_int('id');
    if ($id) {
        Repo::deleteScope($id);
        flash('Nachweis-Sicht geloescht.');
    }
    redirect_route('scopes');
}
