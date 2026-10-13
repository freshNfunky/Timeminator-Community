<?php
declare(strict_types=1);

/**
 * German locale catalog (default). Keys use `area.subarea.purpose` so the
 * catalog stays browsable as it grows; prefer adding keys here before using
 * them in a view so the English catalog stays in lockstep.
 */
return [
    // ---------- Common ----------
    'common.app_name'           => 'Timeminator',
    'common.save'               => 'Speichern',
    'common.cancel'             => 'Abbrechen',
    'common.delete'             => 'Loeschen',
    'common.back'               => 'Zurueck',
    'common.yes'                => 'Ja',
    'common.no'                 => 'Nein',
    'common.optional'           => 'optional',

    // ---------- Nav / topbar ----------
    'nav.dashboard'             => 'Dashboard',
    'nav.entries'               => 'Zeiteintraege',
    'nav.stats'                 => 'Statistik',
    'nav.sentiment'             => 'Stimmung',
    'nav.clients'               => 'Kunden',
    'nav.projects'              => 'Projekte',
    'nav.workpackages'          => 'Arbeitspakete',
    'nav.tasks'                 => 'Aufgaben',
    'nav.scopes'                => 'Sichten',
    'nav.commercial'            => 'Kaufmaennisch',
    'nav.invoices'              => 'Rechnungen',
    'nav.offers'                => 'Angebote',
    'nav.budget'                => 'Budget',
    'nav.admin'                 => 'Systemverwaltung',
    'nav.users'                 => 'Benutzer',
    'nav.roles'                 => 'Rollen',
    'nav.roles_pro'             => 'Rollen Pro',
    'nav.import'                => 'Import',
    'nav.system'                => 'System',
    'nav.pro_link'              => 'Timeminator Pro ↗',
    'nav.pro_feature_tooltip'   => 'Pro-Feature',
    'nav.pro_pill'              => 'Pro',
    'nav.logout'                => 'Abmelden',

    // ---------- Layout footer ----------
    'footer.made_by'            => 'made by FelixSchallerCOM',
    'footer.more_features'      => 'mehr Funktionen mit Timeminator Pro',
    'footer.community_suffix'   => 'Community v%s',

    // ---------- Login ----------
    'login.title'               => 'Anmelden',
    'login.username'            => 'Benutzername',
    'login.password'            => 'Passwort',
    'login.submit'              => 'Anmelden',
    'login.err_bad_credentials' => 'Benutzername oder Passwort falsch.',
    'login.welcome_back'        => 'Willkommen zurueck, %s.',

    // ---------- Dashboard ----------
    'dash.today'                => 'Heute',
    'dash.this_week'            => 'Diese Woche',
    'dash.this_month'           => 'Dieser Monat',
    'dash.timer_start'          => 'Timer starten',
    'dash.timer_stop'           => 'Stoppen & buchen',
    'dash.recent'               => 'Letzte Eintraege',
    'dash.recent_empty'         => 'Noch keine Buchungen.',
    'dash.col_start'            => 'Start',
    'dash.col_client_project'   => 'Kunde / Projekt',
    'dash.col_task'             => 'Aufgabe',
    'dash.col_duration'         => 'Dauer',
    'dash.add_manual'           => '+ Manueller Eintrag',
    'dash.task_placeholder'     => 'Woran arbeitest du?',
    'dash.note'                 => 'Notiz (optional)',
    'dash.no_tasks'             => 'Noch keine Aufgaben. Lege zuerst',
    'dash.no_tasks_and'         => 'und',
    'dash.no_tasks_cont'        => ' an.',
    'dash.timer_running_badge'  => 'laeuft',

    // ---------- Installer ----------
    'install.title'             => 'Installation',
    'install.heading'           => 'Timeminator Community - Installation',
    'install.intro'             => 'Projektbezogene Zeiterfassung',
    'install.sysreq'            => 'Systemvoraussetzungen',
    'install.section_app'       => 'Anwendung',
    'install.app_name_label'    => 'App-Name',
    'install.timezone_label'    => 'Zeitzone',
    'install.section_db'        => 'Datenbank',
    'install.db_type'           => 'Datenbank',
    'install.db_host'           => 'Host',
    'install.db_port'           => 'Port',
    'install.db_name'           => 'Name',
    'install.db_user'           => 'Benutzer',
    'install.db_password'       => 'Passwort',
    'install.section_admin'     => 'Administrator-Konto',
    'install.admin_name'        => 'Anzeigename',
    'install.admin_username'    => 'Benutzername',
    'install.admin_email'       => 'E-Mail',
    'install.admin_password'    => 'Passwort',
    'install.admin_password_repeat' => 'Passwort wiederholen',
    'install.section_registry'  => 'Registrierung',
    'install.section_urls'      => 'Erweitert: Update- und Registrierungs-URLs',
    'install.submit'            => 'Installieren',
    'install.done'              => 'Installation abgeschlossen. Du kannst dich jetzt anmelden.',
    'install.delete_hint'       => 'Aus Sicherheitsgruenden kannst du <code>install.php</code> nun loeschen.',
    'install.to_login'          => 'Zur Anmeldung',
    'install.req_fail'          => 'Bitte zuerst die fehlenden Voraussetzungen beheben (PDO und Schreibrechte auf das Verzeichnis).',
    'install.ok'                => 'OK',
    'install.missing'           => 'fehlt',
    'install.db_mysql'          => 'MySQL / MariaDB (Hosting)',
    'install.db_sqlite'         => 'SQLite (lokal, ohne Server)',
    'install.reg_opt_in'        => 'Ja, ueber Updates informieren',
    'install.reg_optional'      => '(optional, opt-in)',
    'install.reg_hint'          => 'Nur wenn gewuenscht: informiert dich der Maintainer ueber Updates. Es werden nur E-Mail, Domain und Version gesendet - keine Zeiterfassungsdaten.',
    'install.urls_hint'         => 'Die Voreinstellungen zeigen auf den offiziellen Timeminator-Kanal. Fork-Betreiber koennen hier auf einen eigenen Manifest- oder Registry-Server umbiegen, oder beides ganz abschalten.',
    'install.update_manifest_url' => 'Update-Manifest-URL',
    'install.disable_update_check' => 'Update-Pruefung deaktivieren',
    'install.registration_endpoint' => 'Registrierungs-Endpoint',
    'install.disable_registration' => 'Registrierung ganz deaktivieren',

    // ---------- Side-panel ----------
    'sidepanel.brand_aria'      => 'FelixSchallerCOM',

    // ---------- Admin: System - Language ----------
    'admin.language_section'    => 'Sprache',
    'admin.language_label'      => 'Oberflaechensprache',
    'admin.language_hint'       => 'Default ist Deutsch. Nutzer koennen die Sprache per `?lang=en` Query-Parameter umschalten oder hier dauerhaft fuer diese Session setzen.',
    'admin.language_saved'      => 'Sprache gespeichert.',
];
