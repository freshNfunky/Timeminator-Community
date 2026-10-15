<?php
/**
 * Abstract list-editor partial (closes #123, v0.9.1 catch-up).
 *
 * Replaces the "statisches Form-Panel neben der Liste"-pattern used
 * everywhere (Clients, Projects, Workpackages, Tasks, Admin-Users,
 * Admin-Roles). The edit form is now expanded INLINE directly below
 * the row the user clicked "bearbeiten" on — so there is always a
 * clear visual link between the row and the form.
 *
 * No JS: when the URL carries `?edit=<id>`, this partial expands the
 * edit form in a second <tr> that spans the full table width,
 * directly after the matching row. The row itself gets `.is-editing`
 * for a subtle highlight.
 *
 * The calling view sets:
 *
 *   $list_items          array    rows to render
 *   $list_headers        array<string>  table header labels (plain strings)
 *   $list_row_renderer   callable($row): string returning the row's
 *                                             <td>…</td> cells (no <tr> wrapper)
 *   $list_form_partial   string   path to a partial that renders the
 *                                  <form>…</form> for a given $edit row
 *   $list_edit           ?array   current $edit row (or null = new)
 *   $list_show_new       bool     render "neuer Eintrag"-form-partial above?
 *   $list_new_route      string   route name for the "neu" button link
 *   $list_empty_msg      string   message when $list_items is empty
 *   $list_edit_id_field  string   PK field on each row (defaults to 'id')
 */

$list_items         = $list_items         ?? [];
$list_headers       = $list_headers       ?? [];
$list_edit          = $list_edit          ?? null;
$list_show_new      = $list_show_new      ?? ($list_edit === null);
$list_edit_id_field = $list_edit_id_field ?? 'id';
$__editId           = $list_edit ? (int) $list_edit[$list_edit_id_field] : 0;
$__cols             = count($list_headers) + 1; // extra col for the edit button
?>

<?php if ($list_show_new): ?>
  <div class="card list-editor-new">
    <?php
      $edit = null; // The form partial reads $edit from scope.
      require $list_form_partial;
    ?>
  </div>
<?php endif; ?>

<div class="card">
  <table class="table list-editor-table">
    <thead>
      <tr>
        <?php foreach ($list_headers as $__h): ?><th><?= h($__h) ?></th><?php endforeach; ?>
        <th class="r"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$list_items): ?>
        <tr><td colspan="<?= $__cols ?>" class="muted"><?= h($list_empty_msg ?? '—') ?></td></tr>
      <?php endif; ?>

      <?php foreach ($list_items as $__row): ?>
        <?php
          $__rowId = (int) $__row[$list_edit_id_field];
          $__isEditing = $__editId === $__rowId;
        ?>
        <tr class="list-editor-row<?= $__isEditing ? ' is-editing' : '' ?>" id="row-<?= $__rowId ?>">
          <?= $list_row_renderer($__row) ?>
          <td class="r">
            <?php if ($__isEditing): ?>
              <a class="btn btn-sm btn-ghost" href="<?= h(route($list_new_route)) ?>#row-<?= $__rowId ?>"><?= h(t('common.cancel', 'abbrechen')) ?></a>
            <?php else: ?>
              <a class="btn btn-sm" href="<?= h(route($list_new_route, ['edit' => $__rowId])) ?>#row-<?= $__rowId ?>"><?= h(t('common.edit_short', 'bearb.')) ?></a>
            <?php endif; ?>
          </td>
        </tr>
        <?php if ($__isEditing): ?>
          <tr class="list-editor-edit-row">
            <td colspan="<?= $__cols ?>">
              <?php
                $edit = $list_edit;
                require $list_form_partial;
              ?>
            </td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
