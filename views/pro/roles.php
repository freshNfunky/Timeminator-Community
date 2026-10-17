<?php
/**
 * Rollen Pro — Pro-only teaser.
 *
 * Community has the two built-in roles (Admin, User). Pro adds
 * fine-grained custom roles with per-permission matrix. This page mocks
 * the matrix editor in a dimmed state.
 *
 * @var string $pro_url
 */
$__feature = t('nav.roles_pro');
$__bullets = '<li>' . h(t('pro.roles.b1', 'Beliebig viele eigene Rollen (statt zwei)')) . '</li>'
           . '<li>' . h(t('pro.roles.b2', 'Feingranulare Rechte pro Modul')) . '</li>'
           . '<li>' . h(t('pro.roles.b3', 'Rollenvorlagen fuer typische Teams')) . '</li>'
           . '<li>' . h(t('pro.roles.b4', 'Audit-Log fuer Rechteaenderungen')) . '</li>';
?>
<div class="pro-teaser">
  <div class="pro-teaser-mock" aria-hidden="true">
    <div class="card">
      <div class="card-head">
        <h2><?= h(t('nav.roles')) ?></h2>
        <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span>
      </div>
      <div class="pro-teaser-toolbar">
        <button class="btn btn-primary" type="button" disabled><?= h(t('pro.roles.btn_new', '+ Neue Rolle')) ?></button>
        <button class="btn" type="button" disabled><?= h(sprintf(t('pro.roles.btn_template', 'Vorlage: %s'), t('pro.roles.role_accounting', 'Buchhaltung'))) ?></button>
        <button class="btn" type="button" disabled><?= h(sprintf(t('pro.roles.btn_template', 'Vorlage: %s'), t('pro.roles.role_pm', 'Projektleitung'))) ?></button>
      </div>
      <table class="table matrix">
        <thead>
          <tr>
            <th><?= h(t('roles.permissions')) ?></th>
            <th><?= h(t('pro.roles.role_admin', 'Admin')) ?></th>
            <th><?= h(t('pro.roles.role_pm', 'Projektleitung')) ?></th>
            <th><?= h(t('pro.roles.role_accounting', 'Buchhaltung')) ?></th>
            <th><?= h(t('pro.roles.role_freelancer', 'Freelancer')) ?></th>
            <th><?= h(t('pro.roles.role_readonly', 'Readonly')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ([
            'entries.manage'   => t('pro.roles.p_entries_manage',   'Zeiteintraege erfassen'),
            'entries.view_all' => t('pro.roles.p_entries_view_all', 'Alle Nutzer sehen'),
            'stats.view'       => t('pro.roles.p_stats_view',       'Statistik'),
            'structure.manage' => t('pro.roles.p_structure_manage', 'Struktur'),
            'invoices.create'  => t('pro.roles.p_invoices_create',  'Rechnungen erstellen'),
            'offers.create'    => t('pro.roles.p_offers_create',    'Angebote erstellen'),
            'budget.view'      => t('pro.roles.p_budget_view',      'Budgets einsehen'),
            'admin.users'      => t('pro.roles.p_admin_users',      'Benutzerverwaltung'),
            'admin.roles'      => t('pro.roles.p_admin_roles',      'Rollenverwaltung'),
          ] as $__key => $__label):
            // Deterministic sham per cell, so the mock stays stable.
            $__cells = [];
            foreach (range(0, 4) as $__ri) {
              $__cells[] = ($__ri === 0) || (crc32($__key . ':' . $__ri) % 3 !== 2);
            }
          ?>
            <tr>
              <td><?= h($__label) ?> <span class="muted"><?= h($__key) ?></span></td>
              <?php foreach ($__cells as $__ok): ?>
                <td class="r"><?= $__ok ? '&check;' : '&middot;' ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php require __DIR__ . '/../partials/pro_upsell.php'; ?>
</div>
