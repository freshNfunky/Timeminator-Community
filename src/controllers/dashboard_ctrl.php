<?php
declare(strict_types=1);

function ctrl_dashboard(): void
{
    require_login();
    $uid = Auth::id();

    // Fix #186: Timer kann Projekt-Zeit oder Interner Prozess sein.
    // Beide Repos pruefen; die UI rendert je nach Typ.
    $running         = Repo::running($uid);
    $runningProcess  = $running ? null : ProcessRepo::running($uid);
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

    // Felix-Feedback 2026-11-08: persoenliches Sentiment-Widget im
    // Dashboard (Pro-Parity). Der eigene Mood/Flow-Status fuer heute
    // wird direkt vom Dashboard aus eingetragen, nicht nur ueber den
    // Kalender. Default-Granularitaet steuert, ob der Klick auf den Rest
    // der Woche hochgezogen wird oder nur auf heute wirkt.
    $sentimentToday = Sentiment::todayForUser($uid) ?? [];
    $sentimentGran  = Sentiment::granularity();

    // Felix-Feedback 2026-11-09 (Fix #182): Timer-Card bekommt eine
    // Projektart-Combobox oberhalb der task-picker-Hierarchie, analog
    // zum Entry-Dialog. Dafuer brauchen wir die Projektart-Liste.
    $projectCategories = Repo::projectCategories(true);
    // Fix #186: Timer-Card unterstuetzt auch Interne Prozesse ohne
    // Hierarchie. Prozess-Kategorien auflisten fuer den Typ-Switcher.
    $processCategories = ProcessRepo::categories(true);

    view('dashboard', [
        'running'              => $running,
        'runningProcess'       => $runningProcess,
        'recent'               => $recent,
        'tasks'                => $tasks,
        'minToday'             => $minToday,
        'minWeek'              => $minWeek,
        'minMonth'             => $minMonth,
        'planning'             => Repo::projectPlanningSnapshot(),
        'sentimentToday'       => $sentimentToday,
        'sentimentGranularity' => $sentimentGran,
        'projectCategories'    => $projectCategories,
        'processCategories'    => $processCategories,
    ], t('nav.dashboard', 'Dashboard'));
}
