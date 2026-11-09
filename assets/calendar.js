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
  // Felix-Feedback 2026-11-09 (Fix #149/#150/#157): Projektart-Filter.
  // #157: Logik wird HARDCODED nach ckey. Die 3 Default-Kategorien tragen
  // feste Semantik; alle anderen (Admin-eigene Projektarten) werden wie
  // "kunde" behandelt (Kunde sichtbar, Label "Kunde"). Die DB-Felder
  // needs_client / client_label werden hier bewusst ignoriert, bis fuer
  // M1.4 eine dynamische Config-Logik implementiert ist (siehe Pro-Issue).
  var HARDCODED_CAT_CONFIG = {
    'kunde':      {needsClient: true,  clientLabel: ''},
    'intern':     {needsClient: false, clientLabel: ''},
    'foerderung': {needsClient: true,  clientLabel: 'Projekttraeger'}
  };
  function catConfig(ckey) {
    return HARDCODED_CAT_CONFIG[ckey] || HARDCODED_CAT_CONFIG['kunde'];
  }
  function applyCategoryFilter() {
    if (!catFilter || !projectSel || !taskSel) return;
    var catId = catFilter.value;
    var cats = D.projectCats || {};
    var catEntry = catId ? cats[catId] : null;
    // Backwards-compat: in alten Dumps ist der Wert ein einfacher ckey-String.
    if (typeof catEntry === 'string') { catEntry = {ckey: catEntry}; }
    var wantCkey = catEntry ? (catEntry.ckey || '') : '';
    // #157: Logik kommt NICHT aus den DB-Feldern, sondern aus der
    // Hardcode-Config. Fuer unbekannte ckeys (Admin-eigene Kategorien)
    // greift die Default-Logik "wie kunde".
    var cfg = catEntry ? catConfig(wantCkey) : {needsClient: true, clientLabel: ''};
    var needsClient = cfg.needsClient;
    var clientLabelOverride = cfg.clientLabel;

    // #158: Projektart-Filter wird am Picker-Root als data-cat-filter
    // persistiert, damit applyFilters() in app.js den Filter beim
    // Kaskaden-Rebuild mit beruecksichtigt. Sonst wuerde applyFilters
    // alle Projekte wieder sichtbar machen (ueberschreibt o.hidden).
    if (taskPicker) {
      if (wantCkey) {
        taskPicker.setAttribute('data-cat-filter', wantCkey);
      } else {
        taskPicker.removeAttribute('data-cat-filter');
      }
    }
    var curP = projectSel.selectedOptions[0];

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
    try {
      if (typeof dlg.showModal === 'function') {
        dlg.showModal();
      } else {
        dlg.setAttribute('open', '');
      }
    } catch (ex) {
      dlg.setAttribute('open', '');
    }
    var target = taskSel && !taskSel.hidden ? taskSel : startIn;
    if (target && typeof target.focus === 'function') {
      // preventScroll: sonst springt Firefox beim focus() auf ein weit
      // oben stehendes Form-Element (startIn, taskSel) an den Seitenanfang.
      target.focus({preventScroll: true});
    }
  }
  function closeDialog() {
    if (typeof dlg.close === 'function') dlg.close();
    else dlg.removeAttribute('open');
  }

  // Open EMPTY dialog from an empty cell click (prefill date 09:00-10:00).
  // Felix-Feedback 2026-11-09 (Fix #160): Click/Drag im Zeit-Grid soll
  // die tatsaechlich gewaehlten Minuten uebergeben. startMin/endMin sind
  // optional - fallen auf 09:00-10:00 zurueck, wenn beide null (Dashboard-
  // Pfad, neuer Entry ohne Kalender-Kontext).
  function openForNew(ymd, startMin, endMin) {
    reset();
    if (ymd) {
      var sMin = (typeof startMin === 'number' && startMin >= 0) ? startMin : 9 * 60;
      var eMin = (typeof endMin   === 'number' && endMin   > sMin) ? endMin  : sMin + 60;
      if (eMin > 24 * 60) { eMin = 24 * 60; }
      startIn.value = ymd + 'T' + pad2(Math.floor(sMin / 60)) + ':' + pad2(sMin % 60);
      endIn.value   = ymd + 'T' + pad2(Math.floor(eMin / 60)) + ':' + pad2(eMin % 60);
      fromIn.value  = ymd;
      toIn.value    = ymd;
    }
    openDialog();
  }
  function pad2(n) { return n < 10 ? '0' + n : '' + n; }

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
    // Felix-Feedback 2026-11-09 (Fix #160): Click/Drag im Stunden-Grid
    // soll die tatsaechlich geklickte Uhrzeit uebergeben (vorher: immer
    // 09:00-10:00).
    //
    // Felix-Feedback 2026-11-09 (Hardening 3, auch Firefox): mouseup+
    // mousemove gehen bei realem User-Drag oft NICHT ans Grid, wenn die
    // Maus das Grid kurz verlaesst (Browser setzt Capture nur mit
    // explizitem setPointerCapture). Deshalb: mousemove+mouseup leben
    // auf DOCUMENT, nicht auf dem jeweiligen Grid. Der Grid-Handler
    // weiss nur ueber den mousedown Bescheid, der Rest ist global.
    var gDragGrid  = null;
    var gDragStart = null;
    var gDragEnd   = null;
    function minuteFromEventOnGrid(ev, grid) {
      var rect = grid.getBoundingClientRect();
      var y = ev.clientY - rect.top;
      if (y < 0) y = 0;
      if (y > rect.height) y = rect.height;
      var raw = (y / rect.height) * 24 * 60;
      return Math.round(raw / 15) * 15;
    }
    document.querySelectorAll('.cal-day-grid').forEach(function (grid) {
      grid.addEventListener('mousedown', function (ev) {
        if (ev.button !== 0) return;
        if (ev.target.closest('.cal-event, .cal-process, .cal-day-absence, .cal-absbar-pill, .cal-day-bracket')) return;
        gDragGrid  = grid;
        gDragStart = minuteFromEventOnGrid(ev, grid);
        gDragEnd   = gDragStart;
        ev.preventDefault();
      });
    });
    document.addEventListener('mousemove', function (ev) {
      if (!gDragGrid || gDragStart === null) return;
      gDragEnd = minuteFromEventOnGrid(ev, gDragGrid);
    });
    document.addEventListener('mouseup', function (ev) {
      if (!gDragGrid || gDragStart === null) return;
      gDragEnd = minuteFromEventOnGrid(ev, gDragGrid);
      var lo = Math.min(gDragStart, gDragEnd);
      var hi = Math.max(gDragStart, gDragEnd);
      var day = gDragGrid.getAttribute('data-day');
      gDragGrid = null; gDragStart = gDragEnd = null;
      if (hi - lo < 15) { hi = lo + 60; }
      openForNew(day, lo, hi);
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
      var mDragMoved = false;
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
      // Felix-Feedback 2026-11-09 (Fix #154): vorher scheiterte Drag im
      // Monat an .cal-month-link, das die ganze Zelle ueberspannt - der
      // ev.target.closest('.cal-month-link')-Guard verhinderte Drag-Start.
      // Jetzt: Drag startet IMMER (preventDefault haelt die Link-Navigation
      // zurueck). Beim mouseup unterscheiden wir:
      //   - mDragMoved=false (same cell): normaler Link-Click, Navigation
      //     zur Wochenansicht wird manuell ausgeloest.
      //   - mDragMoved=true: openForNewAbsenceRange mit vorbelegtem Range.
      mgrid.addEventListener('mousedown', function (ev) {
        if (ev.button !== 0) return; // nur linke Maustaste
        var cell = monthCellFromEvent(ev);
        if (!cell) return;
        // Pillen + Mood-Picker triggern keinen Drag (eigener Edit-Flow).
        if (ev.target.closest('.cal-month-abs-pill, .cal-sent-cell, .cal-sent-day, .cal-sent-week')) return;
        mDragFrom = mDragTo = cell.getAttribute('data-day');
        mDragMoved = false;
        mgrid.classList.add('is-dragging');
        mHighlight(mDragFrom, mDragTo);
        ev.preventDefault(); // Drag-Image unterdruecken
      });
      mgrid.addEventListener('mousemove', function (ev) {
        if (!mDragFrom) return;
        var cell = monthCellFromEvent(ev);
        if (!cell) return;
        var newDay = cell.getAttribute('data-day');
        if (newDay && newDay !== mDragTo) {
          mDragMoved = true;
          mDragTo = newDay;
          mHighlight(mDragFrom, mDragTo);
        }
      });
      // Click-Fallback: Link innerhalb cal-month-cell wuerde beim mousedown
      // schon navigieren. preventDefault im mousedown verhindert den
      // Default; der tatsaechliche click-Event wird hier abgefangen und
      // bei NICHT-Drag zur Wochenansicht geleitet.
      mgrid.addEventListener('click', function (ev) {
        var link = ev.target.closest('.cal-month-link');
        if (link && mDragMoved) {
          ev.preventDefault();
          ev.stopPropagation();
        }
      }, true);
      function mFinishDrag(ev) {
        if (!mDragFrom) return;
        var moved = mDragMoved;
        var lo = mDragFrom < mDragTo ? mDragFrom : mDragTo;
        var hi = mDragFrom < mDragTo ? mDragTo : mDragFrom;
        var startDay = mDragFrom;
        mgrid.classList.remove('is-dragging');
        mClearHighlight();
        mDragFrom = mDragTo = null;
        mDragMoved = false;
        if (moved) {
          openForNewAbsenceRange(lo, hi);
        } else {
          // Single-Click ohne Drag: Link-Navigation zur Wochenansicht.
          var cell = mgrid.querySelector('.cal-month-cell[data-day="' + startDay + '"]');
          var link = cell ? cell.querySelector('.cal-month-link') : null;
          if (link && link.href) { window.location = link.href; }
        }
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
    // "+ Eintrag"-Button im Tab-Row oeffnet leeren Dialog mit heutigem
    // Datum und AKTUELLER Uhrzeit (gerundet auf 15 Min) statt hardcoded
    // 09:00-10:00 - weniger Klicks, wenn Felix "jetzt" buchen will.
    var addBtn = document.getElementById('entryAddBtn');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        var now = new Date();
        var ymd = now.getFullYear() + '-'
          + String(now.getMonth() + 1).padStart(2, '0') + '-'
          + String(now.getDate()).padStart(2, '0');
        var startMin = now.getHours() * 60 + now.getMinutes();
        startMin = Math.floor(startMin / 15) * 15;
        openForNew(ymd, startMin, startMin + 60);
      });
    }
    // Felix-Feedback 2026-11-09 (Fix #142): Deep-Link mit ?new=1 oder
    // ?edit=X oeffnet den Overlay-Dialog sofort beim Seiten-Load, statt
    // den Nutzer auf die Legacy entry_form-Seite zu schicken. Pro-Parity.
    // Felix-Feedback 2026-11-09 Hardening 5: Deep-Link ?new=1 / ?edit=X
    // zwingend AUS DER URL entfernen - sonst feuert jeder spaetere
    // window.location.reload() (Save-Flow) den Deep-Link erneut und
    // ueberschreibt den gerade gewaehlten Grid-Drag mit heute/09-10.
    // Hardening 4 (history.replaceState) hat laut Felix nicht gegriffen.
    // Hardening 5: sessionStorage-Flag + location.replace() mit sauberer
    // URL. Nach dem Redirect ist ?new=1 definitiv raus und Dialog oeffnet
    // trotzdem einmal via Flag. Save -> reload -> Flag leer -> kein Dialog.
    // Felix-Feedback 2026-11-09 Hardening 7: komplett deklarativ.
    // Nach Hardening 6 (keine Quelle mehr fuer ?new=1) ist auch der
    // sessionStorage-Flag-Trick obsolet und hat selbst Flash/kein-Dialog
    // produziert. Ich loesche beim Load JEDE pending-Flag als
    // Sicherheits-Reset und mache nur noch das Minimum: wenn ?edit=X in
    // URL, Edit-Dialog oeffnen + URL-cleanup. Sonst: nichts tun. Dialog
    // kommt dann ausschliesslich ueber Grid-Drag oder +Eintrag-Button.
    try {
      window.sessionStorage.removeItem('tm-cal-pending-new');
      window.sessionStorage.removeItem('tm-cal-pending-edit');
    } catch (e) {}
    try {
      var params = new URLSearchParams(window.location.search);
      var editId = parseInt(params.get('edit'), 10);
      var hasNew = params.get('new') === '1';

      // ?new=1 wird KOMPLETT ignoriert (kein Dialog mehr erzwingen).
      // Edit-Deep-Link weiterhin unterstuetzt - aber URL dabei cleanen.
      if (hasNew && window.history && window.history.replaceState) {
        params.delete('new');
        var qs = params.toString();
        window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
      }
      if (editId && !isNaN(editId)) {
        if (window.history && window.history.replaceState) {
          params.delete('edit');
          var qs2 = params.toString();
          window.history.replaceState(null, '', window.location.pathname + (qs2 ? '?' + qs2 : ''));
        }
        openForEntry(editId);
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
