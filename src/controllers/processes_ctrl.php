<?php
declare(strict_types=1);

/**
 * Interne Prozesse — Zeitbuchung ohne Projektbezug (v1.0 F6, Epic #146,
 * portiert aus Pro #272/#295). Eigene Historie pro Nutzer, kein teamweiter
 * Einblick; reicht `entries.manage` analog zu Zeiteintraegen.
 *
 * Firmenbilanz-Verknuepfung bleibt bewusst Pro-only — Community sieht nur
 * die reinen Verbrauchszahlen pro Kategorie, nicht wie viel das in EUR
 * oder in der GuV ausmacht.
 */

function ctrl_processes_index(): void
{
    require_perm('entries.manage');
    $from = (new DateTimeImmutable('-90 days'))->format('Y-m-d');
    $to   = (new DateTimeImmutable('+365 days'))->format('Y-m-d');
    view('processes/index', [
        'entries'    => ProcessRepo::forUser((int) Auth::id(), $from, $to),
        'categories' => ProcessRepo::categories(true),
    ], t('processes.title', 'Interne Prozesse'));
}

function ctrl_process_save(): void
{
    require_perm('entries.manage');
    csrf_check();
    $id         = post_int('id');
    $categoryId = post_int('category_id');
    $start      = parse_dtlocal((string) post('start_ts'));
    $end        = parse_dtlocal((string) post('end_ts'));
    $note       = trim((string) post('note'));
    if (!$categoryId || !$start || !$end) {
        flash(t('processes.err_fields_required', 'Kategorie, Beginn und Ende sind erforderlich.'), 'err');
        redirect_route('processes');
    }
    if ($id) {
        $existing = ProcessRepo::entry($id);
        if (!$existing || (int) $existing['user_id'] !== Auth::id()) {
            redirect_route('processes');
        }
    }
    try {
        ProcessRepo::save([
            'user_id'     => Auth::id(),
            'category_id' => $categoryId,
            'start_ts'    => $start,
            'end_ts'      => $end,
            'note'        => $note ?: null,
        ], $id ?: null);
        flash(t('processes.flash_saved', 'Interner Prozess gespeichert.'));
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'err');
    }
    redirect_route('processes');
}

function ctrl_process_delete(): void
{
    require_perm('entries.manage');
    csrf_check();
    $id = post_int('id');
    if ($id) {
        $existing = ProcessRepo::entry($id);
        if ($existing && (int) $existing['user_id'] === Auth::id()) {
            ProcessRepo::delete($id);
            flash(t('processes.flash_deleted', 'Interner Prozess geloescht.'));
        }
    }
    redirect_route('processes');
}

// ---------- Prozesskategorien (Admin-Katalog) ----------

function ctrl_process_categories_index(): void
{
    require_perm('structure.manage');
    view('structure/process_categories', [
        'categories' => ProcessRepo::categories(),
    ], t('process_cat.title', 'Prozessarten'));
}

function ctrl_process_category_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id    = post_int('id');
    $label = trim((string) post('label'));
    if ($label === '') {
        flash(t('process_cat.err_label', 'Label fehlt.'), 'err');
        redirect_route('process_categories');
    }
    $d = [
        'label'    => $label,
        'position' => post_int('position', 0),
        'active'   => post('active') ? 1 : 0,
    ];
    if ($id) {
        ProcessRepo::saveCategory($d, $id);
        flash(t('process_cat.flash_updated', 'Prozessart aktualisiert.'));
    } else {
        $ckey = strtolower(trim((string) post('ckey')));
        $ckey = preg_replace('/[^a-z0-9_]+/', '_', $ckey) ?: '';
        if ($ckey === '') {
            flash(t('process_cat.err_ckey', 'Schluessel (ckey) fehlt.'), 'err');
            redirect_route('process_categories');
        }
        if (DB::scalar('SELECT COUNT(*) FROM process_categories WHERE ckey = ?', [$ckey])) {
            flash(t('process_cat.err_ckey_taken', 'Schluessel bereits vergeben.'), 'err');
            redirect_route('process_categories');
        }
        $d['ckey'] = $ckey;
        ProcessRepo::saveCategory($d);
        flash(t('process_cat.flash_created', 'Prozessart angelegt.'));
    }
    redirect_route('process_categories');
}

function ctrl_process_category_delete(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    if ($id) {
        $inUse = (int) DB::scalar('SELECT COUNT(*) FROM process_entries WHERE category_id = ?', [$id]);
        if ($inUse > 0) {
            flash(sprintf(t('process_cat.err_in_use', 'Prozessart wird noch von %d Eintrag/Eintraegen verwendet und kann nicht geloescht werden.'), $inUse), 'err');
            redirect_route('process_categories');
        }
        ProcessRepo::deleteCategory($id);
        flash(t('process_cat.flash_deleted', 'Prozessart geloescht.'));
    }
    redirect_route('process_categories');
}
