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
 * @var bool   $banner_config_off
 * @var bool   $banner_visible
 * @var bool   $banner_telemetry
 * @var string $banner_endpoint
 * @var string $banner_endpoint_default
 * @var string $banner_endpoint_override
 * @var string $install_id
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

    <div class="card">
      <h2>Community-Banner &amp; Telemetrie</h2>
      <?php if ($banner_config_off): ?>
        <p class="muted">Das Banner ist in <code>config.php</code> fest deaktiviert (<code>banners.enabled =&gt; false</code>, z. B. Pro-Edition). Die folgenden Schalter haben dann keine Wirkung.</p>
      <?php else: ?>
        <p class="muted">Die Community-Edition finanziert sich ueber eine schlanke Banner-Spalte mit Angeboten und Neuigkeiten von FelixSchallerCOM. Die Motive werden von einer konfigurierbaren Subdomain geladen; ist sie nicht erreichbar, zeigt die App den mitgelieferten Standard-Satz. Mit dem Abruf wird ein anonymisiertes Nutzungssignal gesendet (App-Version, zufaellige Installations-ID, ein Hash davon, und die gekuerzte IP) &ndash; niemals Zeiterfassungsdaten. Details und Abschaltung siehe <code>docs/BANNERS.md</code>.</p>
      <?php endif; ?>
      <form method="post" action="<?= h(route('banners_save')) ?>">
        <?= csrf_field() ?>
        <label class="check">
          <input type="checkbox" name="banner_visible" value="1" <?= $banner_visible ? 'checked' : '' ?> <?= $banner_config_off ? 'disabled' : '' ?>>
          Banner-Spalte anzeigen
        </label>
        <label class="check">
          <input type="checkbox" name="banner_telemetry" value="1" <?= $banner_telemetry ? 'checked' : '' ?> <?= $banner_config_off ? 'disabled' : '' ?>>
          Anonymisiertes Nutzungssignal senden (Telemetrie)
        </label>
        <label>Banner-Endpoint
          <input type="url" name="banner_endpoint_override" value="<?= h($banner_endpoint_override) ?>" placeholder="<?= h($banner_endpoint_default ?: 'https://banners.felixschaller.com/feed.json') ?>" <?= $banner_config_off ? 'disabled' : '' ?>>
        </label>
        <p class="muted">Aktiver Endpoint: <code><?= h($banner_endpoint ?: '—') ?></code></p>
        <p class="muted">Installations-ID: <code><?= h($install_id) ?></code></p>
        <div class="form-actions"><button class="btn btn-primary" type="submit" <?= $banner_config_off ? 'disabled' : '' ?>>Speichern</button></div>
      </form>
    </div>

    <div class="card">
      <h2>Endpoints</h2>
      <p class="muted">Update-Kanal und Registrierungs-Endpoint sind vom Installer vorbelegt. Hier kannst du sie ueberschreiben oder ganz abschalten. Leer lassen = Standard aus <code>config.php</code>.</p>
      <form method="post" action="<?= h(route('endpoints_save')) ?>">
        <?= csrf_field() ?>
        <label>Update-Manifest-URL
          <input type="url" name="manifest_override" value="<?= h($manifest_override) ?>" placeholder="<?= h($manifest_default) ?>">
        </label>
        <label class="check">
          <input type="checkbox" name="update_disabled" value="1" <?= $update_disabled ? 'checked' : '' ?>>
          Update-Pruefung deaktivieren
        </label>
        <label>Registrierungs-Endpoint
          <input type="url" name="registration_override" value="<?= h($reg_endpoint_override) ?>" placeholder="<?= h($reg_endpoint_default) ?>">
        </label>
        <label class="check">
          <input type="checkbox" name="registration_disabled" value="1" <?= $reg_disabled ? 'checked' : '' ?>>
          Registrierung deaktivieren (sperrt auch neue Opt-ins)
        </label>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Speichern</button></div>
      </form>
    </div>
  </div>

  <div class="col-form">
    <div class="card">
      <h2>Registrierung (optional)</h2>
      <p class="muted">Rein freiwillig. Uebertragen werden nur Kontakt-E-Mail, Domain und Version - niemals Zeiterfassungsdaten. Jederzeit abschaltbar.</p>
      <p class="muted">Endpoint: <code><?= h($reg_endpoint ?: '—') ?></code></p>
      <?php if ($reg_disabled): ?>
        <div class="flash flash-err">Registrierung ist administrativ deaktiviert. Es werden keine Daten gesendet.</div>
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
