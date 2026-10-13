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
| 2 | **Externe Kalender** | **SOFT** | Community: **max. 1 iCal/CalDAV-Quelle** (für den persönlichen Google-/iCloud-Kalender als Overlay). Pro: unbegrenzt + Team-Kalender. Teaser-Text im Formular: "weitere Quellen → Pro". |
| 3 | **Planung** (Planung + Gantt) | **SOFT** | Community: **nur Soll-Stunden pro Projekt (Gesamtzahl)**, ohne Wochen-/Monats-/Person-Breakdown und ohne Gantt. Pro: alles davon + Gantt. Dashboard-Tile "Projekt-Planung" + einfache Soll-Spalte in der Projekt-Liste. |
| 4 | **Projekt-Report** (Ist vs Plan vs Budget) | **SOFT** | Community: **Ist vs Plan** (sofern Planung gesetzt ist) als simple Tabelle + ein Bar-Chart. **Kein** Budget-Spalte, **kein** Verzögerungs-Report (`delays`), **kein** PDF-Export, **keine** Heatmap. Pro: alles davon. |
| 5 | **Angebote + Bausteine** | **HARD** | Bleibt dimmed Teaser. Kaufmännisches Herzstück von Pro. |
| 6 | **Rechnungen + Billing-Categories** | **HARD** | Bleibt dimmed Teaser. Siehe #5. |
| 7 | **4-stufige Struktur (Modul)** | **SKIP** | Community hat bereits Arbeitspakete als 3. Ebene. Zusätzliche Modul-Ebene würde die Datenbank-Modell-Umbauten rechtfertigen die weder Pro-Käufer noch Community-Nutzer brauchen. Beide Welten können per `code`-Konvention ("MOD-X") in Arbeitspaket-Namen leben. |
| 8 | **Admin → Mail** | **CORE** | Password-Reset (#1) braucht SMTP-Config. Logische Konsequenz. Settings-Page mit `mail_from`, `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, Test-Mail-Button. |
| 9 | **Admin → Cron** | **ADMIN-SKIP** | Pro-SaaS-Feature. Community-Nutzer haben eigenen Cron auf dem Shared Hosting. Doku-Hinweis in Admin → System reicht. |
| 10 | **Admin → Importe (Batches moderieren)** | **CORE** (teils schon da) | Community hat `imports` schon. Nur die Admin-Übersicht aller User-Batches ergänzen (wer hat was wann importiert). Keine Pro-Features. |
| 11 | **Bug-Reports** (in-app) | **SOFT** | Community: **Nutzer können Meldungen senden** (POST zu GitHub-Issues via API oder E-Mail an Maintainer), aber **keine Mod-UI** — das heißt Meldungen gehen an einen externen Issue-Tracker, keine Datenbank-Speicherung. Pro: eigene Mod-UI + Status-Workflow. |
| 12 | **Fine-grained Rollen** (Templates, Audit, Matrix) | **HARD** | Bleibt dimmed Teaser `pro_roles`. Community hat die 2 Basis-Rollen + eigene custom Rollen; die Matrix-Editor-UX bleibt Pro. |
| 13 | **Rate Cards** (Stundensätze) | **HARD** | Hängt direkt an Rechnungen (#6). Bleibt Pro. |
| 14 | **PDF-Export** | **HARD** | Pro-only. Statt dessen: Community hat CSV-Export (schon da). |

## 4. Vorgeschlagenes Implementierungs-Set (MVP für diese Welle)

Reihenfolge nach Impact und Risiko sortiert, ein PR pro Position:

**Phase A — Low-Risk Core (schnelle Wins):**
1. **Password-Reset-Flow** + **Admin → Mail Settings** (CORE #1 + #8) — gehört zusammen, SMTP ist für Reset nötig
2. **Admin → Importe-Übersicht** (CORE #10) — reine Admin-Erweiterung

**Phase B — Soft Upsell (die eigentliche „Nachzieh"-Welle):**
3. **Externe Kalender (1 Quelle)** (SOFT #2) — Infra für Calendar-Sources + Overlay im Week-Kalender; "+ weitere → Pro"
4. **Planung (nur Projekt-Soll-Stunden)** (SOFT #3) — neues Feld `projects.target_hours`, Dashboard-Tile, einfache Projekt-Spalte; "Breakdown + Gantt → Pro"
5. **Projekt-Report (Ist vs Plan)** (SOFT #4) — baut auf Phase B.4; einfache Tabelle + Chart; "Budget + Verzögerungen + PDF + Heatmap → Pro"
6. **Bug-Reports (Submit-only)** (SOFT #11) — kleines Formular im Footer, POST an externen Endpoint; "Mod-UI + Status-Workflow → Pro"

**Nicht in dieser Welle:**
- Angebote, Rechnungen, Rate Cards, PDF-Export, Fine-grained Rollen, Modul-Ebene, Cron — bleiben wie heute

## 5. Was ich dich jetzt entscheiden lassen muss

- [ ] **Ist das Set in Phase A + B der richtige Scope?** (6 PRs, geschätzt 1 pro Tag)
- [ ] **Soll die Phase A** (Password-Reset + Mail + Admin-Imports) **vor der v0.9.0-Deploy-Freigabe** reinkommen, oder als v0.10.0 nach dem Deploy von v0.9.0?
- [ ] **Für SOFT-Features**: ist das Limit-Konzept „max. 1" (bei #2) bzw. „nur Gesamtzahl, kein Breakdown" (bei #3+#4) OK oder willst du andere Hebel? (Ideen: zeitlich befristete Trial, Feature hinter Config-Flag, max. N Projekte)
- [ ] **Bug-Reports (#11)**: externes Target = GitHub Issues (dein Repo) oder E-Mail an eine Admin-Adresse? Bei GitHub: braucht einen Server-side-Token (Secret).
- [ ] **Modul-Ebene (#7 SKIP)**: einverstanden mit Beibehaltung von Community's 3-stufiger Hierarchie, oder willst du auf 4 ausbauen (Breaking Change)?
- [ ] **Admin-Cron (#9 ADMIN-SKIP)**: einverstanden mit Beibehaltung ohne UI, oder soll Community eine schlanke Scheduler-Status-Page bekommen?

## 6. Nächster Schritt

Nach deiner Freigabe pro Zeile öffne ich pro Phase-A-/-B-Position einen Issue
in Community mit konkreter Spec + geschätztem Scope, und arbeite die dann als
PRs ab. v0.9.0 bleibt bis dahin un-deployed.
