<?php
/**
 * @var string $version
 * @var ?array $update
 * @var array  $registration
 * @var string $reg_endpoint
 * @var string $reg_endpoint_default
 * @var string $reg_endpoint_override
 * @var bool   $reg_disabled
 * @var ?string $manifest
 * @var string $manifest_default
 * @var string $manifest_override
 * @var bool   $update_disabled
 */
?>
<div class="cols">
  <div class="col-list">
    <div class="card">
      <div class="card-head"><h2>Version &amp; Updates</h2><span class="pill">v<?= h($version) ?></span></div>
      <p class="muted">Release-Kanal: <code><?= h($manifest ?: '—') ?></code></p>

      <?php if ($update_disabled): ?>
        <div class="update-box"><p><strong>Update-Pruefung deaktiviert.</strong> Aktiviere sie unten, um wieder gegen den Release-Kanal zu pruefen.</p></div>
      <?php else: ?>
        <form method="post" action="<?= h(route('update_check')) ?>" class="inline">
          <?= csrf_field() ?>
          <button class="btn" type="submit">Auf Updates pruefen</button>
        </form>
      <?php endif; ?>

      <?php if ($update && !empty($update['ok'])): ?>
        <div class="update-box <?= !empty($update['newer']) ? 'update-avail' : '' ?>">
          <?php if (!empty($update['newer'])): ?>
            <p><strong>Update verfuegbar:</strong> Version <?= h($update['latest']) ?>
               (installiert <?= h($update['current']) ?>).</p>
            <?php if (!empty($update['url'])): ?><p><a href="<?= h($update['url']) ?>" target="_blank" rel="noopener">Release-Notes ansehen</a></p><?php endif; ?>
            <?php if (!$update_disabled): ?>
              <form method="post" action="<?= h(route('update_apply')) ?>" data-confirm="Update jetzt einspielen? config.php und Daten bleiben erhalten.">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit">Update einspielen</button>
              </form>
            <?php endif; ?>
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
      <h2>Benachrichtigungen <span class="muted">(optional, opt-in)</span></h2>
      <p class="muted">Nur wenn du es ausdruecklich moechtest: der Maintainer informiert dich ueber neue Releases und Sicherheitshinweise. Keine Lizenz, kein Account &mdash; die Community-Edition ist MIT-lizenziert und kostenlos. Uebertragen werden nur deine E-Mail, Domain und App-Version; niemals Zeiterfassungsdaten.</p>
      <p class="muted">Endpoint: <code><?= h($reg_endpoint ?: '—') ?></code></p>
      <?php if ($reg_disabled): ?>
        <div class="flash flash-err">Benachrichtigungen sind administrativ deaktiviert. Es werden keine Daten gesendet.</div>
      <?php endif; ?>
      <form method="post" action="<?= h(route('registration_save')) ?>">
        <?= csrf_field() ?>
        <label>Kontakt-E-Mail
          <input type="email" name="email" value="<?= h($registration['email'] ?? '') ?>" placeholder="du@example.com">
        </label>
        <label class="check">
          <input type="checkbox" name="opt_in" value="1" <?= !empty($registration['opt_in']) ? 'checked' : '' ?> <?= $reg_disabled ? 'disabled' : '' ?>>
          Ueber Updates und Sicherheitshinweise informieren (opt-in)
        </label>
        <?php if (!empty($registration['last_sent'])): ?>
          <p class="muted">Zuletzt gesendet: <?= h(fmt_dt($registration['last_sent'])) ?></p>
        <?php endif; ?>
        <div class="form-actions"><button class="btn btn-primary" type="submit" <?= $reg_disabled ? 'disabled' : '' ?>>Speichern</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <h2>Endpoints</h2>
  <p class="muted">Zwei URLs steuert die Community-Edition: wo sie nach neuen Releases schaut (<em>Update-Kanal</em>) und wohin die Opt-in-Benachrichtigungs-Anmeldung geht (<em>Benachrichtigungs-Endpoint</em>). <strong>Kein Lizenzserver</strong> &mdash; die Community-Edition hat keine Lizenz-Mechanik. Felder leer lassen = Standard aus <code>config.php</code>.</p>
  <form method="post" action="<?= h(route('endpoints_save')) ?>" class="endpoint-form">
    <?= csrf_field() ?>
    <label>Update-Manifest-URL
      <input type="url" name="manifest_override"
             value="<?= h($manifest_override) ?>"
             placeholder="z.B. <?= h($manifest_default) ?>"
             title="<?= h($manifest_override !== '' ? $manifest_override : $manifest_default) ?>">
      <small class="muted">Aktiv: <code><?= h($manifest ?: '—') ?></code></small>
    </label>
    <label class="check">
      <input type="checkbox" name="update_disabled" value="1" <?= $update_disabled ? 'checked' : '' ?>>
      Update-Pruefung deaktivieren
    </label>
    <label>Benachrichtigungs-Endpoint <span class="muted">(opt-in Update/Security-Mails)</span>
      <input type="url" name="registration_override"
             value="<?= h($reg_endpoint_override) ?>"
             placeholder="z.B. <?= h($reg_endpoint_default) ?>"
             title="<?= h($reg_endpoint_override !== '' ? $reg_endpoint_override : $reg_endpoint_default) ?>">
      <small class="muted">Aktiv: <code><?= h($reg_endpoint ?: '—') ?></code></small>
    </label>
    <label class="check">
      <input type="checkbox" name="registration_disabled" value="1" <?= $reg_disabled ? 'checked' : '' ?>>
      Benachrichtigungen deaktivieren (sperrt auch neue Opt-ins)
    </label>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Speichern</button></div>
  </form>
</div>
