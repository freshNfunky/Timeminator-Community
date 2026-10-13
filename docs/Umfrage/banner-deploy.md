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
  "href": "https://claude.ai/artifact/Bk5a3MoW3EuJCNPPvs2aFb",
  "image": "data:image/png;base64,…  (vollständig in feed-item.json)"
}
```

## ⚠️ Wichtig: Allowlist & Release

Der `href` liegt auf `claude.ai`. Jede Community-Instanz säubert den Feed gegen **ihre eigene**
`banners.allowed_hosts`. `claude.ai` wurde dem Code-Default hinzugefügt (`config.sample.php`,
`src/Banners.php`, `src/helpers.php`) — **greift bei bestehenden Nutzern aber erst nach einem
Update auf eine Release, die diese Änderung enthält.**

Zwei Wege:
1. **Release ausliefern**, dann Banner live schalten (sauber, aber mit Vorlauf).
2. **Sofort für aktuelle Nutzer:** `href` auf einen Redirect unter einer bereits erlaubten
   Domain zeigen — ein **Pfad**, keine neue Subdomain: `https://felixschaller.com/zeitcheck`
   → 302 zur Quest-Seite. `felixschaller.com` ist bei allen Instanzen längst erlaubt — kein
   Release nötig. Vorteil: stabile, retargetbare URL (später auf `timeminator.com`
   umbiegbar, ohne Banner/Print zu ändern).

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
