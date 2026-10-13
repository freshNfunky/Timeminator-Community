<?php
declare(strict_types=1);

/**
 * Public password-reset flow (#107).
 *
 * Two routes: `reset_request` (form takes a username OR an e-mail and
 * silently promises "we sent a mail if that account exists" — no
 * enumeration), and `reset_form` (consumes the token from the URL, sets a
 * new password). Behaviour matches the Pro flow but trimmed down: no
 * administrator-triggered resets here, no SMS second factor.
 */

function ctrl_reset_request(): void
{
    if (Auth::check()) {
        redirect_route('dashboard');
    }

    $sent  = (int) get('sent', 0) === 1;
    $error = null;

    if (is_post()) {
        csrf_check();
        $identifier = trim((string) post('identifier'));
        $ip         = client_ip();

        // Lockout re-uses the login throttle so attackers who learned our
        // username list from brute-forcing login can't just shift to this
        // endpoint to keep probing.
        $lock = LoginThrottle::lockoutReason($identifier, $ip);
        if ($lock !== null) {
            http_response_code(429);
            if (!headers_sent()) {
                header('Retry-After: ' . LoginThrottle::retryAfterSeconds($identifier, $ip));
            }
            $error = $lock;
        } else {
            $user = password_reset_lookup_user($identifier);
            if ($user !== null && !PasswordReset::isRateLimited((int) $user['id'])) {
                $token = PasswordReset::create((int) $user['id'], $ip);
                password_reset_send_mail($user, $token);
            } else {
                LoginThrottle::record($identifier !== '' ? $identifier : null, null, $ip, false);
                usleep(LoginThrottle::backoffMicroseconds($identifier));
            }
            // Always redirect to the same "if the account exists, mail sent"
            // page so the response is identical whether or not the identifier
            // matched a user. Prevents account enumeration.
            redirect_route('reset_request', ['sent' => 1]);
        }
    }

    view_bare('password_reset/request', [
        'sent'  => $sent,
        'error' => $error,
    ], t('reset.request_title', 'Passwort zuruecksetzen'));
}

function ctrl_reset_form(): void
{
    if (Auth::check()) {
        redirect_route('dashboard');
    }

    $token = trim((string) (is_post() ? post('token') : get('token', '')));
    $row   = $token !== '' ? PasswordReset::find($token) : null;

    if ($row === null) {
        http_response_code(410);
        view_bare('password_reset/form', [
            'token'   => $token,
            'invalid' => true,
            'error'   => null,
        ], t('reset.form_title', 'Neues Passwort setzen'));
        return;
    }

    $error = null;
    if (is_post()) {
        csrf_check();
        $pw1 = (string) post('password');
        $pw2 = (string) post('password_confirm');
        if (strlen($pw1) < 8) {
            $error = t('reset.err_password_short', 'Das Passwort muss mindestens 8 Zeichen haben.');
        } elseif ($pw1 !== $pw2) {
            $error = t('reset.err_password_mismatch', 'Die Passwoerter stimmen nicht ueberein.');
        } elseif (!PasswordReset::consume($token, $pw1)) {
            // Token raced to expiry/consumption between find() and consume() — rare.
            http_response_code(410);
            view_bare('password_reset/form', [
                'token'   => $token,
                'invalid' => true,
                'error'   => null,
            ], t('reset.form_title', 'Neues Passwort setzen'));
            return;
        } else {
            flash(t('reset.flash_password_set', 'Passwort geaendert. Du kannst dich jetzt anmelden.'));
            redirect_route('login');
        }
    }

    view_bare('password_reset/form', [
        'token'   => $token,
        'invalid' => false,
        'error'   => $error,
    ], t('reset.form_title', 'Neues Passwort setzen'));
}

/**
 * Resolve a free-form identifier (username or e-mail) to a user row, or
 * null when nothing matches an active account. Only active users may
 * reset — disabled accounts stay disabled.
 */
function password_reset_lookup_user(string $identifier): ?array
{
    $identifier = trim($identifier);
    if ($identifier === '') { return null; }

    $byUser = DB::one(
        'SELECT id, username, display_name, email FROM users WHERE username = ? AND active = 1',
        [$identifier]
    );
    if ($byUser) { return $byUser; }

    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $byMail = DB::one(
            'SELECT id, username, display_name, email FROM users WHERE email = ? AND active = 1',
            [$identifier]
        );
        if ($byMail) { return $byMail; }
    }
    return null;
}

/**
 * Build and send the reset mail. Called only when we DID find a user — the
 * caller handles the no-enumeration redirect for the miss case. When the
 * user has no e-mail on file we silently skip the send and let the common
 * redirect handle the response; the admin still sees a token row for
 * audit.
 */
function password_reset_send_mail(array $user, string $plainToken): void
{
    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '' || !Mail::isConfigured()) {
        return;
    }

    $link = password_reset_absolute_link($plainToken);
    $appName = (string) cfg('app_name', 'Timeminator');
    $name    = trim((string) ($user['display_name'] ?? '')) ?: (string) $user['username'];

    $subject = sprintf(
        t('reset.mail_subject', 'Passwort zuruecksetzen - %s'),
        $appName
    );
    $body = sprintf(
        t(
            'reset.mail_body',
            "Hallo %s,\n\n" .
            "wir haben eine Anfrage zum Zuruecksetzen deines Passworts erhalten.\n" .
            "Oeffne den folgenden Link, um ein neues Passwort zu setzen (gueltig fuer eine Stunde):\n\n" .
            "%s\n\n" .
            "Hast du keine Anfrage gestellt? Dann ignoriere diese Mail - dein aktuelles Passwort bleibt gueltig.\n\n" .
            "— %s"
        ),
        $name,
        $link,
        $appName
    );

    [$ok, $err] = Mail::send($email, $subject, $body);
    if (!$ok) {
        error_log('[PasswordReset] mail send failed: ' . ($err ?? 'unknown'));
    }
}

/**
 * Build an absolute URL to the reset form including the token. We only need
 * the origin (scheme://host) — `route()` already returns the full path from
 * document root. Prefer the install's configured `app_url` for its origin;
 * fall back to the active request when that is unset (first-boot installs
 * commonly leave it blank).
 */
function password_reset_absolute_link(string $plainToken): string
{
    $configured = trim((string) cfg('app_url', ''));
    if ($configured !== '' && preg_match('#^(https?://[^/]+)#i', $configured, $m)) {
        $origin = $m[1];
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $origin = $scheme . '://' . $host;
    }
    return $origin . route('reset_form', ['token' => $plainToken]);
}
