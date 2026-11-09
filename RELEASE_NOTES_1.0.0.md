# Timeminator Community v1.0.0

**Release-Datum:** 2026-11-09

Dies ist die erste offizielle Release-Version von Timeminator Community. Seit v0.9.1 sind 153 Commits in den Branch geflossen, mit 43 neuen Features und 67 Bugfixes.

## Highlights

1. **Kalender-Overhaul (Pro-Paritaet).** Der Wochen- und Monatskalender wurde komplett modernisiert: durchgehende Kopfzeile, Click-and-Drag im Stunden-Grid mit Live-Preview, Jetzt-Linie, Tagessoll-Klammer (gruen/gelb/rot), Pensum-Badges pro Tag und ganztaegige Abwesenheits-Pillen mit Gantt-Stacking im Kopfbereich.
2. **Projektart-Hierarchie (Kundenprojekt / Internes Projekt / Foerderprojekt).** Der Entry-Dialog und das Projekt-Formular folgen jetzt einer strikten Kaskade: zuerst Projektart, dann Kunde, dann Projekt, dann Arbeitspaket, dann Aufgabe. Interne Projekte haben keinen Kunden; Foerderprojekte zeigen "Projekttraeger" statt "Kunde".
3. **Rollen- und Rechtesystem.** Sechs vordefinierte Rollen (Admin, Geschaeftsleitung, HR, Projektmanager, Assistenz, User) mit granularen Rechte-Katalog. Nav- und Route-Guards durchgaengig verdrahtet.
4. **Mitarbeiter- und Firmen-Cockpit.** Neue Cockpits fuer Team-Fuehrung mit KPIs, Donut-Charts, Stacked-Area-Diagrammen und absoluter Sentiment-Zeitachse.
5. **Stimmungs-Tracking (Mood plus Flow).** Pro Tag oder pro Woche, mit Team- und Firmen-Aggregaten plus Mitarbeiter-mal-Woche-Matrix.
6. **Ueberstundenabbau zeit-basiert.** Statt Datum plus Stundenangabe laeuft Ueberstundenabbau jetzt wie eine normale Zeitbuchung im Grid, mit eigenem Styling. Pensum und Ueberstundenkonto bleiben sauber getrennt.

## Features im Detail

### Kalender und Zeiterfassung

- Wochen- und Monatsansicht mit sticky Kopfzeile ueber Uhrzeiten (#109, #148)
- Drag im Stunden-Grid mappt Y-Position auf Uhrzeit, mit Live-Preview und 15-Minuten-Snap (#160, #165)
- Rote Jetzt-Linie im Kalender (#138)
- Tagessoll-Klammer rechts der Buchungen plus Ueberstunden-Klammer bei ueberzogenem Pensum (#99)
- Pensum-Badge pro Tag in der Kopfzeile, gruen unter Soll, gelb exakt, rot ueber Soll (#118, #119)
- Pensum-Badges auf einheitlicher Hoehe pro Woche (#173)
- Live-Drag-Preview mit gestricheltem Draft-Block (#165)
- Zwei-Klick-Confirm fuer Loeschen im Dialog (#172)

### Abwesenheiten

- Drag im Wochen-Kopfzeile und in der Monatsansicht fuer ganztaegige Abwesenheiten mit Werktage-Only-Zerlegung (#146, #148)
- Ganztaegige Pillen im Kopfbereich mit Gantt-Stacking bei Ueberlappungen (#162, #173)
- Sonderurlaub als zweite Urlaubsart ohne Urlaubskonto-Effekt (#146)
- Overlap-Guard: zwei Urlaube am gleichen Tag werden abgelehnt (#170)
- Ueberstundenabbau zeit-basiert im Grid statt ganztaegiger Pille (#171, #180, #181)

### Projektart-Hierarchie

- Projektart-Filter als erste Stufe im Entry-Dialog (#149, #150)
- Interne Projekte ohne Kunden-Feld (hidden bei needs_client=0) (#156)
- Foerderprojekte mit Label "Projekttraeger" (dynamisch per data-client-label)
- "Zuerst Projektart waehlen", "Zuerst Kunde waehlen", "Zuerst Projekt waehlen" Lock-Hints (#168)
- Projektart-Hierarchie auch in der Projekt-Neuanlage (#175) und Dashboard-Timer-Card (#182)
- Projektart-Wechsel intern gleich nicht-intern beim Edit gesperrt bis v1.2 (#174)

### Cockpits und Statistik

- Mitarbeiter-Cockpit mit Infoblock, KPIs, Donut-Chart und stackedArea Pro-Paritaet
- Firmen-Cockpit fuer Geschaeftsleitung
- Projekt-Cockpit mit Burn-Up, Soll-Kurve und klickbarer Toggle-Legende
- Sentiment-Chart mit absoluter Zeitachse und Interpolation bei Luecken
- Mitarbeiter-mal-Woche-Smiley-Matrix (Team plus Belegschaft) (#101, #102, #104)
- Team-Stimmung ueber plan_allocations gefiltert plus Mitgliederliste

### Rollen und Rechte

- Sechs Default-Rollen mit Rechte-Katalog (#105, #106, #107, #108)
- Nav- und Route-Guards durchgaengig auf Permission-Keys umgestellt
- HR und GL koennen Vertragsdaten im Mitarbeiter-Cockpit bearbeiten (#98)
- Assistenz der GL bekommt breite operative Sichten

### Sonstige Features

- Dashboard-Timer-Card mit Projektart-Combobox (#182)
- Interne Projekte zeigen em-dash statt Platzhalter-Kunde in Listen (#166, #178, #179)
- Projekt-Farben werden im Kalender respektiert (#169)
- Krankheit-Kategorie-Duplikat entfernt (#183)
- Nav-Dropdowns schliessen automatisch nach Klick (#176)

## Bugfixes

- Grid-Drag-Verlust durch mousemove/mouseup auf document gefixt (#160 Hardening 1-3)
- Deep-Link-Automatik mit ?new=1 eliminiert (Hardening 4-7)
- Alter Grid-Drag-Handler in app.js (Legacy) entfernt (Hardening 8)
- Dialog-Open scrollte Seite nach oben (preventScroll am focus) (#164)
- Edit-Dialog belegte nur Zeit, nicht Projektart/Kunde/Projekt/WP (#167)
- Dialog verschwand sofort nach Oeffnen durch doppelten Legacy-Handler (#164)
- Dashboard-Internes-Projekt-Label bei falscher Kunden-Zuordnung (#178, #179)
- Weitere 60plus kleinere Fixes rund um Form-Validierung, i18n, CSP-strict

## Pro-Issues (geparkt fuer v1.2)

- **#478** Dynamische Projektart-Config (ersetzt Hardcode von kunde/intern/foerderung)
- **#479** Projektart-Wechsel intern gleich nicht-intern mit koordiniertem Kunde-Switch
- **#480** Foerderprojekt-Kunden-Filter (nur Projekttraeger mit Foerderprojekt)
- **#481** Automatischer Ueberstundenabbau bei Unter-Pensum plus Konto-Sync
- **#482** Dashboard-Timer Typ-Switcher Projekt-Zeit gleich Interner Prozess

## Technisches

- 466 PHPUnit-Tests, 3046 Assertions, 1 skipped
- i18n-Audit abgeschlossen (#184): kein hartkodierter UI-Text mehr im Code, alles ueber lang/de.php plus lang/en.php
- CSP-strict: style-src-attr 'none', keine Inline-styles im Markup, nonce-authorized `<style>` plus CSS custom properties
- 50plus neue Regressionstests seit v0.9.1

## Breaking Changes

Keine. Migration auf v1.0.0 ist via Browser-Reload idempotent (siehe schema/migrations/2026-11-09-\*).

## Upgrade

```bash
git pull
# Beim naechsten Browser-Reload laufen die Migrationen automatisch:
# - 2026-11-09-01-sonderurlaub-category
# - 2026-11-09-02-absence-start-end-ts
# - 2026-11-09-03-absence-krankheit-dedup
# - 2026-11-09-04-absence-sick-consolidate
```

## Danke

An Felix fuer die kontinuierlich scharfen UX-Beobachtungen, ohne die diese Release nicht moeglich gewesen waere.
