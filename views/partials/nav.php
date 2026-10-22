<?php
$r = (string) get('r', 'dashboard');
$active = fn(array $keys) => in_array($r, $keys, true) ? ' class="active"' : '';
?>
<a href="<?= h(route('dashboard')) ?>"<?= $active(['dashboard']) ?>><?= h(t('nav.dashboard')) ?></a>
<?php if (Auth::can('entries.manage')): ?>
  <a href="<?= h(route('entries')) ?>"<?= $active(['entries', 'entry_form']) ?>><?= h(t('nav.entries')) ?></a>
  <a href="<?= h(route('processes')) ?>"<?= $active(['processes']) ?>><?= h(t('nav.processes', 'Interne Prozesse')) ?></a>
  <a href="<?= h(route('absences')) ?>"<?= $active(['absences']) ?>><?= h(t('nav.absences', 'Abwesenheiten')) ?></a>
<?php endif; ?>
<?php if (Auth::can('stats.view')): ?>
  <a href="<?= h(route('stats')) ?>"<?= $active(['stats']) ?>><?= h(t('nav.stats')) ?></a>
<?php endif; ?>
<?php if (Auth::can('sentiment.record')): ?>
  <a href="<?= h(route('sentiment')) ?>"<?= $active(['sentiment']) ?>><?= h(t('nav.sentiment')) ?></a>
<?php endif; ?>
<?php if (Auth::can('structure.manage')): ?>
  <a href="<?= h(route('clients')) ?>"<?= $active(['clients']) ?>><?= h(t('nav.clients')) ?></a>
  <a href="<?= h(route('projects')) ?>"<?= $active(['projects']) ?>><?= h(t('nav.projects')) ?></a>
  <a href="<?= h(route('workpackages')) ?>"<?= $active(['workpackages']) ?>><?= h(t('nav.workpackages')) ?></a>
  <a href="<?= h(route('tasks')) ?>"<?= $active(['tasks']) ?>><?= h(t('nav.tasks')) ?></a>
<?php endif; ?>
<?php if (Auth::can('scopes.manage')): ?>
  <a href="<?= h(route('scopes')) ?>"<?= $active(['scopes']) ?>><?= h(t('nav.scopes')) ?></a>
<?php endif; ?>
<?php
  // Pro-only commercial features in a collapsible submenu, mirroring the
  // Pro edition's "Commercial" group. Uses <details> so there is no
  // JS requirement for toggling and the keyboard flow works for free.
  $__kmRoutes  = ['pro_invoices', 'pro_offers', 'pro_budget'];
  $__kmActive  = in_array($r, $__kmRoutes, true);
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
  // Collapse all admin entries (Users / Roles / Roles Pro / Import /
  // System) into one dropdown so the top bar stays compact at normal
  // viewport widths. Same <details> pattern as the Commercial group above.
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
      <?php if (Auth::can('structure.manage')): ?>
        <a href="<?= h(route('presets')) ?>"<?= $active(['presets', 'project_categories', 'process_categories']) ?>><?= h(t('nav.presets', 'Presets')) ?></a>
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
