<?php
declare(strict_types=1);

/**
 * English locale catalog. Keys MUST stay in lockstep with `lang/de.php` —
 * a missing key here silently falls back to the German string via
 * Locale::translate(). Add the key to both files when introducing new UI copy.
 *
 * Dates / numbers / currency are NOT localized yet (tracked separately);
 * this file is UI strings only.
 */
return [
    // ---------- Common ----------
    'common.app_name'           => 'Timeminator',
    'common.save'               => 'Save',
    'common.cancel'             => 'Cancel',
    'common.delete'             => 'Delete',
    'common.back'               => 'Back',
    'common.yes'                => 'Yes',
    'common.no'                 => 'No',
    'common.optional'           => 'optional',

    // ---------- Nav / topbar ----------
    'nav.dashboard'             => 'Dashboard',
    'nav.entries'               => 'Time entries',
    'nav.stats'                 => 'Statistics',
    'nav.sentiment'             => 'Sentiment',
    'nav.clients'               => 'Clients',
    'nav.projects'              => 'Projects',
    'nav.workpackages'          => 'Work packages',
    'nav.tasks'                 => 'Tasks',
    'nav.scopes'                => 'Scopes',
    'nav.commercial'            => 'Commercial',
    'nav.invoices'              => 'Invoices',
    'nav.offers'                => 'Quotes',
    'nav.budget'                => 'Budget',
    'nav.admin'                 => 'Administration',
    'nav.users'                 => 'Users',
    'nav.roles'                 => 'Roles',
    'nav.roles_pro'             => 'Roles Pro',
    'nav.import'                => 'Import',
    'nav.system'                => 'System',
    'nav.pro_link'              => 'Timeminator Pro ↗',
    'nav.pro_feature_tooltip'   => 'Pro feature',
    'nav.pro_pill'              => 'Pro',
    'nav.logout'                => 'Sign out',

    // ---------- Layout footer ----------
    'footer.made_by'            => 'made by FelixSchallerCOM',
    'footer.more_features'      => 'more features in Timeminator Pro',
    'footer.community_suffix'   => 'Community v%s',

    // ---------- Login ----------
    'login.title'               => 'Sign in',
    'login.username'            => 'Username',
    'login.password'            => 'Password',
    'login.submit'              => 'Sign in',
    'login.err_bad_credentials' => 'Username or password incorrect.',
    'login.welcome_back'        => 'Welcome back, %s.',

    // ---------- Dashboard ----------
    'dash.today'                => 'Today',
    'dash.this_week'            => 'This week',
    'dash.this_month'           => 'This month',
    'dash.timer_start'          => 'Start timer',
    'dash.timer_stop'           => 'Stop & book',
    'dash.recent'               => 'Recent entries',
    'dash.recent_empty'         => 'No entries yet.',
    'dash.col_start'            => 'Start',
    'dash.col_client_project'   => 'Client / project',
    'dash.col_task'             => 'Task',
    'dash.col_duration'         => 'Duration',
    'dash.add_manual'           => '+ Manual entry',
    'dash.task_placeholder'     => 'What are you working on?',
    'dash.note'                 => 'Note (optional)',
    'dash.no_tasks'             => 'No tasks yet. First create',
    'dash.no_tasks_and'         => 'and',
    'dash.no_tasks_cont'        => '.',
    'dash.timer_running_badge'  => 'running',

    // ---------- Installer ----------
    'install.title'             => 'Install',
    'install.heading'           => 'Timeminator Community - Install',
    'install.intro'             => 'Project-based time tracking',
    'install.sysreq'            => 'System requirements',
    'install.section_app'       => 'Application',
    'install.app_name_label'    => 'App name',
    'install.timezone_label'    => 'Time zone',
    'install.section_db'        => 'Database',
    'install.db_type'           => 'Database',
    'install.db_host'           => 'Host',
    'install.db_port'           => 'Port',
    'install.db_name'           => 'Name',
    'install.db_user'           => 'User',
    'install.db_password'       => 'Password',
    'install.section_admin'     => 'Administrator account',
    'install.admin_name'        => 'Display name',
    'install.admin_username'    => 'Username',
    'install.admin_email'       => 'Email',
    'install.admin_password'    => 'Password',
    'install.admin_password_repeat' => 'Repeat password',
    'install.section_registry'  => 'Registration',
    'install.section_urls'      => 'Advanced: update + registration URLs',
    'install.submit'            => 'Install',
    'install.done'              => 'Install complete. You can now sign in.',
    'install.delete_hint'       => 'For security, you can now delete <code>install.php</code>.',
    'install.to_login'          => 'Go to sign in',
    'install.req_fail'          => 'Please fix the missing prerequisites first (PDO and write access to the directory).',
    'install.ok'                => 'OK',
    'install.missing'           => 'missing',
    'install.db_mysql'          => 'MySQL / MariaDB (shared hosting)',
    'install.db_sqlite'         => 'SQLite (local, no server)',
    'install.reg_opt_in'        => 'Yes, notify me about updates',
    'install.reg_optional'      => '(optional, opt-in)',
    'install.reg_hint'          => 'Only if you want it: the maintainer notifies you about updates. Only email, domain and version are sent - no time-tracking data.',
    'install.urls_hint'         => 'The defaults point at the official Timeminator channel. Fork operators can redirect to their own manifest / registry server here, or turn either off entirely.',
    'install.update_manifest_url' => 'Update manifest URL',
    'install.disable_update_check' => 'Disable update check',
    'install.registration_endpoint' => 'Registration endpoint',
    'install.disable_registration' => 'Disable registration entirely',

    // ---------- Side-panel ----------
    'sidepanel.brand_aria'      => 'FelixSchallerCOM',

    // ---------- Admin: System - Language ----------
    'admin.language_section'    => 'Language',
    'admin.language_label'      => 'UI language',
    'admin.language_hint'       => 'Default is German. Users can switch via `?lang=en` on any URL, or set it here for the current session.',
    'admin.language_saved'      => 'Language saved.',
];
