<?php
$r = (string) get('r', 'dashboard');
$active = fn(array $keys) => in_array($r, $keys, true) ? ' class="active"' : '';
?>
<a href="<?= h(route('dashboard')) ?>"<?= $active(['dashboard']) ?>><?= h(t('nav.dashboard')) ?></a>

<?php if (Auth::can('entries.manage')): ?>
  <a href="<?= h(route('entries')) ?>"<?= $active(['entries', 'entry_form']) ?>><?= h(t('nav.entries')) ?></a>
<?php endif; ?>

<?php
  // --- Cockpit-Gruppe (nach Pro-Schema): die drei Analytik-Cockpits.
  $__cockpitRoutes = ['cockpit', 'cockpit_project', 'cockpit_review', 'cockpit_employees', 'cockpit_company'];
  $__cockpitActive = in_array($r, $__cockpitRoutes, true);
?>
<?php if (Auth::can('stats.view')): ?>
  <span class="nav-sep"></span>
  <details class="nav-group"<?= $__cockpitActive ? ' open' : '' ?>>
    <summary class="nav-group-toggle<?= $__cockpitActive ? ' active' : '' ?>"><?= h(t('nav.cockpit_group', 'Cockpit')) ?></summary>
    <div class="nav-group-items">
      <a href="<?= h(route('cockpit')) ?>"<?= $active(['cockpit', 'cockpit_project', 'cockpit_review']) ?>><?= h(t('nav.cockpit', 'Projekt-Cockpit')) ?></a>
      <a href="<?= h(route('cockpit_employees')) ?>"<?= $active(['cockpit_employees']) ?>><?= h(t('nav.cockpit_employees', 'Mitarbeiter-Cockpit')) ?></a>
      <a href="<?= h(route('cockpit_company')) ?>"<?= $active(['cockpit_company']) ?>><?= h(t('nav.cockpit_company', 'Firmen-Cockpit')) ?></a>
    </div>
  </details>
<?php endif; ?>

<?php
  // --- Monitoring-Gruppe (nach Pro-Schema): Statistik, Abwesenheiten,
  // interne Prozesse und Stimmung — alles, was einen laufenden Zustand
  // anzeigt / erfasst, aber keine Soll-vs-Ist-Projektsicht ist.
  $__monRoutes = ['stats', 'processes', 'absences', 'sentiment', 'scopes'];
  $__monActive = in_array($r, $__monRoutes, true);
  $__monAny    = Auth::can('stats.view') || Auth::can('entries.manage')
              || Auth::can('sentiment.record') || Auth::can('scopes.manage');
?>
<?php if ($__monAny): ?>
  <span class="nav-sep"></span>
  <details class="nav-group"<?= $__monActive ? ' open' : '' ?>>
    <summary class="nav-group-toggle<?= $__monActive ? ' active' : '' ?>"><?= h(t('nav.monitoring', 'Monitoring')) ?></summary>
    <div class="nav-group-items">
      <?php if (Auth::can('stats.view')): ?>
        <a href="<?= h(route('stats')) ?>"<?= $active(['stats']) ?>><?= h(t('nav.stats')) ?></a>
      <?php endif; ?>
      <?php if (Auth::can('entries.manage')): ?>
        <a href="<?= h(route('absences')) ?>"<?= $active(['absences']) ?>><?= h(t('nav.absences', 'Abwesenheiten')) ?></a>
        <a href="<?= h(route('processes')) ?>"<?= $active(['processes']) ?>><?= h(t('nav.processes', 'Interne Prozesse')) ?></a>
      <?php endif; ?>
      <?php if (Auth::can('sentiment.record')): ?>
        <a href="<?= h(route('sentiment')) ?>"<?= $active(['sentiment']) ?>><?= h(t('nav.sentiment')) ?></a>
      <?php endif; ?>
      <?php if (Auth::can('scopes.manage')): ?>
        <a href="<?= h(route('scopes')) ?>"<?= $active(['scopes']) ?>><?= h(t('nav.scopes')) ?></a>
      <?php endif; ?>
    </div>
  </details>
<?php endif; ?>

<?php
  // --- Stammdaten-Gruppe (nach Pro-Schema): Kunden/Projekte/Arbeitspakete/
  // Aufgaben plus Presets (bleibt aus Konsistenz zur Pro-Variante hier).
  $__dataRoutes = ['clients', 'projects', 'project_show', 'workpackages', 'tasks', 'presets', 'project_categories', 'process_categories'];
  $__dataActive = in_array($r, $__dataRoutes, true);
?>
<?php if (Auth::can('structure.manage')): ?>
  <span class="nav-sep"></span>
  <details class="nav-group"<?= $__dataActive ? ' open' : '' ?>>
    <summary class="nav-group-toggle<?= $__dataActive ? ' active' : '' ?>"><?= h(t('nav.master_data', 'Stammdaten')) ?></summary>
    <div class="nav-group-items">
      <a href="<?= h(route('clients')) ?>"<?= $active(['clients']) ?>><?= h(t('nav.clients')) ?></a>
      <a href="<?= h(route('projects')) ?>"<?= $active(['projects', 'project_show']) ?>><?= h(t('nav.projects')) ?></a>
      <a href="<?= h(route('workpackages')) ?>"<?= $active(['workpackages']) ?>><?= h(t('nav.workpackages')) ?></a>
      <a href="<?= h(route('tasks')) ?>"<?= $active(['tasks']) ?>><?= h(t('nav.tasks')) ?></a>
      <a href="<?= h(route('presets')) ?>"<?= $active(['presets', 'project_categories', 'process_categories']) ?>><?= h(t('nav.presets', 'Presets')) ?></a>
    </div>
  </details>
<?php endif; ?>

<?php
  // --- Kaufmaennisch (Pro-Teaser): Rechnungen/Angebote/Budget.
  $__kmRoutes = ['pro_invoices', 'pro_offers', 'pro_budget'];
  $__kmActive = in_array($r, $__kmRoutes, true);
?>
<?php if (Auth::check()): ?>
  <span class="nav-sep"></span>
  <details class="nav-group"<?= $__kmActive ? ' open' : '' ?>>
    <summary class="nav-group-toggle<?= $__kmActive ? ' active' : '' ?>"><?= h(t('nav.commercial')) ?> <span class="pill pill-pro nav-pill"><?= h(t('nav.pro_pill')) ?></span></summary>
    <div class="nav-group-items">
      <a href="<?= h(route('pro_invoices')) ?>"<?= $active(['pro_invoices']) ?> title="<?= h(t('nav.pro_feature_tooltip')) ?>"><?= h(t('nav.invoices')) ?></a>
      <a href="<?= h(route('pro_offers')) ?>"<?= $active(['pro_offers']) ?> title="<?= h(t('nav.pro_feature_tooltip')) ?>"><?= h(t('nav.offers')) ?></a>
      <a href="<?= h(route('pro_budget')) ?>"<?= $active(['pro_budget']) ?> title="<?= h(t('nav.pro_feature_tooltip')) ?>"><?= h(t('nav.budget')) ?></a>
    </div>
  </details>
<?php endif; ?>

<?php
  // --- Admin-Gruppe: Benutzer/Rollen/Import/System/Scheduler (Presets ist
  // jetzt in Stammdaten gelandet, aus Konsistenz mit Pro).
  $__sysRoutes = ['admin_users', 'admin_roles', 'pro_roles', 'imports', 'admin_system', 'admin_cron'];
  $__sysActive = in_array($r, $__sysRoutes, true);
  $__sysAny    = Auth::can('admin.users') || Auth::can('admin.roles')
              || Auth::can('admin.system') || Auth::can('admin.imports');
?>
<?php if ($__sysAny): ?>
  <span class="nav-sep"></span>
  <details class="nav-group"<?= $__sysActive ? ' open' : '' ?>>
    <summary class="nav-group-toggle<?= $__sysActive ? ' active' : '' ?>"><?= h(t('nav.admin')) ?></summary>
    <div class="nav-group-items">
      <?php if (Auth::can('admin.users')): ?>
        <a href="<?= h(route('admin_users')) ?>"<?= $active(['admin_users']) ?>><?= h(t('nav.users')) ?></a>
      <?php endif; ?>
      <?php if (Auth::can('admin.roles')): ?>
        <a href="<?= h(route('admin_roles')) ?>"<?= $active(['admin_roles']) ?>><?= h(t('nav.roles')) ?></a>
        <a href="<?= h(route('pro_roles')) ?>"<?= $active(['pro_roles']) ?> title="<?= h(t('nav.pro_feature_tooltip')) ?>"><?= h(t('nav.roles_pro')) ?> <span class="pill pill-pro nav-pill"><?= h(t('nav.pro_pill')) ?></span></a>
      <?php endif; ?>
      <?php if (Auth::can('admin.imports')): ?>
        <a href="<?= h(route('imports')) ?>"<?= $active(['imports']) ?>><?= h(t('nav.import')) ?></a>
      <?php endif; ?>
      <?php if (Auth::can('admin.system')): ?>
        <a href="<?= h(route('admin_system')) ?>"<?= $active(['admin_system']) ?>><?= h(t('nav.system')) ?></a>
        <a href="<?= h(route('admin_cron')) ?>"<?= $active(['admin_cron']) ?>><?= h(t('nav.cron')) ?></a>
      <?php endif; ?>
    </div>
  </details>
<?php endif; ?>

<span class="nav-sep"></span>
<a href="<?= h(cfg('pro_url', 'https://timeminator.felixschaller.com')) ?>" target="_blank" rel="noopener" class="nav-pro"><?= h(t('nav.pro_link')) ?></a>
