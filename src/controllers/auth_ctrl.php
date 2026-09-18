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
        if (Auth::attempt($username, $password)) {
            flash('Willkommen zurueck, ' . (Auth::user()['display_name'] ?: $username) . '.');
            redirect_route('dashboard');
        }
        $error = 'Benutzername oder Passwort falsch.';
        usleep(300000); // small delay against brute force
    }
    view_bare('login', ['error' => $error], 'Anmelden');
}

function ctrl_logout(): void
{
    require_login();
    Auth::logout();
    redirect_route('login');
}
