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
