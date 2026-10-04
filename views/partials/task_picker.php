<?php
/**
 * Cascading task picker: Kunde → Projekt → Arbeitspaket → Aufgabe.
 *
 * Replaces the single flat "task_id" select whose optgroup labels crammed
 * the whole hierarchy onto one line. The four selects filter each other
 * client-side via `assets/app.js` (initTaskPickers): changing a parent
 * narrows the child options to only matching ones and resets any outdated
 * child selection. The last select is the real form field (`task_id`) —
 * everything above it is UX only; no new server round-trips.
 *
 * @var array $tasks            flat list from Repo::tasks(true)
 * @var int   $selectedTaskId   preselect this task + its parents (edit mode)
 * @var string $wrapClass       extra CSS class on the wrapping container
 */
$selectedTaskId = $selectedTaskId ?? 0;
$wrapClass = $wrapClass ?? '';

// Derive the three parent sets from $tasks so the controllers don't have
// to pass them separately. Each parent is sorted by name for the UI.
$clients = [];       // id => name
$projects = [];      // id => ['name', 'client_id']
$workpackages = [];  // id => ['name', 'project_id', 'client_id']
foreach ($tasks as $t) {
    $clients[(int) $t['client_id']] = (string) $t['client_name'];
    $projects[(int) $t['project_id']] = [
        'name' => (string) $t['project_name'],
        'client_id' => (int) $t['client_id'],
    ];
    $wpId = (int) ($t['work_package_id'] ?? 0);
    if ($wpId > 0) {
        $workpackages[$wpId] = [
            'name' => (string) ($t['work_package_name'] ?? ''),
            'project_id' => (int) $t['project_id'],
            'client_id' => (int) $t['client_id'],
        ];
    }
}
asort($clients, SORT_NATURAL | SORT_FLAG_CASE);
uasort($projects, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
uasort($workpackages, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

// For every project that has at least one task without a work package,
// surface a synthetic "(ohne Arbeitspaket)" option under that project
// (value `0` means "no WP"; tasks without WP carry `data-wp="0"`).
$projectsWithLooseTasks = []; // project_id => client_id
foreach ($tasks as $t) {
    if (((int) ($t['work_package_id'] ?? 0)) === 0) {
        $projectsWithLooseTasks[(int) $t['project_id']] = (int) $t['client_id'];
    }
}
?>
<div class="task-picker <?= h($wrapClass) ?>" data-selected-task="<?= (int) $selectedTaskId ?>">
  <label>Kunde
    <select class="tp-client" aria-label="Kunde filtern">
      <option value="">Alle Kunden</option>
      <?php foreach ($clients as $cid => $cname): ?>
        <option value="<?= (int) $cid ?>"><?= h($cname) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Projekt
    <select class="tp-project" aria-label="Projekt filtern">
      <option value="">Alle Projekte</option>
      <?php foreach ($projects as $pid => $p): ?>
        <option value="<?= (int) $pid ?>" data-client="<?= (int) $p['client_id'] ?>"><?= h($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Arbeitspaket
    <select class="tp-wp" aria-label="Arbeitspaket filtern">
      <option value="">Alle Arbeitspakete</option>
      <?php foreach ($workpackages as $wid => $w): ?>
        <option value="<?= (int) $wid ?>" data-client="<?= (int) $w['client_id'] ?>" data-project="<?= (int) $w['project_id'] ?>"><?= h($w['name']) ?></option>
      <?php endforeach; ?>
      <?php foreach ($projectsWithLooseTasks as $pid => $cid): ?>
        <option value="0" data-client="<?= (int) $cid ?>" data-project="<?= (int) $pid ?>">(ohne Arbeitspaket)</option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Aufgabe
    <select name="task_id" class="tp-task" required aria-label="Aufgabe">
      <option value="">Aufgabe waehlen …</option>
      <?php foreach ($tasks as $t): ?>
        <option value="<?= (int) $t['id'] ?>"
          data-client="<?= (int) $t['client_id'] ?>"
          data-project="<?= (int) $t['project_id'] ?>"
          data-wp="<?= (int) ($t['work_package_id'] ?? 0) ?>"
          <?= $selectedTaskId == (int) $t['id'] ? 'selected' : '' ?>><?= h($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
</div>
