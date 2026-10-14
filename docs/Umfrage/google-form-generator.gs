/**
 * Timeminator – Nutzer-Umfrage als Google Form (Apps Script Generator)
 * ====================================================================
 * Baut das komplette Feedback-Formular automatisch in deinem Google
 * Workspace (xixum.ai) auf – anonym und für externe LinkedIn-Teilnehmer
 * geöffnet.
 *
 * SO FÜHRST DU ES AUS:
 *  1. Gehe auf https://script.google.com  (mit deinem xixum.ai-Konto)
 *  2. "Neues Projekt" → den gesamten Inhalt dieser Datei einfügen
 *  3. Oben Funktion "buildTimeminatorSurvey" wählen → ▶ Ausführen
 *  4. Beim ersten Mal Berechtigungen bestätigen (dein eigenes Script)
 *  5. Im Ausführungs-Log erscheinen zwei Links:
 *       • "Bearbeiten"  → Formular anpassen
 *       • "Teilen/Live" → genau dieser Link kommt in die Quest-Seite ({{FORM_URL}})
 *
 * Das Formular ist bewusst linear & kompakt gehalten (keine harte
 * Verzweigung), damit es robust läuft und niemand hängenbleibt.
 */
function buildTimeminatorSurvey() {
  var form = FormApp.create('Timeminator – Dein Zeit-Check');

  form.setDescription(
    'Diese Umfrage ist anonym. Angaben zu Person und Unternehmen sind freiwillig und ' +
    'helfen uns nur, Antworten besser einzuordnen. Eine Kontakt-E-Mail gibst du nur an, ' +
    'wenn du Rückmeldung möchtest. Dauer: ca. 3 Minuten.'
  );

  // --- Kritische Einstellungen für anonyme, externe Teilnahme ---
  form.setCollectEmail(false);          // keine E-Mail-Pflicht
  form.setRequireLogin(false);          // KEIN Login / nicht auf xixum.ai beschränkt -> jeder kann teilnehmen
  form.setLimitOneResponsePerUser(false);
  form.setAllowResponseEdits(false);
  form.setProgressBar(true);
  form.setShowLinkToRespondAgain(false);

  // ============ Abschnitt 0 – Wo stehst du gerade? ============
  form.addMultipleChoiceItem()
    .setTitle('Wie würdest du dich beschreiben?')
    .setRequired(true)
    .setChoiceValues([
      'Ich nutze Timeminator Community (self-hosted, kostenlos)',
      'Ich nutze Timeminator Pro',
      'Ich teste es gerade / richte es ein',
      'Ich kenne es, nutze es aber (noch) nicht',
      'Ich bin zum ersten Mal hier'
    ]);

  // ============ Abschnitt 1 – Über dich (freiwillig) ============
  form.addSectionHeaderItem()
    .setTitle('Über dich & dein Unternehmen')
    .setHelpText('Alles freiwillig – lass weg, was du nicht teilen möchtest.');

  form.addMultipleChoiceItem()
    .setTitle('Deine Rolle')
    .showOtherOption(true)
    .setChoiceValues([
      'Freelancer / Solo-Selbstständig', 'Agentur- oder Studio-Inhaber:in',
      'Berater:in / Consultant', 'Team- oder Projektleitung', 'Angestellt im Team',
      'Gründer:in / Startup', 'Forschung & Entwicklung'
    ]);

  form.addTextItem().setTitle('Branche (z. B. IT, Agentur, Beratung, Handwerk …)');

  form.addMultipleChoiceItem()
    .setTitle('Wie viele Menschen arbeiten in deinem Unternehmen?')
    .setChoiceValues(['Nur ich', '2–5', '6–20', '21–50', '51–200', '200+']);

  form.addMultipleChoiceItem()
    .setTitle('Wie viele davon erfassen ihre Zeit?')
    .setChoiceValues(['Nur ich', '2–5', '6–20', '20+', 'Noch niemand systematisch']);

  form.addMultipleChoiceItem()
    .setTitle('Rechtsform')
    .setChoiceValues(['Freiberuflich', 'Einzelunternehmen', 'GbR', 'UG / GmbH', 'AG',
      'Verein / öffentlich', 'Keine Angabe']);

  // ============ Abschnitt 2 – Unternehmerische Herausforderungen ============
  form.addSectionHeaderItem()
    .setTitle('Deine Herausforderungen')
    .setHelpText('Der Kern – darum geht es uns wirklich.');

  form.addMultipleChoiceItem()
    .setTitle('Womit erfasst du heute deine Arbeitszeit?')
    .setHelpText('Timeminator startet die Zeit mit einem Klick – statt sie abends aus dem Gedächtnis zu rekonstruieren.')
    .setRequired(true)
    .showOtherOption(true)
    .setChoiceValues(['Gar nicht / aus dem Kopf', 'Stift & Zettel', 'Excel / Tabelle',
      'SaaS-Tool (Toggl, Clockify, Harvest …)', 'In-house-Lösung', 'Timeminator']);

  var leak = form.addCheckboxItem();
  leak.setTitle('Was kostet dich im Projekt- und Zeitmanagement am meisten Nerven?')
    .setHelpText('Mehrfachauswahl, bitte höchstens 3.')
    .setRequired(true)
    .showOtherOption(true)
    .setChoiceValues([
      'Ich vergesse, die Zeit zu starten/stoppen',
      'Am Monatsende fehlt mir der Überblick, wohin die Stunden flossen',
      'Nachweise für Kunden/Förderung zusammensuchen',
      'Erkennen, ob ein Projekt noch im Budget liegt',
      'Angebote & Rechnungen aus den Stunden bauen',
      'Meine Daten liegen verstreut in fremden Clouds',
      'Das Team zieht bei der Erfassung nicht mit'
    ]);
  leak.setValidation(FormApp.createCheckboxValidation().requireSelectAtMost(3).build());

  form.addScaleItem()
    .setTitle('Wie wichtig ist dir, dass deine Zeitdaten auf deinem eigenen Server liegen?')
    .setHelpText('Timeminator ist self-hosted – deine Daten bleiben auf deiner Maschine, jede Buchung ist nachvollziehbar.')
    .setRequired(true)
    .setBounds(1, 5).setLabels('egal', 'entscheidend');

  form.addParagraphTextItem()
    .setTitle('Was würde dir im Alltag am meisten Zeit zurückgeben?');

  // ============ Abschnitt 3 – Funktionen (Relevanz-Matrix) ============
  form.addSectionHeaderItem().setTitle('Die Funktionen');

  form.addGridItem()
    .setTitle('Wie relevant sind diese Funktionen für dich?')
    .setRequired(true)
    .setRows([
      'Live-Timer & Dashboard (ein Klick startet die Zeit)',
      'Zeiteinträge & farbiger Wochenkalender',
      'Statistik: Stunden je Kunde/Projekt, Verlauf, Vergleich',
      'Nachweis-Sichten (auditierbar für Kunden & Förderung)',
      'Struktur: Kunden → Projekte → Arbeitspakete → Aufgaben',
      'Team-Stimmung (anonym aggregiert)',
      'CSV-Import mit Rollback',
      'Self-Hosting, Rollen & Rechte'
    ])
    .setColumns(['wichtig', 'nice to have', 'brauche ich nicht']);

  form.addParagraphTextItem()
    .setTitle('Welche Funktion fehlt dir am meisten – oder was würdest du zuerst verbessern?');

  // ============ Abschnitt 4 – Pro-Block (optional) ============
  form.addSectionHeaderItem()
    .setTitle('Planung & Report-Cockpit (Timeminator Pro)')
    .setHelpText('Diese Funktionen stecken in Pro. In der Community siehst du die Oberfläche, aber nicht die Daten. Alles hier ist optional.');

  form.addGridItem()
    .setTitle('Wie interessant wären diese Pro-Funktionen für dich? (als Pro-Nutzer: wie zufrieden?)')
    .setRows([
      'Soll-/Budget-Planung + Live-Report (Ampel, Wochen-Heatmap)',
      'Angebote / Kalkulation',
      'Rechnungen + Buchhaltung (lexoffice-Konnektor)',
      'Erweiterte Rollenverwaltung + Audit-Log',
      'REST-API & Konnektoren (Git, Kalender)'
    ])
    .setColumns(['würde ich nutzen', 'interessant', 'brauche ich nicht']);

  form.addMultipleChoiceItem()
    .setTitle('Welche einzelne Funktion würde dich am ehesten zu Pro bewegen?')
    .setChoiceValues([
      'Soll-/Budget-Planung + Live-Report', 'Angebote / Kalkulation',
      'Rechnungen + Buchhaltung', 'Erweiterte Rollenverwaltung', 'REST-API & Konnektoren',
      'Keine davon'
    ]);

  form.addMultipleChoiceItem()
    .setTitle('Was wäre für dich ein fairer Preis für Pro?')
    .setChoiceValues(['< 5 €/Monat', '5–10 €/Monat', '10–20 €/Monat', '20–40 €/Monat',
      'Lieber Einmalkauf', 'Kann ich nicht einschätzen']);

  // ============ Abschnitt 5 – Abschluss ============
  form.addSectionHeaderItem().setTitle('Zum Abschluss');

  form.addScaleItem()
    .setTitle('Wie wahrscheinlich würdest du Timeminator weiterempfehlen – bzw. ausprobieren?')
    .setBounds(0, 10).setLabels('unwahrscheinlich', 'sehr wahrscheinlich');

  form.addParagraphTextItem()
    .setTitle('Ein Satz: Was gefällt dir am besten – oder was hält dich (noch) ab?');

  form.addTextItem()
    .setTitle('Möchtest du Rückmeldung oder Zugang zur Beta? Dann deine E-Mail (optional)')
    .setHelpText('Nur für Rückfragen zu dieser Umfrage und optionale Beta-Einladung. Keine Werbung.');

  // --- Links ausgeben ---
  Logger.log('BEARBEITEN:   ' + form.getEditUrl());
  Logger.log('LIVE / TEILEN: ' + form.getPublishedUrl());
  return form.getPublishedUrl();
}
