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
$__feature = 'Rollen Pro';
$__bullets = '<li>Beliebig viele eigene Rollen (statt zwei)</li>'
           . '<li>Feingranulare Rechte pro Modul</li>'
           . '<li>Rollenvorlagen fuer typische Teams</li>'
           . '<li>Audit-Log fuer Rechteaenderungen</li>';
?>
<div class="pro-teaser">
  <div class="pro-teaser-mock" aria-hidden="true">
    <div class="card">
      <div class="card-head">
        <h2>Rollen</h2>
        <span class="pill pill-pro">Pro</span>
      </div>
      <div class="pro-teaser-toolbar">
        <button class="btn btn-primary" type="button" disabled>+ Neue Rolle</button>
        <button class="btn" type="button" disabled>Vorlage: Buchhaltung</button>
        <button class="btn" type="button" disabled>Vorlage: Projektleitung</button>
      </div>
      <table class="table matrix">
        <thead>
          <tr>
            <th>Berechtigung</th>
            <th>Admin</th><th>Projektleitung</th><th>Buchhaltung</th><th>Freelancer</th><th>Readonly</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ([
            'entries.manage'   => 'Zeiteintraege erfassen',
            'entries.view_all' => 'Alle Nutzer sehen',
            'stats.view'       => 'Statistik',
            'structure.manage' => 'Struktur',
            'invoices.create'  => 'Rechnungen erstellen',
            'offers.create'    => 'Angebote erstellen',
            'budget.view'      => 'Budgets einsehen',
            'admin.users'      => 'Benutzerverwaltung',
            'admin.roles'      => 'Rollenverwaltung',
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
