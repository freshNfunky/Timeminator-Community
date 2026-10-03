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
<?php if (Auth::can('structure.manage')): ?>
  <a href="<?= h(route('clients')) ?>"<?= $active(['clients']) ?>>Kunden</a>
  <a href="<?= h(route('projects')) ?>"<?= $active(['projects']) ?>>Projekte</a>
  <a href="<?= h(route('tasks')) ?>"<?= $active(['tasks']) ?>>Aufgaben</a>
<?php endif; ?>
<?php if (Auth::can('scopes.manage')): ?>
  <a href="<?= h(route('scopes')) ?>"<?= $active(['scopes']) ?>>Sichten</a>
<?php endif; ?>
<?php if (Auth::check()): ?>
  <span class="nav-sep"></span>
  <a href="<?= h(route('pro_invoices')) ?>"<?= $active(['pro_invoices']) ?> title="Pro-Feature">Rechnungen <span class="pill pill-pro nav-pill">Pro</span></a>
  <a href="<?= h(route('pro_offers')) ?>"<?= $active(['pro_offers']) ?> title="Pro-Feature">Angebote <span class="pill pill-pro nav-pill">Pro</span></a>
  <a href="<?= h(route('pro_budget')) ?>"<?= $active(['pro_budget']) ?> title="Pro-Feature">Budget <span class="pill pill-pro nav-pill">Pro</span></a>
<?php endif; ?>
<?php if (Auth::can('admin.users') || Auth::can('admin.roles') || Auth::can('admin.system') || Auth::can('admin.imports')): ?>
  <span class="nav-sep"></span>
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
<?php endif; ?>
<span class="nav-sep"></span>
<a href="<?= h(cfg('pro_url', 'https://timeminator.felixschaller.com')) ?>" target="_blank" rel="noopener" class="nav-pro">Timeminator Pro &nearr;</a>
