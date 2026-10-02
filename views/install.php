<?php /** @var array $checks @var bool $canProceed @var array $errors @var bool $done @var string $icsrf @var array $post */ ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation · Timeminator</title>
<link rel="stylesheet" href="<?= h(asset('app.css')) ?>">
</head>
<body class="auth-body">
<div class="install-card">
  <div class="auth-brand">⏱ Timeminator</div>
  <p class="auth-sub">Installation</p>

  <?php if ($done): ?>
    <div class="flash flash-ok">Installation abgeschlossen. Du kannst dich jetzt anmelden.</div>
    <p class="muted">Aus Sicherheitsgruenden kannst du <code>install.php</code> nun loeschen.</p>
    <p><a class="btn btn-primary btn-block" href="<?= h(base_path() . '/index.php') ?>">Zur Anmeldung</a></p>
  <?php else: ?>

    <?php foreach ($errors as $e): ?><div class="flash flash-err"><?= h($e) ?></div><?php endforeach; ?>

    <h3>Systemvoraussetzungen</h3>
    <table class="table">
      <?php foreach ($checks as [$label, $ok, $extra]): ?>
        <tr><td><?= h($label) ?> <?= $extra ? '<span class="muted">' . h($extra) . '</span>' : '' ?></td>
            <td class="r"><?= $ok ? '<span class="ok-mark">OK</span>' : '<span class="bad-mark">fehlt</span>' ?></td></tr>
      <?php endforeach; ?>
    </table>

    <?php if (!$canProceed): ?>
      <div class="flash flash-err">Bitte zuerst die fehlenden Voraussetzungen beheben (PDO und Schreibrechte auf das Verzeichnis).</div>
    <?php else: ?>
    <form method="post" action="<?= h(base_path() . '/install.php') ?>" class="install-form">
      <input type="hidden" name="_csrf" value="<?= h($icsrf) ?>">

      <h3>Anwendung</h3>
      <label>App-Name <input type="text" name="app_name" value="<?= h($post['app_name'] ?? 'Timeminator') ?>"></label>
      <label>Zeitzone <input type="text" name="timezone" value="<?= h($post['timezone'] ?? 'Europe/Berlin') ?>"></label>

      <h3>Datenbank</h3>
      <div class="db-toggle">
        <label class="check"><input type="radio" name="db_driver" value="mysql" <?= (($post['db_driver'] ?? '') === 'mysql') ? 'checked' : '' ?> data-toggle-target="mysqlBox"> MySQL / MariaDB (Hosting)</label>
        <label class="check"><input type="radio" name="db_driver" value="sqlite" <?= (($post['db_driver'] ?? 'sqlite') !== 'mysql') ? 'checked' : '' ?> data-toggle-target="|mysqlBox"> SQLite (lokal, ohne Server)</label>
      </div>
      <div id="mysqlBox" class="<?= (($post['db_driver'] ?? '') === 'mysql') ? '' : 'hidden' ?>">
        <div class="row">
          <label class="grow">Host <input type="text" name="mysql_host" value="<?= h($post['mysql_host'] ?? 'localhost') ?>"></label>
          <label>Port <input type="number" name="mysql_port" value="<?= h($post['mysql_port'] ?? '3306') ?>"></label>
        </div>
        <label>Datenbank <input type="text" name="mysql_db" value="<?= h($post['mysql_db'] ?? '') ?>"></label>
        <label>Benutzer <input type="text" name="mysql_user" value="<?= h($post['mysql_user'] ?? '') ?>"></label>
        <label>Passwort <input type="password" name="mysql_pass" value=""></label>
      </div>

      <h3>Administrator-Konto</h3>
      <label>Benutzername <input type="text" name="admin_user" value="<?= h($post['admin_user'] ?? '') ?>" required></label>
      <label>Anzeigename <input type="text" name="admin_name" value="<?= h($post['admin_name'] ?? '') ?>"></label>
      <div class="row">
        <label class="grow">Passwort <input type="password" name="admin_pass" required></label>
        <label class="grow">Passwort wiederholen <input type="password" name="admin_pass2" required></label>
      </div>

      <h3>Registrierung <span class="muted">(optional, opt-in)</span></h3>
      <p class="muted">Nur wenn gewuenscht: informiert dich der Maintainer ueber Updates. Es werden nur E-Mail, Domain und Version gesendet - keine Zeiterfassungsdaten.</p>
      <label class="check"><input type="checkbox" name="reg_opt_in" value="1"> Ja, ueber Updates informieren</label>
      <label>E-Mail <input type="email" name="reg_email" value="<?= h($post['reg_email'] ?? '') ?>"></label>

      <details>
        <summary class="summary-adv">Erweitert: Update- und Registrierungs-URLs</summary>
        <p class="muted">Die Voreinstellungen zeigen auf den offiziellen Timeminator-Kanal. Fork-Betreiber koennen hier auf einen eigenen Manifest- oder Registry-Server umbiegen, oder beides ganz abschalten.</p>
        <label>Update-Manifest-URL
          <input type="url" name="update_manifest_url" value="<?= h($post['update_manifest_url'] ?? 'https://api.github.com/repos/freshNfunky/Timeminator-Community/releases/latest') ?>">
        </label>
        <label class="check"><input type="checkbox" name="disable_update_check" value="1" <?= !empty($post['disable_update_check']) ? 'checked' : '' ?>> Update-Pruefung deaktivieren</label>
        <label>Registrierungs-Endpoint
          <input type="url" name="registration_endpoint" value="<?= h($post['registration_endpoint'] ?? 'https://license.felixschaller.com/timeminator-registry/register.php') ?>">
        </label>
        <label class="check"><input type="checkbox" name="disable_registration" value="1" <?= !empty($post['disable_registration']) ? 'checked' : '' ?>> Registrierung ganz deaktivieren</label>
      </details>

      <button class="btn btn-primary btn-block" type="submit">Installieren</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
<script src="<?= h(asset('app.js')) ?>"></script>
</body>
</html>
