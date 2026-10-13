# Timeminator – Nutzer-Umfrage & Attention-Quest

Stand: 2026-10-13 · passend zu Timeminator Community v0.9.0

Zwei Bausteine, die zusammenspielen:

1. **Quest-Seite (Artifact)** – das öffentliche, interaktive Attention-Vehikel für LinkedIn & Kaltakquise. Jeder kann sie anonym ansehen und die Mini-Challenges direkt im Browser ausprobieren. Vermarktet das Produkt subtil über die Fragetexte, verkauft aber nichts. Endet mit einem klaren CTA auf dieses Feedback-Formular und auf die Live-Demo.
2. **Feedback-Formular (Tally / Google Forms)** – sammelt das eigentliche Kunden-Feedback zuverlässig und anonym ein. Der Fragenkatalog dafür steht unten, 1:1 zum Einpflegen.

> **Zwei Platzhalter, die DU setzen musst:**
> - `{{DEMO_URL}}` → Link zu deiner öffentlichen Demo oder Landingpage (z. B. `https://timeminator.felixschaller.com`).
> - `{{FORM_URL}}` → Link zum fertigen Tally-/Google-Forms-Formular.
>
> Beide sind in der Quest-Seite als klar markierte Buttons hinterlegt und müssen vor dem Teilen gesetzt werden.

---

## Leitlinien für die Fragetexte

- **Nutzen zuerst, Feature zweitens.** Jede Frage ist aus Sicht des Nutzers formuliert und transportiert nebenbei, was Timeminator kann.
- **Kein Verkaufsdruck.** Keine Rabatte, keine „Jetzt kaufen". Der Gewinn ist: die Person denkt über ihr Zeit-/Projektproblem nach – und merkt, dass es dafür ein Werkzeug gibt.
- **Niedrigschwellig.** Pflichtfelder auf ein Minimum. Alles zu Person & Unternehmen ist **freiwillig** und mit einem kurzen Datenschutzhinweis versehen.
- **Funktioniert auch ohne Vorwissen.** Jede Feature-Frage hat eine Ein-Satz-Erklärung, damit auch Erstkontakte antworten können.

---

## Datenschutz-Hinweis (an den Anfang des Formulars)

> Diese Umfrage ist anonym. Angaben zu Person und Unternehmen sind **freiwillig** und helfen uns nur, Antworten besser einzuordnen. Keine Pflichtfelder außer dort, wo markiert. Eine Kontakt-E-Mail gibst du nur an, wenn du Rückmeldung möchtest. Dauer: ca. 3 Minuten.

---

## Abschnitt 0 – Wo stehst du gerade? *(steuert die Verzweigung)*

**F0. Wie würdest du dich beschreiben?** *(Pflicht, Einfachauswahl)*
- Ich nutze Timeminator **Community** (self-hosted, kostenlos)
- Ich nutze Timeminator **Pro**
- Ich **teste** es gerade / richte es ein
- Ich kenne es, nutze es aber **(noch) nicht**
- Ich bin **zum ersten Mal** hier

> Verzweigung im Formular:
> - **Community / Pro / Teste** → Abschnitt 3 fragt zusätzlich nach Zufriedenheit („Wie gut gelöst?").
> - **Pro** → Abschnitt 4 (Pro-Block) fragt nach Zufriedenheit statt nach Interesse.
> - **Kenne/nutze nicht & Erstkontakt** → Abschnitt 3 & 4 fragen nur nach Relevanz/Interesse. Abschnitt 5 fragt „Würdest du es ausprobieren?" statt Weiterempfehlung.

---

## Abschnitt 1 – Über dich & dein Unternehmen *(komplett freiwillig)*

**F1. Deine Rolle** *(optional, Einfachauswahl)*
Freelancer / Solo-Selbstständig · Agentur- oder Studio-Inhaber:in · Berater:in / Consultant · Team- oder Projektleitung · Angestellt im Team · Gründer:in / Startup · Forschung & Entwicklung · Sonstiges

**F2. Branche** *(optional, Freitext oder Auswahl)*
z. B. IT/Software · Agentur/Kreativ · Ingenieurwesen · Beratung · Handwerk · Wissenschaft · Sonstiges

**F3. Wie viele Menschen arbeiten in deinem Unternehmen?** *(optional, Einfachauswahl)*
Nur ich · 2–5 · 6–20 · 21–50 · 51–200 · 200+

**F4. Wie viele davon erfassen ihre Zeit?** *(optional, Einfachauswahl)*
Nur ich · 2–5 · 6–20 · 20+ · Noch niemand systematisch

**F5. Rechtsform** *(optional, Einfachauswahl)*
Freiberuflich · Einzelunternehmen · GbR · UG / GmbH · AG · Verein / öffentlich · Sonstiges · Keine Angabe

---

## Abschnitt 2 – Deine unternehmerischen Herausforderungen *(der Kern – gilt für alle)*

**F6. Womit erfasst du heute deine Arbeitszeit?** *(Pflicht, Einfachauswahl)*
Gar nicht / aus dem Kopf · Stift & Zettel · Excel / Tabelle · SaaS-Tool (Toggl, Clockify, Harvest …) · In-house-Lösung · Timeminator · Sonstiges

> *Subtiler Pitch im Hilfetext:* „Timeminator startet die Zeit mit einem Klick – statt sie abends aus dem Gedächtnis zu rekonstruieren."

**F7. Was kostet dich im Projekt- und Zeitmanagement am meisten Nerven?** *(Pflicht, Mehrfachauswahl, max. 3)*
- Ich vergesse, die Zeit zu starten/stoppen
- Am Monatsende fehlt mir der Überblick, wohin die Stunden geflossen sind
- Nachweise für Kunden/Förderung zusammenzusuchen
- Zu erkennen, ob ein Projekt noch im Budget liegt
- Angebote & Rechnungen aus den Stunden zu bauen
- Meine Daten liegen verstreut in fremden Clouds
- Das Team zieht bei der Erfassung nicht mit
- Sonstiges: ________

**F8. Wie wichtig ist dir, dass deine Zeitdaten auf deinem eigenen Server liegen?** *(Pflicht, Skala 1–5)*
1 = egal · 5 = entscheidend

> *Subtiler Pitch:* „Timeminator ist self-hosted – deine Daten bleiben auf deiner Maschine, jede Buchung ist nachvollziehbar und revisionssicher."

**F9. Was würde dir im Alltag am meisten Zeit zurückgeben?** *(optional, Freitext)*

---

## Abschnitt 3 – Die Funktionen *(kompakt – Relevanz für alle, Zufriedenheit nur für Nutzer)*

**F10. Wie relevant sind diese Funktionen für dich?** *(Pflicht, Matrix: „wichtig" / „nice to have" / „brauche ich nicht")*

| Funktion | Ein-Satz-Erklärung (= subtiler Pitch) |
|---|---|
| **Live-Timer & Dashboard** | Ein Klick startet die Zeit; Heute/Woche/Monat immer im Blick. |
| **Zeiteinträge & Wochenkalender** | Farbiger Wochenkalender, Einträge per Ziehen anlegen. |
| **Statistik & Auswertungen** | Stunden je Kunde/Projekt, Verlauf, Gruppen-Vergleich. |
| **Nachweis-Sichten** | Gespeicherte, auditierbare Sichten für Kunden & Förderung. |
| **Struktur: Kunden → Projekte → Arbeitspakete → Aufgaben** | Echte Hierarchie statt flacher Tag-Liste. |
| **Team-Stimmung** | Tägliche 1–5-Stimmung, anonym aggregiert fürs Team. |
| **CSV-Import mit Rollback** | Altdaten stapelweise importieren – und notfalls als Stapel zurückrollen. |
| **Self-Hosting, Rollen & Rechte** | Läuft auf normalem PHP-Hosting; feingranulare Rechte. |

**F11. *(nur Nutzer/Tester)* Wie gut gelöst findest du diese Bereiche heute?** *(optional, gleiche Matrix: „stark" / „okay" / „ausbaufähig")*

**F12. Welche Funktion fehlt dir am meisten – oder was würdest du zuerst verbessern?** *(optional, Freitext)*

---

## Abschnitt 4 – Planung & Report-Cockpit *(optionaler Pro-Block, aufklappbar)*

> Einleitung im Formular: „Diese Funktionen stecken in **Timeminator Pro**. In der Community siehst du die Oberfläche, aber nicht die Daten. Sag uns, was davon dich wirklich weiterbringen würde."

**F13. Wie interessant wären diese Pro-Funktionen für dich?** *(optional, Matrix: „würde ich nutzen" / „interessant" / „brauche ich nicht")*
Für **Pro-Nutzer** umformuliert zu: „Wie zufrieden bist du damit?"

| Funktion | Ein-Satz-Erklärung |
|---|---|
| **Soll-/Budget-Planung + Live-Report** | Soll-Stunden & -Budget je Projekt, Live-Fortschritt Ist-vs-Soll mit Ampel und Wochen-Heatmap. |
| **Angebote / Kalkulation** | Angebote aus Bausteinen, Stunden- & Pauschalposten, direkt in Rechnung wandeln. |
| **Rechnungen + Buchhaltung** | Rechnungen aus erfassten Zeiten, PDF mit eigenem Briefkopf, lexoffice-Konnektor. |
| **Erweiterte Rollenverwaltung** | Beliebige Rollen, feingranulare Rechte pro Modul, Audit-Log. |
| **REST-API & Konnektoren** | Git-Commits, Kalender (CalDAV/iCal) und mehr automatisch als Zeitquellen. |

**F14. Welche einzelne Funktion würde dich am ehesten zu Pro bewegen?** *(optional, Einfachauswahl – gleiche Liste wie F13)*

**F15. Was wäre für dich ein fairer Preis für Pro?** *(optional, Einfachauswahl)*
< 5 €/Monat · 5–10 €/Monat · 10–20 €/Monat · 20–40 €/Monat · Lieber Einmalkauf · Kann ich nicht einschätzen

---

## Abschnitt 5 – Gesamteindruck & Abschluss

**F16a. *(Nutzer/Tester)* Wie wahrscheinlich würdest du Timeminator weiterempfehlen?** *(Skala 0–10, NPS)*
**F16b. *(Erstkontakt/Nicht-Nutzer)* Wie wahrscheinlich würdest du Timeminator ausprobieren?** *(Skala 0–10)*

**F17. Ein Satz: Was gefällt dir am besten – oder was hält dich (noch) ab?** *(optional, Freitext)*

**F18. Möchtest du Rückmeldung oder Zugang zur Beta?** *(optional, E-Mail)*
> Hinweis: „Nur für Rückfragen zu dieser Umfrage und optionale Beta-Einladung. Keine Werbung."

**Abschluss-Text:** „Danke! Du hast gerade ein Tool mitgeformt, das dir Zeit zurückgeben soll. Neugierig? → Live-Demo: {{DEMO_URL}}"

---

## Die Quests (interaktiv auf der Artifact-Seite)

Die Quests sind kleine, barrierefreie Mini-Spiele direkt im Browser. Sie zeigen je ein Produktprinzip und laden ein, es **auf der Demo oder der eigenen Instanz** echt auszuprobieren.

1. **Flow-Timer** – Start/Stopp-Button, der live die Sekunden zählt. Zeigt den Ein-Klick-Timer. CTA: „Mach's echt in der Demo."
2. **Datenhoheit-Schalter** – Umschalter „fremde Cloud ⟷ dein Server"; Animation bringt die Daten „nach Hause". Zeigt Self-Hosting.
3. **Soll-vs-Ist-Ampel** – Schieberegler für Ist-Stunden; Ampel & Burn-Balken reagieren (grün/gelb/rot). Zeigt das Pro-Planungs-Cockpit.
4. **Dein Zeitleck** – schnelle Chip-Auswahl „Wo verlierst du Zeit?" als Brücke ins Feedback-Formular.

Alle Quests sind rein clientseitig, funktionieren anonym und ohne Login, sind per Tastatur bedienbar und respektieren `prefers-reduced-motion`.
