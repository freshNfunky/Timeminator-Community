# Timeminator – Zeit-Check (Nutzer-Umfrage & Kampagne)

Attention-first Nutzer-Umfrage für Kaltakquise & Produkt-Feedback. Das Herzstück ist eine
interaktive „Quest"-Seite, die Timeminator subtil vermarktet (ohne zu verkaufen) und zum
Feedback-Formular führt. Stand: 2026-10-13 · Timeminator Community v0.9.0.

## Live-Bausteine

| Baustein | Wo | Zweck |
|---|---|---|
| **Quest-Seite** (self-hosted) | `https://umfrage.timeminator.com/01/` | Öffentliches Attention-Vehikel für LinkedIn/Sales Navigator. 4 Mini-Quests, anonym, kein Login. |
| **Feedback-Formular** | Google Forms (xixum.ai) `…/1FAIpQLScLMOFUT7dmyNqQcQ_rhmPJ5m5U24kXTnF6NyEOEuo6xk2Gtw` | Sammelt die eigentlichen Antworten anonym ein. |
| **Demo** | `https://demo.felixschaller.com` | Live-Instanz zum Ausprobieren. |

> Die Quest-Seite ist self-hosted unter `umfrage.timeminator.com/01/` (eigene Domain → seriöser für Kaltakquise). Die claude.ai-Artifact-Version diente als Vorlage/Preview.

## Dateien in diesem Ordner

- [`fragenkatalog.md`](fragenkatalog.md) – kompletter Fragenkatalog (Referenz, 1:1 in Forms einpflegbar).
- [`google-form-generator.gs`](google-form-generator.gs) – Apps-Script, das das Google Form automatisch baut.
- [`outreach-playbook.md`](outreach-playbook.md) – Platzierungs-Kanäle + Sales-Navigator-Nachrichten (Templates).
- [`banner-deploy.md`](banner-deploy.md) – wie das In-App-Community-Banner deployt wird.
- `assets/` – LinkedIn-Bild (1080×1350 + @2x), QR-Code (PNG + SVG).
- `banner/` – Vorschau des In-App-Banners. Quelle: [`../../Media/banners/zeitcheck.html`](../../Media/banners/zeitcheck.html), Creative: [`../../assets/banners/zeitcheck.svg`](../../assets/banners/zeitcheck.svg).

## Status / offen

- [x] Quest-Seite live (self-hosted) unter `umfrage.timeminator.com/01/`.
- [ ] In-App-Banner-Creative auf `assets.felixschaller.com` hochladen + Feed-JSON ergänzen (siehe `banner-deploy.md`).
- [ ] Release ausliefern, damit die erweiterte Allowlist (`timeminator.com`) bei bestehenden Nutzern greift (steht an; bis dahin wird der Banner-Link auf älteren Instanzen gestrippt — bewusst in Kauf genommen). Generischer Redirect-Dienst als Future Work: Issue #117.
- [ ] Optional: Kanal-Shortlinks für Quellen-Tracking.
