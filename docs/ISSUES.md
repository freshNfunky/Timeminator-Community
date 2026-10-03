# Timeminator Community - Umfang

Diese Datei beschreibt den Funktionsumfang der Community Edition und grenzt
ihn gegen Timeminator Pro ab.

## Enthalten (Community Edition, MIT-lizenziert)

- **Login und Rollen-/Rechtesystem.** Zugriff nur mit Anmeldedaten. Backend
  mit zuweisbaren Rollen (admin, user, ...), je Rolle unterschiedliche Rechte,
  dynamisch erweiterbar. Rechte: entries.manage, stats.view,
  structure.manage, scopes.manage, admin.users, admin.roles, admin.system.
- **Zeiterfassung.** Live Start/Stopp Timer sowie manuelle Buchungen, Kunden,
  Projekte und Aufgaben.
- **Statistik.** Stunden je Projekt/Kunde, Zeitverlauf, generischer
  Zwei-Gleise-Vergleich, sowie gespeicherte, vollstaendig generische
  "Nachweis-Sichten" (kein Projekt ist im Code fest hinterlegt).
- **Web-Installer.** Gefuehrter Assistent: Requirements-Check, DB-Wahl
  (MySQL/SQLite), Verbindungstest, Schema einspielen, Rollen/Rechte seeden,
  ersten Admin anlegen, `config.php` schreiben.
- **Update-Funktion.** Prueft ein konfigurierbares Release-Manifest (Default
  GitHub Releases API dieses Repos), meldet dem Admin "Update verfuegbar",
  gefuehrtes Einspielen.
- **Lead-Generator (opt-in Registrierung).** Optionale, standardmaessig
  deaktivierte Registrierung der Installation (nur E-Mail, Domain, Version)
  an einen konfigurierbaren Endpoint. Jederzeit abschaltbar. Das ist der
  einzige "Werbe"-Mechanismus der Community Edition, zusaetzlich zu einem
  kleinen, nicht entfernbaren Hinweis im Footer/Nav auf FelixSchallerCOM und
  Timeminator Pro. Keine Drittanbieter-Werbung, kein Tracking.

## Nicht enthalten (Teil von Timeminator Pro, separates privates Repo)

- **Planung, Budget, Fortschritt, Report.** Soll-Planung (PMV) je Projekt,
  Zeit- und Geldbudget, datierter Fortschritt, Live-Report Ist-vs-Soll-vs-
  Budget-vs-Fortschritt mit Ampel und Wochen-Heatmap. Ersetzt die klassische
  Kette aus Ressourcenplan-Excel, Status-Mail und naechtlich gecrawltem
  PDF-Report.
- **Angebotskalkulation.** Angebots-Editor mit Bausteinen, Live-Summen,
  Typst-Ausgabe.
- **Rechnung und Buchhaltung.** Rechnungs-Editor, "Rechnung aus Angebot",
  Typst-Ausgabe, Buchhaltungs-Konnektoren (lexoffice u.a.).
- **REST-API und Daten-Konnektoren** (Roadmap, geplant fuer Pro): Import aus
  Git-Commits, Kalender, Overleaf u.a.

Der Datenbestand (Kunden, Projekte, Aufgaben, Zeiteintraege) ist in beiden
Editionen dasselbe Schema, sodass ein Wechsel zu Pro ohne Datenverlust
moeglich ist.
