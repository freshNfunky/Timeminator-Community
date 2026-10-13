# Pro → Community Feature-Sync — Planungsdokument

**Status:** Vorschlag, noch kein Code. Entscheidung durch @freshNfunky offen.
**Context:** v0.9.0 ist nicht deployed. Vorher soll das Delta an Pro-Features
in Community nachgezogen werden — aber **mit Upsell-Charakter**: strikt
kaufmännische Pro-Features bleiben als Teaser, einige Pro-Features werden
abgespeckt übernommen (soft upsell), ein paar unproblematische Features
wandern komplett rüber (core).

## 1. Delta-Inventar: Was Pro hat, Community (0.9.0) noch nicht

Routes + Permissions in Pro, die in Community fehlen (geprüft gegen
`seed.php::permission_catalog()` auf beiden Seiten):

| Pro-Feature | Pro-Route | Pro-Permission | Community-Status |
|---|---|---|---|
| Password-Reset-Flow | `reset_form`, `reset_request` | (public) | **Nicht vorhanden** |
| Externe Kalender (CalDAV + iCal/Outlook) | `calendar_sources_ctrl` | `calendar.manage` (impliziert) | **Nur Teaser** im Week-Kalender |
| Planung (Soll-Stunden pro Projekt / Woche / Monat / Person) | `planning`, `gantt` | `planning.manage` | **Nicht vorhanden** |
| Projekt-Report (Ist vs Plan vs Budget, Heatmap) | `report`, `delays` | `report.view` | **Teaser** `pro_budget` |
| Angebote + Bausteine | `offers`, `blocks` | `offers.manage` | **Teaser** `pro_offers` |
| Rechnungen + Abrechnungskategorien | `invoices`, `billing_categories` | `invoices.manage` | **Teaser** `pro_invoices` |
| 4-stufige Struktur (Modul zwischen Projekt und Aufgabe) | `modules` | `structure.manage` | Hat **Arbeitspakete** (ähnlich, anders benannt) |
| Admin → Mail (SMTP, Templates) | `admin_mail` | `admin.system` | **Nicht vorhanden** |
| Admin → Cron (Scheduler-Status + Trigger) | `admin_cron` | `admin.system` | **Nicht vorhanden** |
| Admin → Importe (Batches moderieren) | `admin_imports` | `admin.imports` | Hat **`imports`** (etwas anders) |
| Bug-Reports (in-app Nutzer-Meldungen + Mod-UI) | `bug_reports`, `bugs_ctrl` | `bug_reports.moderate` | **Nicht vorhanden** |
| Fine-grained Rollen (Vorlagen, Audit-Log, Matrix) | (Rollen-Page) | `admin.roles` erweitert | **Teaser** `pro_roles` |
| Stundensätze / Rate Cards pro Kunde/Projekt/Rolle | (Settings) | `invoices.manage` | **Nicht vorhanden** |
| PDF-Export (Rechnungen, Angebote) | (Export-Routes) | — | **Nicht vorhanden** |
| Rollen-Feature in Pro: `Sentiment nur aggregiert sehen` | – | `sentiment.view_team` | **Vorhanden** ✓ |

Community-exclusive (bleibt so, nicht nach Pro portieren):
- `banners` Side-Panel + Telemetrie (Finanzierungs-Flow für die MIT-Edition)
- `install.php` Web-Installer mit DB-Picker (Pro hat nur SQL-Setup)
- Opt-in Registrierung + Update-Benachrichtigungen

## 2. Klassifikations-Schema

Jedes Delta-Feature bekommt eine Einstufung:

- **CORE** → voll nach Community portieren (ohne Einschränkung)
- **SOFT** → nach Community portieren mit sichtbarer Einschränkung + Pro-CTA
  ("mehr in Pro"). Das Feature funktioniert, aber ein offensichtliches Limit
  macht die Pro-Variante attraktiver.
- **HARD** → bleibt als dimmed Teaser-Page in Community (wie heute `pro_*`)
- **ADMIN-SKIP** → gar nicht in Community (betrifft nur Pro-SaaS-Infra)

## 3. Vorschlags-Einstufung pro Feature

| # | Feature | Vorschlag | Begründung + geplantes Limit |
|---|---|---|---|
| 1 | **Password-Reset** | **CORE** | Basic Hygiene. Jedes Login-Produkt braucht das. Keinen Pro-Hebel hier. Umsetzung: SMTP via `mail()` oder `admin_mail`-Settings; Token-Tabelle, 1-Stunden-Gültigkeit, Rate-Limit. |
| 2 | **Externe Kalender** | **STATIC** (per Entscheidung 2026-10-13) | Community bekommt **nur Feiertags-Overlay** aus einer eingebauten statischen Liste — zwei Sätze passend zur UI-Sprache: **DACH-Feiertage** (DE/AT/CH) für `de`-Locale, **US-Feiertage** für `en`-Locale. **Keine externen Quellen, kein CalDAV, kein iCal-Import.** Die „CalDAV + iCal-URL"-Teaser im Week-Kalender (schon da) bleiben als Hinweis dass das in Pro geht. |
| 3 | **Planung** (Planung + Gantt) | **SOFT** | Community: **nur Soll-Stunden pro Projekt (Gesamtzahl)**, ohne Wochen-/Monats-/Person-Breakdown und ohne Gantt. Pro: alles davon + Gantt. Dashboard-Tile "Projekt-Planung" + einfache Soll-Spalte in der Projekt-Liste. |
| 4 | **Projekt-Report** (Ist vs Plan vs Budget) | **SOFT** | Community: **Ist vs Plan** (sofern Planung gesetzt ist) als simple Tabelle + ein Bar-Chart. **Kein** Budget-Spalte, **kein** Verzögerungs-Report (`delays`), **kein** PDF-Export, **keine** Heatmap. Pro: alles davon. |
| 5 | **Angebote + Bausteine** | **HARD** | Bleibt dimmed Teaser. Kaufmännisches Herzstück von Pro. |
| 6 | **Rechnungen + Billing-Categories** | **HARD** | Bleibt dimmed Teaser. Siehe #5. |
| 7 | **„Modul-Ebene"** | **SKIP (keine neue Ebene nötig)** — per Entscheidung 2026-10-13 | **Erkenntnis beim Nachsehen im Pro-Code:** Pro hat **keine 4. Ebene**. „Modul" ist in Pro nur das UI-Label für die `work_packages`-Tabelle, die Community als „Arbeitspaket" bezeichnet. Hierarchie identisch: Kunde → Projekt → Modul/Arbeitspaket → Aufgabe. **Einziger Pro-exklusiver Zusatz auf dieser Ebene**: `ctrl_module_import()` befüllt Module aus **Angebotspositionen** — das ist kaufmännisch und bleibt Pro (hängt an #5 Angebote). Fürs Cockpit (#4) heißt das: wir rechnen gegen `work_packages`, exakt wie Pro — keine Breaking Change nötig. |
| 8 | **Admin → Mail** | **CORE** | Password-Reset (#1) braucht SMTP-Config. Logische Konsequenz. Settings-Page mit `mail_from`, `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, Test-Mail-Button. |
| 9 | **Admin → Cron** | **CORE** (per Entscheidung 2026-10-13) | Scheduler-Klasse + Web-Cron-Token-Endpoint sind gerade für Shared Hosting nützlich (viele Hoster erlauben nur HTTP-Cron, kein System-Cron). Portieren inkl. Status-Page in Admin → System: Job-Liste, Last-Run-Timestamp, manueller „Jetzt ausführen"-Button, Token-Regenerate. Keine Pro-Hebel; triggert Housekeeping (z.B. Rollback-Snapshot-Prune, abgelaufene Password-Reset-Tokens). |
| 10 | **Admin → Importe (Batches moderieren)** | **CORE** (teils schon da) | Community hat `imports` schon. Nur die Admin-Übersicht aller User-Batches ergänzen (wer hat was wann importiert). Keine Pro-Features. |
| 11 | **Bug-Reports** (in-app) | **SOFT / deferred to v1.0** (per Entscheidung 2026-10-13) | Target = **GitHub-Issues** im Community-Repo. **Bevorzugter Weg**: via License-Server als Moderations-Puffer — Community-App POSTet Meldung an License-Server-Endpoint, der Betreiber moderiert, die GitHub-API-Weiterleitung läuft dann unter dem Maintainer-Token. Direkt-POST zu GitHub ist nicht praktikabel (keine anonyme Issue-Erstellung, Token-Verteilung in MIT-Builds = Secret-Leak). **Nicht in dieser Welle** — als v1.0-Feature in [#105](https://github.com/freshNfunky/Timeminator-Community/issues/105) spezifiziert. Für jetzt: Teaser-Hinweis im Footer „Bug? → GitHub-Repo", Link auf das Issues-Tab. |
| 12 | **Fine-grained Rollen** (Templates, Audit, Matrix) | **HARD** | Bleibt dimmed Teaser `pro_roles`. Community hat die 2 Basis-Rollen + eigene custom Rollen; die Matrix-Editor-UX bleibt Pro. |
| 13 | **Rate Cards** (Stundensätze) | **HARD** | Hängt direkt an Rechnungen (#6). Bleibt Pro. |
| 14 | **PDF-Export** | **HARD** | Pro-only. Statt dessen: Community hat CSV-Export (schon da). |

## 4. Vorgeschlagenes Implementierungs-Set (MVP für diese Welle)

Reihenfolge nach Impact und Risiko sortiert, ein PR pro Position:

**Phase A — Low-Risk Core (schnelle Wins):**
1. **Password-Reset-Flow** + **Admin → Mail Settings** (CORE #1 + #8) — gehört zusammen, SMTP ist für Reset nötig
2. **Admin → Importe-Übersicht** (CORE #10) — reine Admin-Erweiterung
3. **Admin → Cron / Scheduler** (CORE #9) — Scheduler-Klasse portieren, Status-Page + Web-Cron-Token-Endpoint; triggert Housekeeping (abgelaufene Reset-Tokens, Rollback-Snapshot-Prune)

**Phase B — Soft Upsell (die eigentliche „Nachzieh"-Welle):**
4. **Feiertags-Overlay** (STATIC #2) — statische DACH-Liste (für `de`) + US-Liste (für `en`), als dezentes Overlay im Week-Kalender; **keine externen Quellen**, CalDAV bleibt Pro
5. **Planung (nur Projekt-Soll-Stunden)** (SOFT #3) — neues Feld `projects.target_hours`, Dashboard-Tile, einfache Projekt-Spalte; "Breakdown + Gantt → Pro"
6. **Projekt-Report (Ist vs Plan)** (SOFT #4) — baut auf Phase B.5; einfache Tabelle + Chart; "Budget + Verzögerungen + PDF + Heatmap → Pro"
7. ~~**Bug-Reports (Submit-only)**~~ — nach v1.0 verschoben, siehe [#105](https://github.com/freshNfunky/Timeminator-Community/issues/105). Für diese Welle: nur Teaser-Link im Footer „Bug? → GitHub Issues".

**Nicht in dieser Welle:**
- Angebote, Rechnungen, Rate Cards, PDF-Export, Fine-grained Rollen — bleiben wie heute Teaser
- „Modul-Ebene" ist keine echte Zusatzebene (siehe #7 oben), kein eigener PR nötig

## 5. Entscheidungen

Historie:

- [x] **SOFT-Hebel** (max. 1 bei #2 war der Ursprung, inzwischen durch STATIC ersetzt;
  „nur Gesamtzahl, kein Breakdown" bei #3+#4): **Entschieden 2026-10-13** — Aufteilung passt.
- [x] **Externe Kalender (#2)**: **Entschieden 2026-10-13** — **keine externen Quellen in
  Community**, nur statisches Feiertags-Overlay (DACH für `de`, US für `en`). CalDAV
  bleibt vollständig Pro-exklusiv. (Reklassifiziert von SOFT → STATIC in Abschnitt 3.)
- [x] **Bug-Reports (#11)**: **Entschieden 2026-10-13** — Ziel = GitHub-Issues im
  Community-Repo, mit License-Server als Moderations-Puffer. **Nicht in dieser
  Welle**, auf v1.0 vertagt — Spec in [#105](https://github.com/freshNfunky/Timeminator-Community/issues/105).
  In der jetzigen Welle nur ein Teaser-Link im Footer („Bug? → GitHub Issues").

- [x] **Release-Taktung**: **Entschieden 2026-10-13** — die komplette Nachzieh-Welle
  (Phase A + B) landet unter dem **v0.9.0-Label**. Kein v0.10.0, kein
  Zwischen-Deploy, keine krummen Versionen. Beim Release-Finale wird der
  bereits gesetzte v0.9.0-Tag force-gepusht (`git tag -f v0.9.0 && git push
  --force origin v0.9.0`); diese Features waren ursprünglich für 0.9.0
  geplant, der frühere Tag war verfrüht.
- [x] **Modul-Ebene (#7)**: **Entschieden 2026-10-13** — SKIP bestätigt, aber mit
  korrigierter Begründung: beim Review des Pro-Codes stellt sich raus, dass
  Pro **keine 4. Ebene** hat. „Modul" ist dort nur das UI-Label für die
  `work_packages`-Tabelle, die Community als „Arbeitspaket" bezeichnet. Die
  Hierarchie ist identisch (Kunde → Projekt → Modul/AP → Aufgabe). Fürs
  Cockpit (#4) ist das ideal: wir rechnen gegen `work_packages` wie Pro, kein
  Breaking Change. Der einzige Pro-exklusive Zusatz hier (`module_import` aus
  Angebotspositionen) bleibt Pro, weil er an Angebote hängt.
- [x] **Admin-Cron (#9)**: **Entschieden 2026-10-13** — CORE statt SKIP. Scheduler
  + Web-Cron-Token-Endpoint sind gerade auf Shared Hosting nützlich. Phase A
  bekommt dafür einen eigenen PR (jetzt A.3). Reklassifiziert in Abschnitt 3.

## 6. Nächster Schritt

**Alle Entscheidungen sind damit gelockt.** Phase A + B umfassen jetzt
**6 PRs** (A: 3, B: 3), #11 Bug-Reports bleibt für v1.0.

Als Nächstes öffne ich pro PR-Position einen Issue in Community mit
konkreter Spec + Scope-Schätzung, und arbeite die dann sequenziell als PRs
ab. **v0.9.0 bleibt bis zum Abschluss aller 6 PRs un-deployed**; am Ende
wird der v0.9.0-Tag force-gepusht und die komplette Welle als ein
v0.9.0-Deploy auf die Nutzer-Instanz freigegeben.
