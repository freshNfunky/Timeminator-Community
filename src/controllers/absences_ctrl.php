<?php
declare(strict_types=1);

/**
 * Abwesenheiten — Urlaub, Krankheit, Ueberstundenabbau (v1.0 F5, Epic
 * #146, portiert aus Pro #160). Eigene Historie pro Nutzer; reicht
 * `entries.manage` analog zu Zeiteintraegen.
 *
 * Firmenbilanz-/Mitarbeiterkonto-Fortschreibung ist in Community
 * noch nicht automatisiert (kommt mit F1 Mitarbeiter-Cockpit) — hier
 * wird nur die Account-Zuordnung angezeigt. Calendar-Integration
 * (hourly rendering fuer account=overtime) kommt ebenfalls in F1.
 */

function ctrl_absences_index(): void
{
    require_perm('entries.manage');
    $from = (new DateTimeImmutable('-90 days'))->format('Y-m-d');
    $to   = (new DateTimeImmutable('+365 days'))->format('Y-m-d');
    view('absences/index', [
        'absences'   => AbsenceRepo::forUser((int) Auth::id(), $from, $to),
        'categories' => AbsenceRepo::categories(true),
    ], t('absences.title', 'Abwesenheiten'));
}

function ctrl_absence_save(): void
{
    require_perm('entries.manage');
    csrf_check();
    $id         = post_int('id');
    $categoryId = post_int('category_id');
    $start      = trim((string) post('start_date'));
    $end        = trim((string) post('end_date')) ?: $start;
    $note       = trim((string) post('note'));
    if (!$categoryId || $start === '') {
        flash(t('absences.err_fields_required', 'Kategorie und Von-Datum sind erforderlich.'), 'err');
        redirect_route('absences');
    }
    if ($id) {
        $existing = AbsenceRepo::absence($id);
        if (!$existing || (int) $existing['user_id'] !== Auth::id()) {
            redirect_route('absences');
        }
    }
    try {
        AbsenceRepo::save([
            'user_id'     => Auth::id(),
            'category_id' => $categoryId,
            'start_date'  => $start,
            'end_date'    => $end,
            'note'        => $note ?: null,
            'hours'       => AbsenceRepo::parseHours(post('hours'), AbsenceRepo::category($categoryId)),
        ], $id ?: null);
        flash(t('absences.flash_saved', 'Abwesenheit gespeichert.'));
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'err');
    }
    redirect_route('absences');
}

function ctrl_absence_delete(): void
{
    require_perm('entries.manage');
    csrf_check();
    $id = post_int('id');
    if ($id) {
        $existing = AbsenceRepo::absence($id);
        if ($existing && (int) $existing['user_id'] === Auth::id()) {
            AbsenceRepo::delete($id);
            flash(t('absences.flash_deleted', 'Abwesenheit geloescht.'));
        }
    }
    redirect_route('absences');
}

// ---------- Abwesenheitsarten (Admin-Katalog) ----------

function ctrl_absence_categories_index(): void
{
    require_perm('structure.manage');
    view('structure/absence_categories', [
        'categories' => AbsenceRepo::categories(),
    ], t('absence_cat.title', 'Abwesenheitsarten'));
}

function ctrl_absence_category_save(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id    = post_int('id');
    $label = trim((string) post('label'));
    if ($label === '') {
        flash(t('absence_cat.err_label', 'Label fehlt.'), 'err');
        redirect_route('absence_categories');
    }
    $account = (string) post('account');
    $d = [
        'label'    => $label,
        'position' => post_int('position', 0),
        'active'   => post('active') ? 1 : 0,
        'account'  => isset(AbsenceRepo::ACCOUNTS[$account]) ? $account : null,
    ];
    if ($id) {
        AbsenceRepo::saveCategory($d, $id);
        flash(t('absence_cat.flash_updated', 'Abwesenheitsart aktualisiert.'));
    } else {
        $ckey = strtolower(trim((string) post('ckey')));
        $ckey = preg_replace('/[^a-z0-9_]+/', '_', $ckey) ?: '';
        if ($ckey === '') {
            flash(t('absence_cat.err_ckey', 'Schluessel (ckey) fehlt.'), 'err');
            redirect_route('absence_categories');
        }
        if (DB::scalar('SELECT COUNT(*) FROM absence_categories WHERE ckey = ?', [$ckey])) {
            flash(t('absence_cat.err_ckey_taken', 'Schluessel bereits vergeben.'), 'err');
            redirect_route('absence_categories');
        }
        $d['ckey'] = $ckey;
        AbsenceRepo::saveCategory($d);
        flash(t('absence_cat.flash_created', 'Abwesenheitsart angelegt.'));
    }
    redirect_route('absence_categories');
}

function ctrl_absence_category_delete(): void
{
    require_perm('structure.manage');
    csrf_check();
    $id = post_int('id');
    if ($id) {
        $inUse = (int) DB::scalar('SELECT COUNT(*) FROM absences WHERE category_id = ?', [$id]);
        if ($inUse > 0) {
            flash(sprintf(t('absence_cat.err_in_use', 'Abwesenheitsart wird noch von %d Eintrag/Eintraegen verwendet und kann nicht geloescht werden.'), $inUse), 'err');
            redirect_route('absence_categories');
        }
        AbsenceRepo::deleteCategory($id);
        flash(t('absence_cat.flash_deleted', 'Abwesenheitsart geloescht.'));
    }
    redirect_route('absence_categories');
}
