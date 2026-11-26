<?php
declare(strict_types=1);

/**
 * Felix-Feedback 2026-11-09 (#111): persoenlicher Benutzerbereich.
 *
 * Hier liegen die Darstellungs-Praeferenzen des Users nach der
 * 3-Schichten-Trennung (siehe Memory settings-layer-classification):
 * - locale (M1.1 #191) in user_prefs.locale
 * - sentiment_granularity (Default 'week') via Sentiment::setUserGranularity
 *
 * Kein Admin-Nav; der Toggle liegt im Account-Menu neben Logout.
 */

function ctrl_profile_index(): void
{
    require_login();
    $uid = (int) Auth::id();
    view('profile/index', [
        'sentimentGranularity' => Sentiment::granularity($uid),
        'userLocale'           => UserPrefs::locale($uid),
    ], t('profile.title', 'Meine Einstellungen'));
}

function ctrl_profile_save(): void
{
    require_login();
    csrf_check();
    $uid = (int) Auth::id();

    // M1.1 #191: Sprache - leerer String = Pref loeschen (Browser-Default).
    // Nur speichern wenn das Feld tatsaechlich im Form war, damit der
    // Sentiment-Save den locale-Wert nicht ungewollt resettet.
    $saved = false;
    if (array_key_exists('locale', $_POST)) {
        $localeRaw = trim((string) post('locale', ''));
        UserPrefs::setLocale($uid, $localeRaw === '' ? null : $localeRaw);
        // Session-Cache synchronisieren, damit die Antwort schon in der
        // neuen Sprache rendert (sonst erst beim naechsten Request).
        if ($localeRaw === '') {
            if (isset($_SESSION) && is_array($_SESSION)) {
                unset($_SESSION['_locale']);
            }
        } else {
            I18n::setSession($localeRaw);
        }
        $saved = true;
    }

    if (array_key_exists('sentiment_granularity', $_POST)) {
        $g = (string) post('sentiment_granularity', 'week');
        if (!Sentiment::setUserGranularity($uid, $g)) {
            flash(t('profile.err_bad_granularity', 'Ungueltiger Rhythmus.'), 'err');
            redirect_route('profile');
        }
        $saved = true;
    }

    if ($saved) {
        flash(t('profile.flash_saved', 'Einstellungen gespeichert.'));
    }
    redirect_route('profile');
}
