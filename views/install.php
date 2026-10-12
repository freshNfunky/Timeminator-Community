<?php /** @var array $checks @var bool $canProceed @var array $errors @var bool $done @var string $icsrf @var array $post */ ?>
<!doctype html>
<html lang="<?= h(I18n::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(t('install.title')) ?> · Timeminator</title>
<link rel="stylesheet" href="<?= h(asset('app.css')) ?>">
</head>
<body class="auth-body">
<div class="install-card">
  <div class="auth-brand">⏱ Timeminator</div>
  <p class="auth-sub"><?= h(t('install.title')) ?></p>

  <?php if ($done): ?>
    <div class="flash flash-ok"><?= h(t('install.done')) ?></div>
    <p class="muted"><?= t('install.delete_hint') /* contains <code>, intentional */ ?></p>
    <p><a class="btn btn-primary btn-block" href="<?= h(base_path() . '/index.php') ?>"><?= h(t('install.to_login')) ?></a></p>
  <?php else: ?>

    <?php foreach ($errors as $e): ?><div class="flash flash-err"><?= h($e) ?></div><?php endforeach; ?>

    <h3><?= h(t('install.sysreq')) ?></h3>
    <table class="table">
      <?php foreach ($checks as [$label, $ok, $extra]): ?>
        <tr><td><?= h($label) ?> <?= $extra ? '<span class="muted">' . h($extra) . '</span>' : '' ?></td>
            <td class="r"><?= $ok
                ? '<span class="ok-mark">' . h(t('install.ok')) . '</span>'
                : '<span class="bad-mark">' . h(t('install.missing')) . '</span>' ?></td></tr>
      <?php endforeach; ?>
    </table>

    <?php if (!$canProceed): ?>
      <div class="flash flash-err"><?= h(t('install.req_fail')) ?></div>
    <?php else: ?>
    <form method="post" action="<?= h(base_path() . '/install.php') ?>" class="install-form">
      <input type="hidden" name="_csrf" value="<?= h($icsrf) ?>">

      <h3><?= h(t('install.section_app')) ?></h3>
      <label><?= h(t('install.app_name_label')) ?> <input type="text" name="app_name" value="<?= h($post['app_name'] ?? 'Timeminator') ?>"></label>
      <label><?= h(t('install.timezone_label')) ?> <input type="text" name="timezone" value="<?= h($post['timezone'] ?? 'Europe/Berlin') ?>"></label>

      <h3><?= h(t('install.section_db')) ?></h3>
      <div class="db-toggle">
        <label class="check"><input type="radio" name="db_driver" value="mysql" <?= (($post['db_driver'] ?? '') === 'mysql') ? 'checked' : '' ?> data-toggle-target="mysqlBox"> <?= h(t('install.db_mysql')) ?></label>
        <label class="check"><input type="radio" name="db_driver" value="sqlite" <?= (($post['db_driver'] ?? 'sqlite') !== 'mysql') ? 'checked' : '' ?> data-toggle-target="|mysqlBox"> <?= h(t('install.db_sqlite')) ?></label>
      </div>
      <div id="mysqlBox" class="<?= (($post['db_driver'] ?? '') === 'mysql') ? '' : 'hidden' ?>">
        <div class="row">
          <label class="grow"><?= h(t('install.db_host')) ?> <input type="text" name="mysql_host" value="<?= h($post['mysql_host'] ?? 'localhost') ?>"></label>
          <label><?= h(t('install.db_port')) ?> <input type="number" name="mysql_port" value="<?= h($post['mysql_port'] ?? '3306') ?>"></label>
        </div>
        <label><?= h(t('install.db_name')) ?> <input type="text" name="mysql_db" value="<?= h($post['mysql_db'] ?? '') ?>"></label>
        <label><?= h(t('install.db_user')) ?> <input type="text" name="mysql_user" value="<?= h($post['mysql_user'] ?? '') ?>"></label>
        <label><?= h(t('install.db_password')) ?> <input type="password" name="mysql_pass" value=""></label>
      </div>

      <h3><?= h(t('install.section_admin')) ?></h3>
      <label><?= h(t('install.admin_username')) ?> <input type="text" name="admin_user" value="<?= h($post['admin_user'] ?? '') ?>" required></label>
      <label><?= h(t('install.admin_name')) ?> <input type="text" name="admin_name" value="<?= h($post['admin_name'] ?? '') ?>"></label>
      <div class="row">
        <label class="grow"><?= h(t('install.admin_password')) ?> <input type="password" name="admin_pass" required></label>
        <label class="grow"><?= h(t('install.admin_password_repeat')) ?> <input type="password" name="admin_pass2" required></label>
      </div>

      <h3><?= h(t('install.section_registry')) ?> <span class="muted"><?= h(t('install.reg_optional')) ?></span></h3>
      <p class="muted"><?= h(t('install.reg_hint')) ?></p>
      <label class="check"><input type="checkbox" name="reg_opt_in" value="1"> <?= h(t('install.reg_opt_in')) ?></label>
      <label><?= h(t('install.admin_email')) ?> <input type="email" name="reg_email" value="<?= h($post['reg_email'] ?? '') ?>"></label>

      <details>
        <summary class="summary-adv"><?= h(t('install.section_urls')) ?></summary>
        <p class="muted"><?= h(t('install.urls_hint')) ?></p>
        <label><?= h(t('install.update_manifest_url')) ?>
          <input type="url" name="update_manifest_url" value="<?= h($post['update_manifest_url'] ?? 'https://api.github.com/repos/freshNfunky/Timeminator-Community/releases/latest') ?>">
        </label>
        <label class="check"><input type="checkbox" name="disable_update_check" value="1" <?= !empty($post['disable_update_check']) ? 'checked' : '' ?>> <?= h(t('install.disable_update_check')) ?></label>
        <label><?= h(t('install.registration_endpoint')) ?>
          <input type="url" name="registration_endpoint" value="<?= h($post['registration_endpoint'] ?? 'https://license.felixschaller.com/timeminator-registry/register.php') ?>">
        </label>
        <label class="check"><input type="checkbox" name="disable_registration" value="1" <?= !empty($post['disable_registration']) ? 'checked' : '' ?>> <?= h(t('install.disable_registration')) ?></label>
      </details>

      <button class="btn btn-primary btn-block" type="submit"><?= h(t('install.submit')) ?></button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
  <?php require __DIR__ . '/partials/lang_switcher.php'; ?>
</div>
<script src="<?= h(asset('app.js')) ?>"></script>
</body>
</html>
