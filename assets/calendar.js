// v1.0 Kalender-Dialog für Projekt / Interner Prozess / Abwesenheit.
// Konsistent mit Pro. Vanilla JS, CSP-safe (via nonce).
(function () {
  var dataEl = document.getElementById('calDialogData');
  var dlg    = document.getElementById('calDialog');
  if (!dataEl || !dlg) return;
  var D = JSON.parse(dataEl.textContent || '{}');

  var typeSel  = document.getElementById('calType');
  var titleEl  = document.getElementById('calDialogTitle');
  var idInput  = document.getElementById('calEntryId');
  var taskSel  = document.getElementById('calTask');
  var taskWrap = document.getElementById('calTaskWrap');
  var startIn  = document.getElementById('calStart');
  var endIn    = document.getElementById('calEnd');
  var timeRow  = document.getElementById('calTimeRow');
  var fromIn   = document.getElementById('calDateFrom');
  var toIn     = document.getElementById('calDateTo');
  var dateRow  = document.getElementById('calDateRow');
  var hoursIn  = document.getElementById('calHours');
  var hoursWrap= document.getElementById('calHoursWrap');
  var noteIn   = document.getElementById('calNote');
  var errorEl  = document.getElementById('calError');
  var saveBtn  = document.getElementById('calSaveBtn');
  var deleteBtn= document.getElementById('calDeleteBtn');
  var closeBtn = document.getElementById('calCloseBtn');

  function typeKind(v) {
    if (!v) return 'project';
    if (v === 'project') return 'project';
    if (v.indexOf('p:') === 0) return 'process';
    return /^\d+$/.test(v) ? 'absence' : 'project';
  }
  function absenceAccountOf(catId) {
    var opt = typeSel.querySelector('option[value="' + catId + '"]');
    return opt ? (opt.getAttribute('data-account') || '') : '';
  }
  function setType(v) {
    var k = typeKind(v);
    taskWrap.hidden = (k !== 'project');
    timeRow.hidden  = (k === 'absence');
    dateRow.hidden  = (k !== 'absence');
    hoursWrap.hidden = !(k === 'absence' && absenceAccountOf(v) === 'overtime');
    taskSel.required = (k === 'project');
    startIn.required = endIn.required = (k !== 'absence');
    fromIn.required  = (k === 'absence');
  }
  function showError(msg) {
    errorEl.textContent = msg || D.i18n.err_generic;
    errorEl.hidden = false;
  }
  function clearError() { errorEl.hidden = true; errorEl.textContent = ''; }
  function toLocalInput(ts) {
    if (!ts) return '';
    // "2026-10-28 09:00:00" -> "2026-10-28T09:00"
    return String(ts).replace(' ', 'T').slice(0, 16);
  }
  function reset() {
    idInput.value = '';
    typeSel.value = 'project';
    taskSel.value = '';
    startIn.value = endIn.value = '';
    fromIn.value = toIn.value = '';
    hoursIn.value = '';
    noteIn.value = '';
    deleteBtn.hidden = true;
    titleEl.textContent = D.i18n.title_new;
    clearError();
    setType('project');
  }
  function openDialog() {
    if (typeof dlg.showModal === 'function') dlg.showModal();
    else dlg.setAttribute('open', '');
    (taskSel.hidden ? startIn : taskSel).focus();
  }
  function closeDialog() {
    if (typeof dlg.close === 'function') dlg.close();
    else dlg.removeAttribute('open');
  }

  // Open EMPTY dialog from an empty cell click (prefill date 09:00-10:00).
  function openForNew(ymd) {
    reset();
    if (ymd) {
      startIn.value = ymd + 'T09:00';
      endIn.value   = ymd + 'T10:00';
      fromIn.value  = ymd;
      toIn.value    = ymd;
    }
    openDialog();
  }

  function openForEntry(id) {
    var e = D.entries[String(id)];
    if (!e) return;
    reset();
    typeSel.value = 'project';
    idInput.value = String(e.id);
    taskSel.value = String(e.task_id || '');
    startIn.value = toLocalInput(e.start_ts);
    endIn.value   = toLocalInput(e.end_ts);
    noteIn.value  = e.note || '';
    deleteBtn.hidden = false;
    titleEl.textContent = D.i18n.title_edit;
    setType('project');
    openDialog();
  }

  function openForProcess(id) {
    var p = D.processes[String(id)];
    if (!p) return;
    reset();
    typeSel.value = 'p:' + p.category_id;
    idInput.value = String(p.id);
    startIn.value = toLocalInput(p.start_ts);
    endIn.value   = toLocalInput(p.end_ts);
    noteIn.value  = p.note || '';
    deleteBtn.hidden = false;
    titleEl.textContent = D.i18n.title_edit;
    setType(typeSel.value);
    openDialog();
  }

  function openForAbsence(id) {
    var a = (D.absences || []).find(function (x) { return x.id === id; });
    if (!a) return;
    reset();
    typeSel.value = String(a.cat_id);
    idInput.value = String(a.id);
    fromIn.value  = a.start_date;
    toIn.value    = a.end_date;
    hoursIn.value = a.hours == null ? '' : String(a.hours);
    noteIn.value  = a.note || '';
    deleteBtn.hidden = false;
    titleEl.textContent = D.i18n.title_edit;
    setType(typeSel.value);
    openDialog();
  }

  function wireGrid() {
    document.querySelectorAll('.cal-day-grid').forEach(function (grid) {
      grid.addEventListener('click', function (ev) {
        if (ev.target === grid || ev.target.classList.contains('cal-day-line')) {
          openForNew(grid.getAttribute('data-day'));
        }
      });
    });
    document.querySelectorAll('.cal-event').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var t = btn.getAttribute('data-entry-type');
        var id = parseInt(btn.getAttribute('data-entry-id'), 10);
        if (t === 'project') openForEntry(id);
        else if (t === 'process') openForProcess(id);
      });
    });
    document.querySelectorAll('.cal-day-absence').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openForAbsence(parseInt(btn.getAttribute('data-absence-id'), 10));
      });
    });
    // Edit-Buttons in der Liste (alle 3 Typen).
    document.querySelectorAll('.entry-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var kind = btn.getAttribute('data-entry-kind');
        var id = parseInt(btn.getAttribute('data-entry-id'), 10);
        if (kind === 'project') openForEntry(id);
        else if (kind === 'process') openForProcess(id);
        else if (kind === 'absence') openForAbsence(id);
      });
    });
    // "+ Eintrag"-Button im Tab-Row oeffnet leeren Dialog mit heutigem Datum.
    var addBtn = document.getElementById('entryAddBtn');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        var today = new Date();
        var ymd = today.getFullYear() + '-'
          + String(today.getMonth() + 1).padStart(2, '0') + '-'
          + String(today.getDate()).padStart(2, '0');
        openForNew(ymd);
      });
    }
  }

  function postForm(url, data) {
    var body = new URLSearchParams();
    body.set('_csrf', D.csrf);
    Object.keys(data).forEach(function (k) {
      if (data[k] === null || data[k] === undefined) return;
      body.set(k, data[k]);
    });
    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function submit() {
    clearError();
    var k = typeKind(typeSel.value);
    var id = idInput.value;
    var note = noteIn.value;

    if (k === 'project') {
      if (!taskSel.value || !startIn.value || !endIn.value) {
        showError('Aufgabe, Start und Ende sind erforderlich.');
        return;
      }
      postForm(D.urls.entry_save, {
        id: id, task_id: taskSel.value,
        start_ts: startIn.value, end_ts: endIn.value, note: note,
      }).then(function (res) {
        if (!res.ok) { showError(res.error); return; }
        window.location.reload();
      });
    } else if (k === 'process') {
      if (!startIn.value || !endIn.value) {
        showError('Start und Ende sind erforderlich.');
        return;
      }
      postForm(D.urls.process_save, {
        id: id, category_id: typeSel.value.slice(2),
        start_ts: startIn.value, end_ts: endIn.value, note: note,
      }).then(function (res) {
        if (!res.ok) { showError(res.error); return; }
        window.location.reload();
      });
    } else {
      if (!fromIn.value) { showError('Von-Datum ist erforderlich.'); return; }
      postForm(D.urls.absence_save, {
        id: id, category_id: typeSel.value,
        start_date: fromIn.value, end_date: toIn.value || fromIn.value,
        note: note, hours: hoursIn.value || '',
      }).then(function (res) {
        if (!res.ok) { showError(res.error); return; }
        window.location.reload();
      });
    }
  }

  function doDelete() {
    if (!idInput.value) return;
    if (!window.confirm(D.i18n.confirm_delete)) return;
    var k = typeKind(typeSel.value);
    var url = k === 'absence' ? D.urls.absence_delete
            : k === 'process' ? D.urls.process_delete
            : D.urls.entry_delete;
    postForm(url, { id: idInput.value }).then(function (res) {
      if (!res.ok) { showError(res.error); return; }
      window.location.reload();
    });
  }

  typeSel.addEventListener('change', function () { setType(typeSel.value); });
  saveBtn.addEventListener('click', submit);
  deleteBtn.addEventListener('click', doDelete);
  closeBtn.addEventListener('click', closeDialog);
  dlg.addEventListener('cancel', function () { closeDialog(); });

  wireGrid();
})();
