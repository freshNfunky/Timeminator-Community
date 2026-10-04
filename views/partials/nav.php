<?php
$r = (string) get('r', 'dashboard');
$active = fn(array $keys) => in_array($r, $keys, true) ? ' class="active"' : '';
?>
<a href="<?= h(route('dashboard')) ?>"<?= $active(['dashboard']) ?>>Dashboard</a>
<?php if (Auth::can('entries.manage')): ?>
  <a href="<?= h(route('entries')) ?>"<?= $active(['entries', 'entry_form']) ?>>Zeiteintraege</a>
<?php endif; ?>
<?php if (Auth::can('stats.view')): ?>
  <a href="<?= h(route('stats')) ?>"<?= $active(['stats']) ?>>Statistik</a>
<?php endif; ?>
<?php if (Auth::can('sentiment.record')): ?>
  <a href="<?= h(route('sentiment')) ?>"<?= $active(['sentiment']) ?>>Stimmung</a>
<?php endif; ?>
<?php if (Auth::can('structure.manage')): ?>
  <a href="<?= h(route('clients')) ?>"<?= $active(['clients']) ?>>Kunden</a>
  <a href="<?= h(route('projects')) ?>"<?= $active(['projects']) ?>>Projekte</a>
  <a href="<?= h(route('workpackages')) ?>"<?= $active(['workpackages']) ?>>Arbeitspakete</a>
  <a href="<?= h(route('tasks')) ?>"<?= $active(['tasks']) ?>>Aufgaben</a>
<?php endif; ?>
<?php if (Auth::can('scopes.manage')): ?>
  <a href="<?= h(route('scopes')) ?>"<?= $active(['scopes']) ?>>Sichten</a>
<?php endif; ?>
<?php
  // Pro-only commercial features in a collapsible submenu, mirroring the
  // Pro edition's "Kaufmaennisch" group. Uses <details> so there is no
  // JS requirement for toggling and the keyboard flow works for free.
  $__kmRoutes  = ['pro_invoices', 'pro_offers', 'pro_budget'];
  $__kmActive  = in_array($r, $__kmRoutes, true);
?>
<?php if (Auth::check()): ?>
  <span class="nav-sep"></span>
  <details class="nav-group"<?= $__kmActive ? ' open' : '' ?>>
    <summary class="nav-group-toggle<?= $__kmActive ? ' active' : '' ?>">Kaufmaennisch <span class="pill pill-pro nav-pill">Pro</span></summary>
    <div class="nav-group-items">
      <a href="<?= h(route('pro_invoices')) ?>"<?= $active(['pro_invoices']) ?> title="Pro-Feature">Rechnungen</a>
      <a href="<?= h(route('pro_offers')) ?>"<?= $active(['pro_offers']) ?> title="Pro-Feature">Angebote</a>
      <a href="<?= h(route('pro_budget')) ?>"<?= $active(['pro_budget']) ?> title="Pro-Feature">Budget</a>
    </div>
  </details>
<?php endif; ?>
<?php
  // Collapse all admin entries (Benutzer / Rollen / Rollen Pro / Import /
  // System) into one dropdown "Systemverwaltung" so the top bar stays
  // compact at normal viewport widths. Same <details> pattern as the
  // Kaufmaennisch group above.
  $__sysRoutes = ['admin_users', 'admin_roles', 'pro_roles', 'imports', 'admin_system'];
  $__sysActive = in_array($r, $__sysRoutes, true);
  $__sysAny    = Auth::can('admin.users') || Auth::can('admin.roles')
              || Auth::can('admin.system') || Auth::can('admin.imports');
?>
<?php if ($__sysAny): ?>
  <span class="nav-sep"></span>
  <details class="nav-group"<?= $__sysActive ? ' open' : '' ?>>
    <summary class="nav-group-toggle<?= $__sysActive ? ' active' : '' ?>">Systemverwaltung</summary>
    <div class="nav-group-items">
      <?php if (Auth::can('admin.users')): ?>
        <a href="<?= h(route('admin_users')) ?>"<?= $active(['admin_users']) ?>>Benutzer</a>
      <?php endif; ?>
      <?php if (Auth::can('admin.roles')): ?>
        <a href="<?= h(route('admin_roles')) ?>"<?= $active(['admin_roles']) ?>>Rollen</a>
        <a href="<?= h(route('pro_roles')) ?>"<?= $active(['pro_roles']) ?> title="Pro-Feature">Rollen Pro <span class="pill pill-pro nav-pill">Pro</span></a>
      <?php endif; ?>
      <?php if (Auth::can('admin.imports')): ?>
        <a href="<?= h(route('imports')) ?>"<?= $active(['imports']) ?>>Import</a>
      <?php endif; ?>
      <?php if (Auth::can('admin.system')): ?>
        <a href="<?= h(route('admin_system')) ?>"<?= $active(['admin_system']) ?>>System</a>
      <?php endif; ?>
    </div>
  </details>
<?php endif; ?>
<span class="nav-sep"></span>
<a href="<?= h(cfg('pro_url', 'https://timeminator.felixschaller.com')) ?>" target="_blank" rel="noopener" class="nav-pro">Timeminator Pro &nearr;</a>
