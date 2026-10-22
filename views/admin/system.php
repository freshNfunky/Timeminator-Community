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
 * @var string $theme_preference
 * @var array  $working_days
 * @var string $sentiment_granularity
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
      <div class="card-head"><h2><?= h(t('system.updates_h2', 'Version &amp; Updates')) ?></h2><span class="pill">v<?= h($version) ?></span></div>
      <p class="muted"><?= h(t('system.release_channel_label', 'Release-Kanal:')) ?> <code><?= h($manifest ?: '—') ?></code></p>

      <?php if ($update_disabled): ?>
        <div class="update-box"><p><strong><?= h(t('system.update_check_disabled_title', 'Update-Pruefung deaktiviert.')) ?></strong> <?= h(t('system.update_check_disabled_hint', 'Aktiviere sie unten, um wieder gegen den Release-Kanal zu pruefen.')) ?></p></div>
      <?php else: ?>
        <form method="post" action="<?= h(route('update_check')) ?>" class="inline">
          <?= csrf_field() ?>
          <button class="btn" type="submit"><?= h(t('system.check_updates', 'Auf Updates pruefen')) ?></button>
        </form>
      <?php endif; ?>

      <?php if ($update && !empty($update['ok'])): ?>
        <div class="update-box <?= !empty($update['newer']) ? 'update-avail' : '' ?>">
          <?php if (!empty($update['newer'])): ?>
            <p><strong><?= h(t('system.update_available_label', 'Update verfuegbar:')) ?></strong>
               <?= h(sprintf(t('system.update_available_detail', 'Version %s (installiert %s).'), $update['latest'], $update['current'])) ?></p>
            <?php if (!empty($update['url'])): ?><p><a href="<?= h($update['url']) ?>" target="_blank" rel="noopener"><?= h(t('system.release_notes_link', 'Release-Notes ansehen')) ?></a></p><?php endif; ?>
            <?php if (!$update_disabled): ?>
              <form method="post" action="<?= h(route('update_apply')) ?>" data-confirm="<?= h(t('system.update_apply_confirm', 'Update jetzt einspielen? config.php und Daten bleiben erhalten.')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit"><?= h(t('system.apply_update', 'Update einspielen')) ?></button>
              </form>
            <?php endif; ?>
          <?php else: ?>
            <p><?= h(sprintf(t('system.update_current', 'Timeminator ist aktuell (v%s). Zuletzt geprueft: %s.'), $update['current'], fmt_dt($update['checked_at'] ?? null))) ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2><?= h(t('system.perms_h2', 'Rechte-Katalog')) ?><?= tip('system.perms_hint') ?></h2>
      <form method="post" action="<?= h(route('admin_system')) ?>" class="inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="sync_perms">
        <button class="btn" type="submit"><?= h(t('system.sync_perms', 'Rechte synchronisieren')) ?></button>
      </form>
    </div>

    <div class="card">
      <h2><?= h(t('system.theme_h2', 'Darstellung')) ?><?= tip('system.theme_hint') ?></h2>
      <form method="post" action="<?= h(route('theme_save')) ?>">
        <?= csrf_field() ?>
        <label class="check">
          <input type="radio" name="theme_preference" value="system" <?= $theme_preference === 'system' ? 'checked' : '' ?>>
          <?= h(t('system.theme_system', 'System (OS-Einstellung)')) ?>
        </label>
        <label class="check">
          <input type="radio" name="theme_preference" value="light" <?= $theme_preference === 'light' ? 'checked' : '' ?>>
          <?= h(t('system.theme_light', 'Hell')) ?>
        </label>
        <label class="check">
          <input type="radio" name="theme_preference" value="dark" <?= $theme_preference === 'dark' ? 'checked' : '' ?>>
          <?= h(t('system.theme_dark', 'Dunkel')) ?>
        </label>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button></div>
      </form>
    </div>

    <div class="card">
      <h2><?= h(t('system.working_days_h2', 'Werktage')) ?><?= tip('system.working_days_hint') ?></h2>
      <form method="post" action="<?= h(route('working_days_save')) ?>">
        <?= csrf_field() ?>
        <?php
          $__dayLabels = [
              1 => t('cal.day_short.mon', 'Mo'),
              2 => t('cal.day_short.tue', 'Di'),
              3 => t('cal.day_short.wed', 'Mi'),
              4 => t('cal.day_short.thu', 'Do'),
              5 => t('cal.day_short.fri', 'Fr'),
              6 => t('cal.day_short.sat', 'Sa'),
              7 => t('cal.day_short.sun', 'So'),
          ];
        ?>
        <div class="calimport-days">
          <?php foreach ($__dayLabels as $__dow => $__lbl): ?>
            <label class="check calimport-day<?= in_array($__dow, [6,7], true) ? ' calimport-day-weekend' : '' ?>">
              <input type="checkbox" name="working_days[]" value="<?= $__dow ?>" <?= in_array($__dow, $working_days, true) ? 'checked' : '' ?>>
              <?= h($__lbl) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button></div>
      </form>
    </div>

    <div class="card">
      <h2><?= h(t('system.sentiment_granularity_h2', 'Stimmungs-Rhythmus')) ?><?= tip('system.sentiment_granularity_hint', 'Wie oft wird nach Mood und Flow gefragt? Taeglich = ein Eintrag pro Tag; Woechentlich = ein Preset fuer die ganze Woche (bereits einzeln gesetzte Tage werden nicht ueberschrieben).') ?></h2>
      <form method="post" action="<?= h(route('sentiment_granularity_save')) ?>">
        <?= csrf_field() ?>
        <?php foreach (Sentiment::GRANULARITIES as $__g => $__defaultLabel): ?>
          <label class="check">
            <input type="radio" name="granularity" value="<?= h($__g) ?>" <?= $sentiment_granularity === $__g ? 'checked' : '' ?>>
            <?= h(t('sent.granularity_' . $__g, $__defaultLabel)) ?>
          </label>
        <?php endforeach; ?>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button></div>
      </form>
    </div>

    <div class="card">
      <h2><?= h(t('admin.language_section', 'Sprache')) ?><?= tip('admin.language_hint') ?></h2>
      <form method="post" action="<?= h(route('language_save')) ?>">
        <?= csrf_field() ?>
        <?php foreach (I18n::available() as $__loc): ?>
          <label class="check">
            <input type="radio" name="locale" value="<?= h($__loc['code']) ?>" <?= I18n::current() === $__loc['code'] ? 'checked' : '' ?>>
            <?= h($__loc['native']) ?> <span class="muted">(<?= h($__loc['label']) ?>)</span>
          </label>
        <?php endforeach; ?>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button></div>
      </form>
    </div>

    <div class="card">
      <h2><?= h(t('system.banner_h2', 'Community-Banner &amp; Telemetrie')) ?></h2>
      <?php if ($banner_config_off): ?>
        <p class="muted"><?= t('system.banner_config_off', 'Das Banner ist in <code>config.php</code> fest deaktiviert (<code>banners.enabled =&gt; false</code>, z. B. Pro-Edition). Die folgenden Schalter haben dann keine Wirkung.') /* contains code tags */ ?></p>
      <?php else: ?>
        <p class="muted"><?= t('system.banner_intro', 'Die Community-Edition finanziert sich ueber eine schlanke Banner-Spalte mit Angeboten und Neuigkeiten von FelixSchallerCOM. Die Motive werden von einer konfigurierbaren Subdomain geladen; ist sie nicht erreichbar, zeigt die App den mitgelieferten Standard-Satz. Mit dem Abruf wird ein anonymisiertes Nutzungssignal gesendet (App-Version, zufaellige Installations-ID, ein Hash davon, und die gekuerzte IP) &ndash; niemals Zeiterfassungsdaten. Details und Abschaltung siehe <code>docs/BANNERS.md</code>.') /* contains code tags */ ?></p>
      <?php endif; ?>
      <form method="post" action="<?= h(route('banners_save')) ?>">
        <?= csrf_field() ?>
        <label class="check">
          <input type="checkbox" name="banner_visible" value="1" <?= $banner_visible ? 'checked' : '' ?> <?= $banner_config_off ? 'disabled' : '' ?>>
          <?= h(t('system.banner_show', 'Banner-Spalte anzeigen')) ?>
        </label>
        <label class="check">
          <input type="checkbox" name="banner_telemetry" value="1" <?= $banner_telemetry ? 'checked' : '' ?> <?= $banner_config_off ? 'disabled' : '' ?>>
          <?= h(t('system.banner_telemetry', 'Anonymisiertes Nutzungssignal senden (Telemetrie)')) ?>
        </label>
        <label><?= h(t('system.banner_endpoint', 'Banner-Endpoint')) ?>
          <input type="url" name="banner_endpoint_override" value="<?= h($banner_endpoint_override) ?>" placeholder="<?= h($banner_endpoint_default ?: 'https://assets.felixschaller.com/feed.json') ?>" <?= $banner_config_off ? 'disabled' : '' ?>>
        </label>
        <p class="muted"><?= h(t('system.banner_active_endpoint', 'Aktiver Endpoint:')) ?> <code><?= h($banner_endpoint ?: '—') ?></code></p>
        <p class="muted"><?= h(t('system.banner_install_id', 'Installations-ID:')) ?> <code><?= h($install_id) ?></code></p>
        <div class="form-actions"><button class="btn btn-primary" type="submit" <?= $banner_config_off ? 'disabled' : '' ?>><?= h(t('common.save')) ?></button></div>
      </form>
    </div>

    <div class="card">
      <h2><?= h(t('system.endpoints_h2', 'Endpoints')) ?><?= tip('system.endpoints_intro_short') ?></h2>
      <form method="post" action="<?= h(route('endpoints_save')) ?>">
        <?= csrf_field() ?>
        <label><?= h(t('install.update_manifest_url')) ?>
          <input type="url" name="manifest_override" value="<?= h($manifest_override) ?>" placeholder="<?= h($manifest_default) ?>">
        </label>
        <label class="check">
          <input type="checkbox" name="update_disabled" value="1" <?= $update_disabled ? 'checked' : '' ?>>
          <?= h(t('install.disable_update_check')) ?>
        </label>
        <label><?= h(t('install.registration_endpoint')) ?>
          <input type="url" name="registration_override" value="<?= h($reg_endpoint_override) ?>" placeholder="<?= h($reg_endpoint_default) ?>">
        </label>
        <label class="check">
          <input type="checkbox" name="registration_disabled" value="1" <?= $reg_disabled ? 'checked' : '' ?>>
          <?= h(t('system.disable_registration_optins', 'Registrierung deaktivieren (sperrt auch neue Opt-ins)')) ?>
        </label>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button></div>
      </form>
    </div>
  </div>

  <div class="col-form">
    <div class="card">
      <h2><?= h(t('system.notify_h2', 'Benachrichtigungen')) ?> <span class="muted"><?= h(t('system.notify_optional_label', '(optional, opt-in)')) ?></span></h2>
      <p class="muted"><?= t('system.notify_intro', 'Nur wenn du es ausdruecklich moechtest: der Maintainer informiert dich ueber neue Releases und Sicherheitshinweise. Keine Lizenz, kein Account &mdash; die Community-Edition ist MIT-lizenziert und kostenlos. Uebertragen werden nur deine E-Mail, Domain und App-Version; niemals Zeiterfassungsdaten.') /* contains &mdash; entity */ ?></p>
      <p class="muted"><?= h(t('system.endpoint_label', 'Endpoint:')) ?> <code><?= h($reg_endpoint ?: '—') ?></code></p>
      <?php if ($reg_disabled): ?>
        <div class="flash flash-err"><?= h(t('system.notify_disabled_flash', 'Benachrichtigungen sind administrativ deaktiviert. Es werden keine Daten gesendet.')) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= h(route('registration_save')) ?>">
        <?= csrf_field() ?>
        <label><?= h(t('system.contact_email', 'Kontakt-E-Mail')) ?>
          <input type="email" name="email" value="<?= h($registration['email'] ?? '') ?>" placeholder="du@example.com">
        </label>
        <label class="check">
          <input type="checkbox" name="opt_in" value="1" <?= !empty($registration['opt_in']) ? 'checked' : '' ?> <?= $reg_disabled ? 'disabled' : '' ?>>
          <?= h(t('system.notify_opt_in', 'Ueber Updates und Sicherheitshinweise informieren (opt-in)')) ?>
        </label>
        <?php if (!empty($registration['last_sent'])): ?>
          <p class="muted"><?= h(t('system.notify_last_sent', 'Zuletzt gesendet:')) ?> <?= h(fmt_dt($registration['last_sent'])) ?></p>
        <?php endif; ?>
        <div class="form-actions"><button class="btn btn-primary" type="submit" <?= $reg_disabled ? 'disabled' : '' ?>><?= h(t('common.save')) ?></button></div>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <h2><?= h(t('system.endpoints_h2', 'Endpoints')) ?><?= tip('system.endpoints_intro_long') ?></h2>
  <form method="post" action="<?= h(route('endpoints_save')) ?>" class="endpoint-form">
    <?= csrf_field() ?>
    <label><?= h(t('install.update_manifest_url')) ?>
      <input type="url" name="manifest_override"
             value="<?= h($manifest_override) ?>"
             placeholder="<?= h($manifest_default) ?>"
             title="<?= h($manifest_override !== '' ? $manifest_override : $manifest_default) ?>">
      <small class="muted"><?= h(t('system.endpoint_active_label', 'Aktiv:')) ?> <code><?= h($manifest ?: '—') ?></code></small>
    </label>
    <label class="check">
      <input type="checkbox" name="update_disabled" value="1" <?= $update_disabled ? 'checked' : '' ?>>
      <?= h(t('system.disable_update_check_label', 'Update-Pruefung deaktivieren')) ?>
    </label>
    <label><?= h(t('system.notify_endpoint_label', 'Benachrichtigungs-Endpoint')) ?> <span class="muted"><?= h(t('system.notify_endpoint_hint', '(opt-in Update/Security-Mails)')) ?></span>
      <input type="url" name="registration_override"
             value="<?= h($reg_endpoint_override) ?>"
             placeholder="<?= h($reg_endpoint_default) ?>"
             title="<?= h($reg_endpoint_override !== '' ? $reg_endpoint_override : $reg_endpoint_default) ?>">
      <small class="muted"><?= h(t('system.endpoint_active_label', 'Aktiv:')) ?> <code><?= h($reg_endpoint ?: '—') ?></code></small>
    </label>
    <label class="check">
      <input type="checkbox" name="registration_disabled" value="1" <?= $reg_disabled ? 'checked' : '' ?>>
      <?= h(t('system.disable_notify_label', 'Benachrichtigungen deaktivieren (sperrt auch neue Opt-ins)')) ?>
    </label>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button></div>
  </form>
</div>

<div class="card">
  <h2><?= h(t('mail.h2', 'Mail (SMTP)')) ?><?= tip('mail.intro') ?></h2>
  <?php if (!$mail_configured): ?>
    <p class="muted"><em><?= h(t('mail.not_configured_hint', 'Noch nicht konfiguriert — Password-Reset-Mails koennen nicht verschickt werden, bis zumindest eine From-Adresse gesetzt ist.')) ?></em></p>
  <?php endif; ?>
  <form method="post" action="<?= h(route('mail_save')) ?>" class="endpoint-form">
    <?= csrf_field() ?>
    <label><?= h(t('mail.from', 'Absender-Adresse (From)')) ?>
      <input type="email" name="mail_from" value="<?= h($mail['from']) ?>" placeholder="no-reply@example.com" required>
    </label>
    <label><?= h(t('mail.from_name', 'Absender-Name')) ?>
      <input type="text" name="mail_from_name" value="<?= h($mail['from_name']) ?>" placeholder="<?= h(cfg('app_name', 'Timeminator')) ?>">
    </label>
    <label><?= h(t('mail.host', 'SMTP-Host')) ?><?= tip('mail.host_hint') ?>
      <input type="text" name="mail_host" value="<?= h($mail['host']) ?>" placeholder="smtp.example.com">
    </label>
    <label><?= h(t('mail.port', 'Port')) ?>
      <input type="number" name="mail_port" value="<?= h((string) ($mail['port'] ?: '')) ?>" min="0" max="65535" placeholder="587">
    </label>
    <label><?= h(t('mail.security', 'Verschluesselung')) ?>
      <select name="mail_security">
        <option value="none" <?= $mail['security'] === 'none' ? 'selected' : '' ?>>none</option>
        <option value="tls"  <?= $mail['security'] === 'tls'  ? 'selected' : '' ?>>STARTTLS</option>
        <option value="ssl"  <?= $mail['security'] === 'ssl'  ? 'selected' : '' ?>>SSL/TLS</option>
      </select>
    </label>
    <label><?= h(t('mail.user', 'SMTP-Benutzer')) ?>
      <input type="text" name="mail_user" value="<?= h($mail['user']) ?>" autocomplete="off">
    </label>
    <label><?= h(t('mail.pass', 'SMTP-Passwort')) ?>
      <input type="password" name="mail_pass" value="" autocomplete="new-password" placeholder="<?= $mail_has_password ? h(t('mail.pass_kept', '(gespeichert — leer lassen, um nichts zu aendern)')) : '' ?>">
    </label>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button></div>
  </form>
  <form method="post" action="<?= h(route('mail_test')) ?>" class="endpoint-form" style="margin-top:12px">
    <?= csrf_field() ?>
    <label><?= h(t('mail.test_to', 'Test-Mail an')) ?><?= tip('mail.test_hint') ?>
      <input type="email" name="test_to" value="<?= h((string) (Auth::user()['email'] ?? '')) ?>" placeholder="du@example.com">
    </label>
    <div class="form-actions">
      <button class="btn btn-ghost" type="submit" <?= $mail_configured ? '' : 'disabled' ?>><?= h(t('mail.test_submit', 'Test-Mail schicken')) ?></button>
    </div>
  </form>
</div>
