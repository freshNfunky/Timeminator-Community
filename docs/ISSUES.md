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
  an einen konfigurierbaren Endpoint. Jederzeit abschaltbar. Zusaetzlich ein
  kleiner, nicht entfernbarer Hinweis im Footer/Nav auf FelixSchallerCOM und
  Timeminator Pro.
- **Community-Banner-Karussell + Telemetrie.** Schlanke Banner-Spalte rechts im
  Layout, die durch Angebote, Tools und News von FelixSchallerCOM rotiert. Die
  Motive werden serverseitig von einer konfigurierbaren Subdomain geladen; bei
  fehlendem Netz greift der mitgelieferte Standard-Satz
  (`assets/banners/default.json`). So finanziert sich die kostenlose Community
  Edition. Mit jedem Abruf wird ein anonymisiertes Nutzungssignal gesendet
  (App-Version, zufaellige Installations-ID, Hash davon, gekuerzte IP) -
  niemals Zeiterfassungsdaten. Pro Feld, per Admin-Schalter und per
  `config.php` vollstaendig abschaltbar; in Pro komplett entfernt. Keine
  Drittanbieter-Werbenetzwerke. Details: `docs/BANNERS.md`.

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
