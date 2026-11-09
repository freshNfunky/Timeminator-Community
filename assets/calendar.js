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
  var taskWrap = document.getElementById('calTaskWrap');
  // Felix-Feedback 2026-11-09 (Fix #149): Dialog nutzt jetzt das
  // task_picker-Partial mit kaskadierender Hierarchie (Projektart ->
  // Kunde -> Projekt -> Arbeitspaket -> Aufgabe). Der Aufgaben-Select
  // ist .tp-task, zusaetzlich gibt es .tp-cat als erste Filterstufe.
  var catFilter  = taskWrap ? taskWrap.querySelector('.tp-cat') : null;
  var taskPicker = taskWrap ? taskWrap.querySelector('.task-picker') : null;
  var clientSel  = taskPicker ? taskPicker.querySelector('.tp-client')  : null;
  var projectSel = taskPicker ? taskPicker.querySelector('.tp-project') : null;
  var wpSel      = taskPicker ? taskPicker.querySelector('.tp-wp')      : null;
  var taskSel    = taskPicker ? taskPicker.querySelector('.tp-task')    : null;
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
    if (taskSel) taskSel.required = (k === 'project');
    startIn.required = endIn.required = (k !== 'absence');
    fromIn.required  = (k === 'absence');
  }
  // Felix-Feedback 2026-11-09 (Fix #149/#150): Projektart-Filter:
  // - filtert Projekt-Optionen nach ckey
  // - bei Internes Projekt (needs_client=0) entfaellt Kunde-Feld komplett;
  //   Projekte werden dann direkt ohne Client-Filter freigeschaltet
  // - bei Foerderprojekt wird das Label "Kunde" durch "Projekttraeger"
  //   (bzw. client_label der Kategorie) ersetzt
  function applyCategoryFilter() {
    if (!catFilter || !projectSel || !taskSel) return;
    var catId = catFilter.value;
    var cats = D.projectCats || {};
    var cfg = catId ? cats[catId] : null;
    // Backwards-compat: in alten Dumps ist der Wert ein einfacher ckey-String.
    if (typeof cfg === 'string') { cfg = {ckey: cfg, needs_client: 1, client_label: ''}; }
    var wantCkey = cfg ? (cfg.ckey || '') : '';
    var needsClient = cfg ? (cfg.needs_client !== 0) : true;
    var clientLabelOverride = cfg ? (cfg.client_label || '') : '';

    // Projekt-Optionen nach ckey filtern.
    Array.prototype.forEach.call(projectSel.options, function (o) {
      if (o.value === '') return;
      var ckey = o.getAttribute('data-cat-ckey') || '';
      var ok = (wantCkey === '' || ckey === wantCkey);
      o.hidden = !ok; o.disabled = !ok;
    });
    var curP = projectSel.selectedOptions[0];
    if (curP && curP.hidden) { projectSel.value = ''; }

    // Kunde-Feld show/hide + Label-Switch.
    var clientLabel = taskPicker ? taskPicker.querySelector('.tp-client-label') : null;
    if (clientLabel) {
      clientLabel.hidden = !needsClient;
      var labelText = clientLabel.querySelector('.tp-client-label-text');
      if (labelText) {
        var baseLabel = clientLabel.getAttribute('data-base-label') || labelText.textContent;
        labelText.textContent = (needsClient && clientLabelOverride) ? clientLabelOverride : baseLabel;
      }
    }
    if (clientSel) {
      if (!needsClient) {
        // Internes Projekt: Kunde leer + aus dem Weg, Projekte duerfen
        // trotzdem gewaehlt werden.
        clientSel.value = '';
        clientSel.disabled = true;
      } else {
        clientSel.disabled = false;
      }
    }
    // Kaskade stossen (task_picker haengt an change).
    if (clientSel)  clientSel.dispatchEvent(new Event('change', {bubbles: true}));
    projectSel.dispatchEvent(new Event('change', {bubbles: true}));
  }
  if (catFilter) catFilter.addEventListener('change', applyCategoryFilter);
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
    if (catFilter)  catFilter.value  = '';
    if (clientSel)  clientSel.value  = '';
    if (projectSel) projectSel.value = '';
    if (wpSel)      wpSel.value      = '';
    if (taskSel)    taskSel.value    = '';
    // Auch tp-new-task-Panel zuruecksetzen (hidden + leer).
    if (taskPicker) {
      var np = taskPicker.querySelector('.tp-new-task');
      if (np) { np.hidden = true; }
      var ni = taskPicker.querySelector('.tp-new-task-name');
      if (ni) { ni.value = ''; ni.required = false; }
    }
    applyCategoryFilter();
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

  // Felix-Feedback 2026-11-09 (#146): Drag im Wochen-Kopf-Header erzeugt
  // eine Date-Range; der Dialog oeffnet mit Typ = erste Urlaub-Kategorie
  // und vorbelegtem Range. User kann im Dialog zwischen Urlaub und
  // Sonderurlaub (oder anderen Abwesenheits-Kategorien) umschalten.
  function openForNewAbsenceRange(fromYmd, toYmd) {
    reset();
    // Default-Vorauswahl: erste Kategorie mit ckey='urlaub' oder account=vacation,
    // sonst erste absence-option.
    var opts = typeSel.querySelectorAll('optgroup[label="' + (D.i18n.group_absences || 'Abwesenheiten') + '"] option');
    var picked = '';
    for (var i = 0; i < opts.length; i++) {
      var ckey = (opts[i].textContent || '').toLowerCase();
      if (ckey.indexOf('urlaub') !== -1 && ckey.indexOf('sonder') === -1) {
        picked = opts[i].value; break;
      }
    }
    if (!picked && opts.length) picked = opts[0].value;
    if (picked) typeSel.value = picked;
    fromIn.value = fromYmd;
    toIn.value   = toYmd || fromYmd;
    setType(typeSel.value);
    openDialog();
  }

  function openForEntry(id) {
    var e = D.entries[String(id)];
    if (!e) return;
    reset();
    typeSel.value = 'project';
    idInput.value = String(e.id);
    // #149: Taskpicker-Hierarchie-Preselect: Task waehlen, Cascader
    // fuellt Parents automatisch.
    if (taskSel) {
      taskSel.value = String(e.task_id || '');
      // task_picker initPicker setzt die Parents aus data-* beim Init;
      // wir triggern einen change, damit auch bei Bestandsentry filterOk.
      taskSel.dispatchEvent(new Event('change', {bubbles: true}));
    }
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

    // Felix-Feedback 2026-11-09 (#146): Band-Pillen oben in der Kopfzeile
    // oeffnen den Edit-Dialog der jeweiligen Absence.
    document.querySelectorAll('.cal-absbar-pill').forEach(function (p) {
      p.addEventListener('click', function () {
        openForAbsence(parseInt(p.getAttribute('data-abs-id'), 10));
      });
    });

    // Drag-Gesture im Wochen-Head: Mousedown auf einem dayhead -> Mousemove
    // tracked eine Range ueber die 7 Dayheads; Mouseup oeffnet den Dialog
    // mit Typ=Urlaub und vorbelegtem Date-Range (from..to via data-day).
    var head = document.querySelector('.cal-week-head');
    if (head) {
      var dragFrom = null;
      var dragTo   = null;
      function headsInOrder() {
        return Array.prototype.slice.call(head.querySelectorAll('.cal-week-dayhead'));
      }
      function dayheadFromEvent(ev) {
        var t = ev.target;
        while (t && t !== head) {
          if (t.classList && t.classList.contains('cal-week-dayhead')) return t;
          t = t.parentNode;
        }
        return null;
      }
      function clearHighlight() {
        headsInOrder().forEach(function (d) { d.classList.remove('is-drag-range'); });
      }
      function highlight(fromYmd, toYmd) {
        var lo = fromYmd < toYmd ? fromYmd : toYmd;
        var hi = fromYmd < toYmd ? toYmd : fromYmd;
        headsInOrder().forEach(function (d) {
          var day = d.getAttribute('data-day') || '';
          d.classList.toggle('is-drag-range', day >= lo && day <= hi);
        });
      }
      head.addEventListener('mousedown', function (ev) {
        var dh = dayheadFromEvent(ev);
        if (!dh) return;
        // Ignorieren, wenn Click auf einem interaktiven Element im Header
        // (Mood-Picker, Pensum-Badge) landet - der soll normal reagieren.
        if (ev.target.closest('.cal-sent-day-head, .cal-day-pensum, .cal-sent-cell')) return;
        dragFrom = dragTo = dh.getAttribute('data-day');
        head.classList.add('is-dragging');
        highlight(dragFrom, dragTo);
        ev.preventDefault();
      });
      head.addEventListener('mousemove', function (ev) {
        if (!dragFrom) return;
        var dh = dayheadFromEvent(ev);
        if (!dh) return;
        dragTo = dh.getAttribute('data-day');
        highlight(dragFrom, dragTo);
      });
      function finishDrag() {
        if (!dragFrom) return;
        var lo = dragFrom < dragTo ? dragFrom : dragTo;
        var hi = dragFrom < dragTo ? dragTo : dragFrom;
        head.classList.remove('is-dragging');
        clearHighlight();
        dragFrom = dragTo = null;
        openForNewAbsenceRange(lo, hi);
      }
      head.addEventListener('mouseup', finishDrag);
      head.addEventListener('mouseleave', function () {
        if (dragFrom) finishDrag();
      });
    }

    // Felix-Feedback 2026-11-09 (#148): Monatsansicht - Drag-Range ueber
    // Kacheln (horizontal in Woche, vertikal ueber mehrere Wochen) oeffnet
    // den Abwesenheits-Dialog mit vorbelegtem Range.
    var mgrid = document.querySelector('.cal-month-grid');
    if (mgrid) {
      var mDragFrom = null;
      var mDragTo   = null;
      function monthCells() {
        return Array.prototype.slice.call(mgrid.querySelectorAll('.cal-month-cell'));
      }
      function monthCellFromEvent(ev) {
        var t = ev.target;
        while (t && t !== mgrid) {
          if (t.classList && t.classList.contains('cal-month-cell')) return t;
          t = t.parentNode;
        }
        return null;
      }
      function mClearHighlight() {
        monthCells().forEach(function (c) { c.classList.remove('is-drag-range'); });
      }
      function mHighlight(fromYmd, toYmd) {
        var lo = fromYmd < toYmd ? fromYmd : toYmd;
        var hi = fromYmd < toYmd ? toYmd : fromYmd;
        monthCells().forEach(function (c) {
          var day = c.getAttribute('data-day') || '';
          c.classList.toggle('is-drag-range', day >= lo && day <= hi);
        });
      }
      mgrid.addEventListener('mousedown', function (ev) {
        var cell = monthCellFromEvent(ev);
        if (!cell) return;
        // Click auf Pill / Mood-Picker / Link darf nicht triggern.
        if (ev.target.closest('.cal-month-abs-pill, .cal-sent-cell, .cal-month-link, .cal-sent-day, .cal-sent-week')) return;
        mDragFrom = mDragTo = cell.getAttribute('data-day');
        mgrid.classList.add('is-dragging');
        mHighlight(mDragFrom, mDragTo);
        ev.preventDefault();
      });
      mgrid.addEventListener('mousemove', function (ev) {
        if (!mDragFrom) return;
        var cell = monthCellFromEvent(ev);
        if (!cell) return;
        mDragTo = cell.getAttribute('data-day');
        mHighlight(mDragFrom, mDragTo);
      });
      function mFinishDrag() {
        if (!mDragFrom) return;
        var lo = mDragFrom < mDragTo ? mDragFrom : mDragTo;
        var hi = mDragFrom < mDragTo ? mDragTo : mDragFrom;
        mgrid.classList.remove('is-dragging');
        mClearHighlight();
        mDragFrom = mDragTo = null;
        openForNewAbsenceRange(lo, hi);
      }
      mgrid.addEventListener('mouseup', mFinishDrag);
      mgrid.addEventListener('mouseleave', function () {
        if (mDragFrom) mFinishDrag();
      });
      document.querySelectorAll('.cal-month-abs-pill').forEach(function (p) {
        p.addEventListener('click', function (ev) {
          ev.stopPropagation();
          openForAbsence(parseInt(p.getAttribute('data-abs-id'), 10));
        });
      });
    }

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
    // Felix-Feedback 2026-11-09 (Fix #142): Deep-Link mit ?new=1 oder
    // ?edit=X oeffnet den Overlay-Dialog sofort beim Seiten-Load, statt
    // den Nutzer auf die Legacy entry_form-Seite zu schicken. Pro-Parity.
    try {
      var params = new URLSearchParams(window.location.search);
      var editId = parseInt(params.get('edit'), 10);
      if (editId && !isNaN(editId)) {
        openForEntry(editId);
      } else if (params.get('new') === '1') {
        var t = new Date();
        var y = t.getFullYear() + '-'
          + String(t.getMonth() + 1).padStart(2, '0') + '-'
          + String(t.getDate()).padStart(2, '0');
        openForNew(y);
      }
      if ((editId && !isNaN(editId)) || params.get('new') === '1') {
        // Clean URL, damit Reload keinen Dialog erneut aufpoppt.
        if (window.history && window.history.replaceState) {
          params.delete('new'); params.delete('edit');
          var qs = params.toString();
          window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
        }
      }
    } catch (e) { /* URLSearchParams nicht supported -> egal */ }
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
      if (!taskSel || !taskSel.value || !startIn.value || !endIn.value) {
        showError('Aufgabe, Start und Ende sind erforderlich.');
        return;
      }
      // #149/#141: "+ Neue Aufgabe"-Fall - name + parent IDs mitgeben.
      var payload = {
        id: id, task_id: taskSel.value,
        start_ts: startIn.value, end_ts: endIn.value, note: note,
      };
      if (taskSel.value === '__new__' && taskPicker) {
        var ni = taskPicker.querySelector('.tp-new-task-name');
        var np = taskPicker.querySelector('.tp-new-task-project');
        var nw = taskPicker.querySelector('.tp-new-task-wp');
        if (!ni || !ni.value.trim()) { showError('Bitte Namen der neuen Aufgabe eingeben.'); return; }
        if (!projectSel || !projectSel.value) { showError('Fuer eine neue Aufgabe muss ein Projekt ausgewaehlt sein.'); return; }
        payload.new_task_name       = ni.value.trim();
        payload.new_task_project_id = (np && np.value) || projectSel.value;
        payload.new_task_wp_id      = (nw && nw.value) || (wpSel ? wpSel.value : '');
      }
      postForm(D.urls.entry_save, payload).then(function (res) {
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
