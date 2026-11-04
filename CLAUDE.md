# Timeminator Community — Claude-Arbeitsregeln

Alles was hier steht gilt verbindlich fuer jede Claude-Session in diesem
Repository.

## Regressionstest-Pflicht fuer Features

**Jedes Feature in dieser Anwendung MUSS durch mindestens einen
Regressionstest abgesichert sein.** (Felix-Feedback 2026-11-04: "schreib
gleich mal in claude.md alle features mit regressionstests verifizieren,
damit das nicht passiert, dass etwas wieder rückgängig gemacht wird ohne
aufzufallen.")

Konkret:

1. **Neu gebaut**: jedes neue Feature braucht Regressionstests in
   `tests/` — view-seitig minimal als Datei-/HTML-Smoke-Test (die
   erwarteten DOM-Haken existieren), backend-seitig als Repo- oder
   Controller-Test.
2. **Alt wiederhergestellt**: wenn ein Feature wieder auftauchen soll,
   das frueher einmal existierte, muss der Test, der seine Abwesenheit
   erkannt haette, ebenfalls ergaenzt werden — sonst kippt es beim
   naechsten Refactor erneut.
3. **Was zaehlt als Feature**: alles, was in der UI sichtbar ist (Buttons,
   Charts, Formularfelder, Spalten, Menueeintraege), jeder Controller-
   Pfad, jedes Save/Load-Verhalten, jede Berechnung, jede API-Antwort.
4. **CI-Ausfuehrung**: `vendor/bin/phpunit` muss nach jedem Commit gruen
   sein. Lokal immer mit laufen lassen, nicht nur den eigenen neuen Test.
5. **Regressionstest-Form**: bevorzugt echte Backend-Pfade (Repo-Call +
   DB-Assertion) statt reiner Regex-Suche im Quelltext. Wenn nur Markup
   betroffen ist, reicht eine string-basierte Assertion gegen die View-
   Datei — aber dann so geschrieben, dass sie bei Umbenennung oder Loeschen
   tatsaechlich rot wird.

### Beispiele im Repo

- `tests/BookingOverlapTest.php` sichert den Overlap-Guard in Repo,
  ProcessRepo und Controllern.
- `tests/BurnupViewTest.php` haelt den Vertrag der Burn-Up-Chart-View
  und der Plan-Matrix fest (CSS-Klassen, Attribute, Pflichtfelder).
- `tests/BurnupDataTest.php` sichert die Burn-Up-Payload-Shape.

Neue Features bekommen entsprechend neue Dateien oder erweitern bestehende.

## i18n-Parity

Jeder neue t()-Key MUSS in `lang/de.php` UND `lang/en.php` existieren.
`vendor/bin/phpunit --filter I18nTest` prueft das. Default-Fallback-
Strings im Code sind erlaubt und sinnvoll, aber ersetzen nicht die
offizielle Uebersetzung.

## CSP-strict

Inline-`style="..."`-Attribute werden von `style-src-attr 'none'`
blockiert. Fuer Farben am Markup entweder Inline-SVG mit Praesentations-
Attributen (`fill`, `stroke`) oder eine CSS-Klasse, nicht Inline-Styles.

## Pro-Variante als Vorlage

Wenn eine Komponente in Pro bereits existiert, ist die Pro-Umsetzung die
Vorlage — insbesondere Layout, Farben, Interaktion. Abweichungen nur,
wenn Community-Scope das wirklich erfordert (kaufmaennische Felder,
Mehrbenutzer-only-Features). In allen anderen Faellen Pro-Paritaet
anstreben.
