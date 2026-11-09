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
$projects = [];      // id => ['name', 'client_id', 'category_ckey', 'category_label']
$workpackages = [];  // id => ['name', 'project_id', 'client_id']
foreach ($tasks as $t) {
    $clients[(int) $t['client_id']] = (string) $t['client_name'];
    $projects[(int) $t['project_id']] = [
        'name' => (string) $t['project_name'],
        'client_id' => (int) $t['client_id'],
        // Felix-Feedback 2026-11-09 (#143): Projektart in der Option-Label
        // mitfuehren, damit Felix intern / extern / Foerder schon im
        // Dropdown unterscheiden kann.
        'category_label' => (string) ($t['project_category_label'] ?? $t['category_label'] ?? ''),
        'category_ckey'  => (string) ($t['project_category_ckey']  ?? $t['category_ckey']  ?? ''),
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
  <?php /* Felix-Feedback 2026-11-09 (Fix #150): Label "Kunde" / "Projekt-
     traeger" wird im Dialog dynamisch nach Projektart gesetzt; bei
     internem Projekt wird das ganze Label versteckt. */ ?>
  <label class="tp-client-label" data-base-label="<?= h(t('nav.clients')) ?>">
    <span class="tp-client-label-text"><?= h(t('nav.clients')) ?></span>
    <select class="tp-client" aria-label="<?= h(t('tp.client_filter', 'Kunde filtern')) ?>">
      <option value=""><?= h(t('tp.all_clients', 'Alle Kunden')) ?></option>
      <?php foreach ($clients as $cid => $cname): ?>
        <option value="<?= (int) $cid ?>"><?= h($cname) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= h(t('projects.col_project')) ?>
    <select class="tp-project" aria-label="<?= h(t('tp.project_filter', 'Projekt filtern')) ?>">
      <option value=""><?= h(t('tp.all_projects', 'Alle Projekte')) ?></option>
      <?php /* Felix-Feedback 2026-11-09 (Fix #159): Kategorie-Prefix im
         Option-Text (z.B. "[Kundenprojekt] Antriebsstudie") ist
         redundant - die Projektart wird oben bereits gewaehlt und
         filtert die Projektliste. data-cat-ckey + data-cat-label
         bleiben als Datenattribute (fuer JS-Filterung + Edit-Modus). */ ?>
      <?php foreach ($projects as $pid => $p): ?>
        <option value="<?= (int) $pid ?>"
                data-client="<?= (int) $p['client_id'] ?>"
                data-cat-ckey="<?= h($p['category_ckey']) ?>"
                data-cat-label="<?= h($p['category_label']) ?>"><?= h($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= h(t('wp.single', 'Arbeitspaket')) ?>
    <select class="tp-wp" aria-label="<?= h(t('tp.wp_filter', 'Arbeitspaket filtern')) ?>">
      <option value=""><?= h(t('tp.all_wp', 'Alle Arbeitspakete')) ?></option>
      <?php foreach ($workpackages as $wid => $w): ?>
        <option value="<?= (int) $wid ?>" data-client="<?= (int) $w['client_id'] ?>" data-project="<?= (int) $w['project_id'] ?>"><?= h($w['name']) ?></option>
      <?php endforeach; ?>
      <?php foreach ($projectsWithLooseTasks as $pid => $cid): ?>
        <option value="0" data-client="<?= (int) $cid ?>" data-project="<?= (int) $pid ?>"><?= h(t('tp.wp_none_inline', '(ohne Arbeitspaket)')) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= h(t('dash.col_task')) ?>
    <select name="task_id" class="tp-task" required aria-label="<?= h(t('dash.col_task')) ?>">
      <option value=""><?= h(t('tp.pick_task', 'Aufgabe waehlen …')) ?></option>
      <?php foreach ($tasks as $t): ?>
        <option value="<?= (int) $t['id'] ?>"
          data-client="<?= (int) $t['client_id'] ?>"
          data-project="<?= (int) $t['project_id'] ?>"
          data-wp="<?= (int) ($t['work_package_id'] ?? 0) ?>"
          <?= $selectedTaskId == (int) $t['id'] ? 'selected' : '' ?>><?= h($t['name']) ?></option>
      <?php endforeach; ?>
      <?php /* Felix-Feedback 2026-11-09 (Fix #141): Pro-Variante erlaubt
         "+ Neue Aufgabe" als letzter Option - Klick blendet unter dem
         Select ein Inline-Input ein, das beim Speichern eine neue Task im
         gefilterten Projekt anlegt. */ ?>
      <option value="__new__" class="tp-opt-new">+ <?= h(t('tp.new_task', 'Neue Aufgabe')) ?></option>
    </select>
  </label>
  <?php /* Inline-Create-Panel. Default versteckt, JS klappt es bei
     task_id == __new__ aus und versteckt es sonst wieder. Die Parent-IDs
     (Client/Projekt/Arbeitspaket) werden beim Submit per JS aus den
     UX-Selects in die Hidden-Felder kopiert, damit der Server weiss, zu
     welchem Projekt die neue Aufgabe gehoert. */ ?>
  <div class="tp-new-task" hidden>
    <label><?= h(t('tp.new_task_name', 'Name der neuen Aufgabe')) ?>
      <input type="text" name="new_task_name" class="tp-new-task-name"
             maxlength="120"
             placeholder="<?= h(t('tp.new_task_placeholder', 'z.B. Konzept, Review, Deployment')) ?>">
    </label>
    <input type="hidden" name="new_task_project_id" class="tp-new-task-project" value="">
    <input type="hidden" name="new_task_wp_id"       class="tp-new-task-wp"      value="">
    <?php /* Felix-Feedback 2026-11-09 (Fix #150): Prosa-Hinweis "Waehle
       Kunde und Projekt oben" entfernt - durch die Hierarchie-Enforce-
       Logik kann "+ Neue Aufgabe" ohnehin erst gewaehlt werden, wenn der
       Parent-Context steht. */ ?>
  </div>
</div>
