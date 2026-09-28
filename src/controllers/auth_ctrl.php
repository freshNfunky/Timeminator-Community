<?php
declare(strict_types=1);

function ctrl_login(): void
{
    if (Auth::check()) {
        redirect_route('dashboard');
    }
    $error = null;
    if (is_post()) {
        csrf_check();
        $username = trim((string) post('username'));
        $password = (string) post('password');
        $ip       = LoginThrottle::clientIp();

        $throttle = LoginThrottle::check($ip, $username);
        if (!$throttle['allowed']) {
            $minutes = (int) ceil($throttle['retry_after'] / 60);
            error_log(sprintf(
                '[auth] login blocked by throttle (%s) user=%s ip=%s retry_after=%ds',
                $throttle['reason'],
                $username !== '' ? $username : '(empty)',
                $ip !== '' ? $ip : '(unknown)',
                $throttle['retry_after']
            ));
            http_response_code(429);
            header('Retry-After: ' . $throttle['retry_after']);
            $error = 'Zu viele Fehlversuche. Bitte in etwa '
                . max(1, $minutes) . ' Minute(n) erneut versuchen.';
        } elseif (Auth::attempt($username, $password)) {
            LoginThrottle::recordSuccess($ip, $username);
            flash('Willkommen zurueck, ' . (Auth::user()['display_name'] ?: $username) . '.');
            redirect_route('dashboard');
        } else {
            LoginThrottle::recordFailure($ip, $username);
            $error = 'Benutzername oder Passwort falsch.';
            usleep(300000); // small delay on top of the throttle
        }
    }
    view_bare('login', ['error' => $error], 'Anmelden');
}

function ctrl_logout(): void
{
    require_login();
    Auth::logout();
    redirect_route('login');
}
