<?php /** @var int $code @var string $message */ ?>
<div class="card">
  <h2><?= (int) $code ?></h2>
  <p><?= h($message) ?></p>
  <p><a class="btn" href="<?= h(route('dashboard')) ?>">Zum Dashboard</a></p>
</div>
