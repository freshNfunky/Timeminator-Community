<?php
declare(strict_types=1);

/**
 * Team-Sentiment-Tracking controllers (fixes #92).
 *
 * - `ctrl_sentiment_index` renders the personal recorder + history, plus a
 *   team summary when the user has `sentiment.view_team`.
 * - `ctrl_sentiment_save` upserts the user's score for a given day (defaults
 *   to today). CSRF-checked POST.
 *
 * The feature was advertised on the landing page before it existed in code;
 * this makes the promise good. Keep the UI feature-complete enough that an
 * evaluator who follows the hero copy to the app finds a working flow.
 */

function ctrl_sentiment_index(): void
{
    require_perm('sentiment.record');
    $uid = Auth::id();
    if ($uid === null) {
        redirect_route('login');
    }

    $today      = Sentiment::todayForUser($uid);
    $history    = Sentiment::historyForUser($uid, 60);
    $canTeam    = Auth::can('sentiment.view_team');
    $teamSummary = $canTeam ? Sentiment::teamSummary(30) : null;
    $teamDaily   = $canTeam ? Sentiment::teamDaily(60)   : [];

    view('sentiment/index', [
        'today'        => $today,
        'history'      => $history,
        'canTeam'      => $canTeam,
        'teamSummary'  => $teamSummary,
        'teamDaily'    => $teamDaily,
        'granularity'  => Sentiment::granularity(),
    ], t('nav.sentiment'));
}

/**
 * Bulk-Setzen einer Dimension für die aktuelle KW (v1.0 F4). Portiert aus
 * Pro #323: setzt mood oder flow auf $score, aber nur fuer Tage dieser
 * Woche, die diese Dimension noch nicht haben — niemals Overwrite.
 */
function ctrl_sentiment_fill_week(): void
{
    require_perm('sentiment.record');
    csrf_check();
    $uid = Auth::id();
    if ($uid === null) {
        redirect_route('login');
    }
    $score = Sentiment::normalizeScore(post('score'));
    $dim   = (string) post('dim');
    if ($score === null || !in_array($dim, Sentiment::DIMENSIONS, true)) {
        flash(t('sent.err_pick_any', 'Bitte setze Mood oder Flow (oder beides) auf 1-5.'), 'err');
        redirect_route('sentiment');
    }
    $monday = (new DateTimeImmutable('today'))->modify('monday this week')->format('Y-m-d');
    $today  = Sentiment::today();
    $filled = Sentiment::fillRange($uid, $monday, $today, $dim, $score);
    flash(sprintf(
        t('sent.flash_week_filled', '%d Tag(e) der laufenden Woche vorbelegt (bereits gesetzte Tage unberuehrt).'),
        count($filled)
    ));
    redirect_route('sentiment');
}

function ctrl_sentiment_save(): void
{
    require_perm('sentiment.record');
    csrf_check();

    $uid = Auth::id();
    if ($uid === null) {
        redirect_route('login');
    }

    $mood = Sentiment::normalizeScore(post('mood'));
    $flow = Sentiment::normalizeScore(post('flow'));
    if ($mood === null && $flow === null) {
        flash(t('sent.err_pick_any', 'Bitte setze Mood oder Flow (oder beides) auf 1-5.'), 'err');
        redirect_route('sentiment');
    }
    $asOf = Sentiment::normalizeDate((string) post('as_of', ''));
    $note = (string) post('note', '');

    Sentiment::record($uid, $asOf, $mood, $flow, $note);

    flash(sprintf(t('sent.flash_saved', 'Danke, Eintrag fuer %s gespeichert.'), $asOf));
    redirect_route('sentiment');
}

/**
 * JSON-API fuer den Kalender-Mood-Picker (Felix-Feedback 2026-11-04):
 * setzt mood ODER flow fuer einen bestimmten Tag und liefert den neuen
 * Zustand zurueck (kein Redirect, kein Flash).
 *
 * POST: csrf, day=YYYY-MM-DD, dim=mood|flow, score=1..5 (oder leer = clear).
 */
function ctrl_sentiment_api_save(): void
{
    require_perm('sentiment.record');
    csrf_check();
    $uid = Auth::id();
    if ($uid === null) { json_out(['ok' => false, 'error' => 'auth'], 401); }

    $day   = Sentiment::parseDay((string) post('day', ''));
    $dim   = (string) post('dim', '');
    $score = Sentiment::normalizeScore(post('score'));
    if (!$day || !in_array($dim, Sentiment::DIMENSIONS, true)) {
        json_out(['ok' => false, 'error' => 'bad_request'], 400);
    }
    Sentiment::setDimension($uid, $day, $dim, (int) ($score ?? 0));
    $row = Sentiment::forDay($uid, $day) ?: [];
    json_out([
        'ok'    => true,
        'day'   => $day,
        'dim'   => $dim,
        'score' => $score,
        'row'   => [
            'mood' => $row['mood'] ?? null,
            'flow' => $row['flow'] ?? null,
        ],
    ]);
}

/**
 * JSON-API fuer Wochen-Bulk-Befuellung (Felix-Feedback 2026-11-04,
 * "wöchentliche Meldung"): setzt eine Dimension (mood|flow) auf $score
 * fuer alle Tage von $monday bis $monday+6, UEBERSCHREIBT nicht.
 */
function ctrl_sentiment_api_fill_week(): void
{
    require_perm('sentiment.record');
    csrf_check();
    $uid = Auth::id();
    if ($uid === null) { json_out(['ok' => false, 'error' => 'auth'], 401); }

    $monday = Sentiment::parseDay((string) post('monday', ''));
    $dim    = (string) post('dim', '');
    $score  = Sentiment::normalizeScore(post('score'));
    if (!$monday || !in_array($dim, Sentiment::DIMENSIONS, true) || $score === null) {
        json_out(['ok' => false, 'error' => 'bad_request'], 400);
    }
    $end = (new DateTimeImmutable($monday))->modify('+6 days')->format('Y-m-d');
    $filled = Sentiment::fillRange($uid, $monday, $end, $dim, $score);
    json_out(['ok' => true, 'filled' => $filled, 'monday' => $monday, 'dim' => $dim, 'score' => $score]);
}

/**
 * Setzt den globalen Sentiment-Rhythmus per API (Admin-only). Fuer den
 * Kalender-Toggle Taeglich/Woechentlich: braucht admin.system, sonst 403.
 */
function ctrl_sentiment_granularity_api(): void
{
    require_perm('admin.system');
    csrf_check();
    $g = (string) post('granularity', '');
    if (!array_key_exists($g, Sentiment::GRANULARITIES)) {
        json_out(['ok' => false, 'error' => 'bad_value'], 400);
    }
    Settings::set('sentiment_granularity', $g);
    json_out(['ok' => true, 'granularity' => $g]);
}
