<?php
declare(strict_types=1);

/**
 * Pro-only feature teasers — Community edition.
 *
 * Each handler renders a dimmed read-only mockup of a feature that only
 * exists in Timeminator Pro (invoicing, quotes, budget tracking, unlimited
 * roles). The right-edge upsell banner links out to pro_url. No data is
 * mutated here; these pages only read what Community already stores so the
 * mockup looks plausible without inventing fixtures.
 */

/** Shared pro_url + sample strings used by every teaser view. */
function pro_teaser_context(): array
{
    return [
        'pro_url' => (string) cfg('pro_url', 'https://timeminator.felixschaller.com'),
    ];
}

function ctrl_pro_invoices(): void
{
    require_login();
    $ctx = pro_teaser_context();
    // Group the user's recent entries by client so the mock invoice list
    // has plausible numbers instead of lorem-ipsum.
    $rows = Repo::entries(['user_id' => Auth::id(), 'limit' => 200]);
    $groups = [];
    foreach ($rows as $r) {
        $k = (string) ($r['client_name'] ?? '—');
        if (!isset($groups[$k])) {
            $groups[$k] = ['client' => $k, 'minutes' => 0, 'projects' => []];
        }
        $groups[$k]['minutes'] += (int) $r['duration_min'];
        $groups[$k]['projects'][(string) ($r['project_name'] ?? '—')] = true;
    }
    $groups = array_values($groups);
    usort($groups, static fn($a, $b) => $b['minutes'] <=> $a['minutes']);
    $groups = array_slice($groups, 0, 6);
    view('pro/invoices', $ctx + ['groups' => $groups], 'Rechnungen');
}

function ctrl_pro_offers(): void
{
    require_login();
    view('pro/offers', pro_teaser_context(), 'Angebote');
}

function ctrl_pro_budget(): void
{
    require_login();
    $ctx = pro_teaser_context();
    // Pull the user's own projects for the mockup table; each gets a
    // deterministic sham budget derived from the project id so the numbers
    // don't jitter between renders.
    $projects = array_slice(Repo::projects(), 0, 8);
    $mock = [];
    foreach ($projects as $p) {
        $pid = (int) $p['id'];
        $budgetH = 20 + ($pid * 7) % 160;
        $usedH   = max(0, (int) round($budgetH * ((($pid * 13) % 90) / 100.0)));
        $mock[] = [
            'client'   => (string) ($p['client_name'] ?? '—'),
            'project'  => (string) ($p['name'] ?? '—'),
            'budget_h' => $budgetH,
            'used_h'   => $usedH,
            'pct'      => $budgetH > 0 ? min(100, (int) round($usedH / $budgetH * 100)) : 0,
        ];
    }
    view('pro/budget', $ctx + ['rows' => $mock], 'Budget / Soll');
}

function ctrl_pro_roles(): void
{
    require_login();
    view('pro/roles', pro_teaser_context(), 'Rollen Pro');
}
