<?php
declare(strict_types=1);

/**
 * Presets-Hub (v1.0 F6, Epic #146, portiert aus Pro). Sammelt alle
 * Katalog-/Vorlagen-Einstellungen auf einer Seite in Sektionen. Community
 * bringt zwei Sektionen (Projektarten + Prozessarten) + Pro-Teaser-Row
 * fuer die vier Pro-only Sektionen (Abrechnungskategorien, Textvorlagen,
 * Senioritaetsstufen, Arbeitstage).
 */
function ctrl_presets_index(): void
{
    require_perm('structure.manage');
    view('presets/index', [
        'project_categories' => Repo::projectCategories(),
        'process_categories' => ProcessRepo::categories(),
        'pro_url'            => (string) cfg('pro_url', 'https://timeminator.felixschaller.com'),
    ], t('presets.title', 'Presets'));
}

// ---------- Projektarten (Admin-Katalog) ----------

function ctrl_project_categories_index(): void
{
    require_perm('structure.manage');
    view('structure/project_categories', [
        'categories' => Repo::projectCategories(),
    ], t('project_cat.title', 'Projektarten'));
}

function ctrl_project_category_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id    = post_int('id');
    $label = trim((string) post('label'));
    if ($label === '') {
        flash(t('project_cat.err_label', 'Label fehlt.'), 'err');
        redirect_route('project_categories');
    }
    $d = [
        'label'        => $label,
        'needs_client' => post('needs_client') ? 1 : 0,
        'client_label' => trim((string) post('client_label')) ?: null,
        'position'     => post_int('position', 0),
        'active'       => post('active') ? 1 : 0,
    ];
    if ($id) {
        Repo::saveProjectCategory($d, $id);
        flash(t('project_cat.flash_updated', 'Projektart aktualisiert.'));
    } else {
        $ckey = strtolower(trim((string) post('ckey')));
        $ckey = preg_replace('/[^a-z0-9_]+/', '_', $ckey) ?: '';
        if ($ckey === '') {
            flash(t('project_cat.err_ckey', 'Schluessel (ckey) fehlt.'), 'err');
            redirect_route('project_categories');
        }
        if (DB::scalar('SELECT COUNT(*) FROM project_categories WHERE ckey = ?', [$ckey])) {
            flash(t('project_cat.err_ckey_taken', 'Schluessel bereits vergeben.'), 'err');
            redirect_route('project_categories');
        }
        $d['ckey'] = $ckey;
        Repo::saveProjectCategory($d);
        flash(t('project_cat.flash_created', 'Projektart angelegt.'));
    }
    redirect_route('project_categories');
}

function ctrl_project_category_delete(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    if ($id) {
        $inUse = (int) DB::scalar('SELECT COUNT(*) FROM projects WHERE category_id = ?', [$id]);
        if ($inUse > 0) {
            flash(sprintf(t('project_cat.err_in_use', 'Projektart wird noch von %d Projekt(en) verwendet und kann nicht geloescht werden.'), $inUse), 'err');
            redirect_route('project_categories');
        }
        Repo::deleteProjectCategory($id);
        flash(t('project_cat.flash_deleted', 'Projektart geloescht.'));
    }
    redirect_route('project_categories');
}
