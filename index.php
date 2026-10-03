<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

// First run: no configuration yet -> go to the installer.
if (!config_exists()) {
    redirect(base_path() . '/install.php');
}

app_boot();
require __DIR__ . '/src/Repo.php';
require __DIR__ . '/src/Stats.php';
require __DIR__ . '/src/Settings.php';
require __DIR__ . '/src/Updater.php';
require __DIR__ . '/src/Registration.php';
require __DIR__ . '/src/PromoFeed.php';
require __DIR__ . '/src/seed.php';
require __DIR__ . '/src/Migrator.php';

// Apply pending schema migrations (idempotent, cheap).
Migrator::run();

// Controllers
require __DIR__ . '/src/controllers/auth_ctrl.php';
require __DIR__ . '/src/controllers/dashboard_ctrl.php';
require __DIR__ . '/src/controllers/entries_ctrl.php';
require __DIR__ . '/src/controllers/structure_ctrl.php';
require __DIR__ . '/src/controllers/stats_ctrl.php';
require __DIR__ . '/src/controllers/admin_ctrl.php';
require __DIR__ . '/src/controllers/imports_ctrl.php';
require __DIR__ . '/src/controllers/pro_teaser_ctrl.php';

$r = (string) get('r', Auth::check() ? 'dashboard' : 'login');

$routes = [
    'login'        => 'ctrl_login',
    'logout'       => 'ctrl_logout',
    'dashboard'    => 'ctrl_dashboard',

    'entries'         => 'ctrl_entries_index',
    'entries_export'  => 'ctrl_entries_export',
    'entry_form'      => 'ctrl_entry_form',
    'entry_save'      => 'ctrl_entry_save',
    'entry_delete'    => 'ctrl_entry_delete',
    'timer_start'  => 'ctrl_timer_start',
    'timer_stop'   => 'ctrl_timer_stop',
    'timer_status' => 'ctrl_timer_status',

    'clients'      => 'ctrl_clients_index',
    'client_save'  => 'ctrl_client_save',
    'projects'     => 'ctrl_projects_index',
    'project_save' => 'ctrl_project_save',
    'workpackages'     => 'ctrl_workpackages_index',
    'workpackage_save' => 'ctrl_workpackage_save',
    'tasks'        => 'ctrl_tasks_index',
    'task_save'    => 'ctrl_task_save',

    'stats'        => 'ctrl_stats_index',
    'stats_data'   => 'ctrl_stats_data',
    'scopes'       => 'ctrl_scopes_index',
    'scope_save'   => 'ctrl_scope_save',
    'scope_delete' => 'ctrl_scope_delete',

    'admin_users'  => 'ctrl_users_index',
    'user_save'    => 'ctrl_user_save',
    'admin_roles'  => 'ctrl_roles_index',
    'role_save'    => 'ctrl_role_save',
    'role_delete'  => 'ctrl_role_delete',
    'admin_system' => 'ctrl_system_index',
    'update_check'   => 'ctrl_update_check',
    'update_apply'   => 'ctrl_update_apply',
    'update_dismiss' => 'ctrl_update_dismiss',
    'update_skip'    => 'ctrl_update_skip',
    'promo_dismiss'  => 'ctrl_promo_dismiss',
    'update_clear_skipped' => 'ctrl_update_clear_skipped',
    'registration_save' => 'ctrl_registration_save',
    'endpoints_save'    => 'ctrl_endpoints_save',

    'imports'         => 'ctrl_imports_index',
    'imports_upload'  => 'ctrl_imports_upload',
    'imports_confirm' => 'ctrl_imports_confirm',
    'imports_discard' => 'ctrl_imports_discard',
    'imports_delete'  => 'ctrl_imports_delete',

    // Pro-only feature teasers (Community edition). Each one renders a
    // dimmed read-only mockup + right-edge upsell banner.
    'pro_invoices' => 'ctrl_pro_invoices',
    'pro_offers'   => 'ctrl_pro_offers',
    'pro_budget'   => 'ctrl_pro_budget',
    'pro_roles'    => 'ctrl_pro_roles',
];

$handler = $routes[$r] ?? null;
if ($handler === null || !function_exists($handler)) {
    http_response_code(404);
    view('error', ['code' => 404, 'message' => 'Seite nicht gefunden.'], 'Nicht gefunden');
    exit;
}

$handler();
