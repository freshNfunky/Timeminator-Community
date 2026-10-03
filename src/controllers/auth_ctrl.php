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
        $ip = client_ip();

        $lock = LoginThrottle::lockoutReason($username, $ip);
        if ($lock !== null) {
            LoginThrottle::record($username !== '' ? $username : null, null, $ip, false);
            $retry = LoginThrottle::retryAfterSeconds($username, $ip);
            http_response_code(429);
            if (!headers_sent()) {
                header('Retry-After: ' . $retry);
            }
            $error = $lock;
        } elseif (Auth::attempt($username, $password)) {
            $uid = Auth::id();
            if ($uid !== null) {
                LoginThrottle::clearForUser($uid);
            }
            LoginThrottle::record($username, $uid, $ip, true);
            flash('Willkommen zurueck, ' . (Auth::user()['display_name'] ?: $username) . '.');
            redirect_route('dashboard');
        } else {
            $existing = $username !== ''
                ? DB::one('SELECT id FROM users WHERE username = ?', [$username])
                : null;
            $uid = $existing ? (int) $existing['id'] : null;
            LoginThrottle::record($username !== '' ? $username : null, $uid, $ip, false);
            usleep(LoginThrottle::backoffMicroseconds($username));
            $error = 'Benutzername oder Passwort falsch.';
        }
        if (random_int(0, 25) === 0) {
            LoginThrottle::prune();
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

/**
 * Hide the right-side promo sidebar for this browser session. Any logged-in
 * user may dismiss their own view; the next login shows it again. There is
 * no server-side persistence beyond `$_SESSION` because the whole slot is
 * cheap and can be hidden by the operator via `promo_sidebar_enabled` in
 * config.php when a stricter opt-out is needed.
 */
function ctrl_promo_dismiss(): void
{
    require_login();
    csrf_check();
    $_SESSION['promo_sidebar_dismissed'] = true;
    redirect(referrer_or_default(route('dashboard')));
}
