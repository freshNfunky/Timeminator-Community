<?php
declare(strict_types=1);

/**
 * Felix-Feedback 2026-11-09 (#111): persoenlicher Benutzerbereich.
 *
 * Hier liegt derzeit nur die Stimmungs-Rhythmus-Praeferenz (woechentlich
 * vs. taeglich). Default ist 'week' ("meistens reicht woechentlich"), ein
 * Nutzer kann fuer sich selbst auf 'day' umstellen - unabhaengig vom
 * System-Default, den der Admin setzt.
 *
 * Bewusst kein Admin-Nav: der Toggle liegt im Account-Menu neben Logout.
 */

function ctrl_profile_index(): void
{
    require_login();
    $uid = (int) Auth::id();
    view('profile/index', [
        'sentimentGranularity' => Sentiment::granularity($uid),
        'systemDefault'        => (string) Settings::get('sentiment_granularity', 'week'),
    ], t('profile.title', 'Meine Einstellungen'));
}

function ctrl_profile_save(): void
{
    require_login();
    csrf_check();
    $uid = (int) Auth::id();
    $g = (string) post('sentiment_granularity', 'week');
    if (!Sentiment::setUserGranularity($uid, $g)) {
        flash(t('profile.err_bad_granularity', 'Ungueltiger Rhythmus.'), 'err');
    } else {
        flash(t('profile.flash_saved', 'Einstellungen gespeichert.'));
    }
    redirect_route('profile');
}
