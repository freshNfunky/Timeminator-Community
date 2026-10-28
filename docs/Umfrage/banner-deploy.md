# In-App-Banner „Zeit-Check" – Deploy

Das Community-Banner rotiert im Sidepanel (Portrait-Skyscraper, 300×600). Es gibt **nur dieses
eine Hochformat**; mobil wird dasselbe Creative skaliert. Details: [`../BANNERS.md`](../BANNERS.md).

## Artefakte

- **Quelle (HTML):** [`../../Media/banners/zeitcheck.html`](../../Media/banners/zeitcheck.html)
- **Render-Master (PNG @2x):** `Media/banners/zeitcheck@2x.png`
- **Creative (self-contained SVG):** [`../../assets/banners/zeitcheck.svg`](../../assets/banners/zeitcheck.svg)
- **Paste-fertiges Feed-Item:** [`feed-item.json`](feed-item.json)

### Neu rendern (bei Textänderung)

```bash
cd Media/banners
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  --headless=new --disable-gpu --hide-scrollbars --default-background-color=00000000 \
  --screenshot="zeitcheck@2x.png" --window-size=300,600 \
  --force-device-scale-factor=2 --virtual-time-budget=3500 \
  "file://$PWD/zeitcheck.html"
# dann das SVG-Creative neu bauen (siehe Commit-Historie / Python-Snippet)
```

## Feed-Item

`href` zeigt direkt auf die Quest-Seite. `image` ist als **`data:`-URI** eingebettet, damit das
Creative unter der **Default-CSP** (`img-src 'self' data:`) bei **jeder** Instanz rendert — ohne
dass Admins ihre CSP anpassen müssen.

```json
{
  "id": "zeitcheck-2026",
  "title": "Gestalte Timeminator mit – der Zeit-Check: 3 Minuten, anonym, direkt in die Roadmap.",
  "cta": "Umfrage starten",
  "href": "https://umfrage.timeminator.com/01/",
  "image": "data:image/png;base64,…  (vollständig in feed-item.json)"
}
```

## Lokalisierung (DE/EN)

Die Banner werden **je nach Timeminator-Spracheinstellung** lokalisiert ausgespielt — eine
deutsche oder englische Variante. Für den Zeit-Check gibt es daher zwei Creatives und zwei
Feed-Items:

| Sprache | Creative | href |
|---|---|---|
| DE | `assets/banners/zeitcheck.svg` | `https://umfrage.timeminator.com/01/` |
| EN | `assets/banners/zeitcheck-en.svg` *(dein EN-Creative)* | `https://umfrage.timeminator.com/01en/` |

> Hinweis: Im aktuellen Community-Banner-Code (`src/Banners.php`, `src/controllers/banners_ctrl.php`)
> gibt es **keine** Sprach-Logik. Die Lokalisierung läuft außerhalb dieses Moduls (Feed-seitig oder
> in einem anderen Branch). Mechanik hier noch nachzutragen, sobald geklärt.

### EN-Umfrageseite

Die englische Variante der Quest-Seite ist gebaut: [`quest-en.html`](quest-en.html) (die deutsche
Live-Version liegt zur Parität als [`quest-de.html`](quest-de.html) daneben). Deployen als
**Geschwister-Ordner** `umfrage.timeminator.com/01en/` (als `index.html`) — dann zeigt der englische
Banner sauber dorthin. Die Root-Landingpage ([`root-index.html`](root-index.html)) verlinkt beide.

**Feedback-Formular EN:** erstellt (Generator: [`google-form-generator-en.gs`](google-form-generator-en.gs))
und in `quest-en.html` eingetragen — der englische Funnel ist damit durchgängig (EN-Seite → EN-Form).

## Allowlist & Release

Der `href` zeigt **direkt** auf `umfrage.timeminator.com`. Jede Community-Instanz säubert den Feed
gegen **ihre eigene** `banners.allowed_hosts` — die Allowlist lebt also in jeder laufenden Instanz.
`timeminator.com` (eine First-Party-Brand-Domain, Subdomains inklusive) wurde dem Code-Default
hinzugefügt (`config.sample.php`, `src/Banners.php`, `src/helpers.php`) und **greift, sobald die
Instanzen auf die Release mit dieser Änderung aktualisiert sind** (steht unmittelbar an).

Bis dahin wird der Link auf älteren Instanzen gestrippt (Banner-Bild ohne funktionierenden Link).
Da die Release unmittelbar ansteht, wird das bewusst in Kauf genommen — ein Zwischen-Redirect
lohnt den Aufwand nicht. Ein generischer, ID-basierter Shortlink-/Redirect-Dienst (bit.ly-Stil)
ist als Future Work notiert: Issue #117.

## Upload auf assets.felixschaller.com

Erst Creative/Feed hochladen, dann `feed.json` neu generieren. Deploy-Methode (SSH/rsync) steht
noch aus — Zielhost/-pfad bitte ergänzen:

```bash
# PLATZHALTER – echten Host/Pfad eintragen:
# scp assets/banners/zeitcheck.svg USER@HOST:/var/www/assets/
# danach feed.json auf dem Server um das Item aus feed-item.json ergänzen
```

> `assets.felixschaller.com` und `banner.felixschaller.com` teilen denselben Docroot; der
> Server-Ordner heißt weiterhin `banner/`.
