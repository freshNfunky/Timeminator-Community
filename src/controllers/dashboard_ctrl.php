<?php
declare(strict_types=1);

function ctrl_dashboard(): void
{
    require_login();
    $uid = Auth::id();

    $running = Repo::running($uid);
    $recent = Repo::entries(['user_id' => $uid, 'limit' => 8]);

    // Totals for today / this week / this month (work-day aware).
    $now = new DateTimeImmutable('now');
    $monthStart = $now->format('Y-m-01');
    $entriesMonth = Repo::entries([
        'user_id' => $uid,
        'from'    => $monthStart,
        'to'      => $now->format('Y-m-d'),
    ]);

    $today = $now->format('Y-m-d');
    $isoWeek = $now->format('o-\WW');

    $minToday = 0; $minWeek = 0; $minMonth = 0;
    foreach ($entriesMonth as $e) {
        $wd = Stats::workDay((string) $e['start_ts']);
        $wdDt = new DateTimeImmutable($wd);
        $min = (int) $e['duration_min'];
        $minMonth += $min;
        if ($wd === $today) $minToday += $min;
        if ($wdDt->format('o-\WW') === $isoWeek) $minWeek += $min;
    }

    // Active tasks for the quick-start timer picker.
    $tasks = Repo::tasks(true);

    view('dashboard', [
        'running'   => $running,
        'recent'    => $recent,
        'tasks'     => $tasks,
        'minToday'  => $minToday,
        'minWeek'   => $minWeek,
        'minMonth'  => $minMonth,
    ], 'Dashboard');
}
