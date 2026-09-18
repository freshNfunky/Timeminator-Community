<?php /** @var string $version @var ?array $update @var array $registration @var string $reg_endpoint @var ?string $manifest */ ?>
<div class="cols">
  <div class="col-list">
    <div class="card">
      <div class="card-head"><h2>Version &amp; Updates</h2><span class="pill">v<?= h($version) ?></span></div>
      <p class="muted">Release-Kanal: <code><?= h($manifest ?: '—') ?></code></p>

      <form method="post" action="<?= h(route('update_check')) ?>" class="inline">
        <?= csrf_field() ?>
        <button class="btn" type="submit">Auf Updates pruefen</button>
      </form>

      <?php if ($update && !empty($update['ok'])): ?>
        <div class="update-box <?= !empty($update['newer']) ? 'update-avail' : '' ?>">
          <?php if (!empty($update['newer'])): ?>
            <p><strong>Update verfuegbar:</strong> Version <?= h($update['latest']) ?>
               (installiert <?= h($update['current']) ?>).</p>
            <?php if (!empty($update['url'])): ?><p><a href="<?= h($update['url']) ?>" target="_blank" rel="noopener">Release-Notes ansehen</a></p><?php endif; ?>
            <form method="post" action="<?= h(route('update_apply')) ?>" onsubmit="return confirm('Update jetzt einspielen? config.php und Daten bleiben erhalten.')">
              <?= csrf_field() ?>
              <button class="btn btn-primary" type="submit">Update einspielen</button>
            </form>
          <?php else: ?>
            <p>Timeminator ist aktuell (v<?= h($update['current']) ?>). Zuletzt geprueft: <?= h(fmt_dt($update['checked_at'] ?? null)) ?>.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Rechte-Katalog</h2>
      <p class="muted">Nach einem Update neue Berechtigungen in die Datenbank uebernehmen.</p>
      <form method="post" action="<?= h(route('admin_system')) ?>" class="inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="sync_perms">
        <button class="btn" type="submit">Rechte synchronisieren</button>
      </form>
    </div>
  </div>

  <div class="col-form">
    <div class="card">
      <h2>Registrierung (optional)</h2>
      <p class="muted">Rein freiwillig. Uebertragen werden nur Kontakt-E-Mail, Domain und Version - niemals Zeiterfassungsdaten. Jederzeit abschaltbar.</p>
      <p class="muted">Endpoint: <code><?= h($reg_endpoint ?: '—') ?></code></p>
      <form method="post" action="<?= h(route('registration_save')) ?>">
        <?= csrf_field() ?>
        <label>Kontakt-E-Mail
          <input type="email" name="email" value="<?= h($registration['email'] ?? '') ?>" placeholder="du@example.com">
        </label>
        <label class="check">
          <input type="checkbox" name="opt_in" value="1" <?= !empty($registration['opt_in']) ? 'checked' : '' ?>>
          Ueber Updates und Sicherheitshinweise informieren (opt-in)
        </label>
        <?php if (!empty($registration['last_sent'])): ?>
          <p class="muted">Zuletzt gesendet: <?= h(fmt_dt($registration['last_sent'])) ?></p>
        <?php endif; ?>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Speichern</button></div>
      </form>
    </div>
  </div>
</div>
