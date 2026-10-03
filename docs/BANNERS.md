# Community banner carousel & telemetry

The Community edition carries a slim banner column on the right of the main
layout that rotates through FelixSchallerCOM services, tools and news. This is
how the free edition is funded: free users get the latest news through the same
channel, and the operator gets a basic, anonymized count of running instances in
return. **In Timeminator Pro the whole feature — and any telemetry it entails —
is removed.**

This document describes exactly what the feature does, what is sent, and how to
turn every part of it off.

## How it loads

1. The page asks a **first-party route** of your own installation
   (`index.php?r=banners`) for the carousel — never a third party directly. The
   request is asynchronous and never blocks page rendering.
2. That route fetches the creatives **server-side** from a configurable banner
   subdomain (default `https://banners.felixschaller.com/feed.json`) so creatives
   can be rotated without shipping a new Timeminator release, and caches them for
   `cache_ttl` seconds.
3. On **any** failure — isolated server, blocked outbound traffic, CSP block,
   timeout, malformed response — the app serves the **bundled default set** in
   [`assets/banners/default.json`](../assets/banners/default.json), so the slot
   is never empty or broken.

Because the browser only ever talks to your own installation, the strict default
Content-Security-Policy (`connect-src 'self'`) needs no exception.

### Feed format

```json
{
  "items": [
    {
      "id": "spring-sale",
      "title": "Headline",
      "text": "One or two sentences.",
      "cta": "Call to action",
      "href": "https://felixschaller.com/landing",
      "image": "https://banners.felixschaller.com/spring.svg"
    }
  ]
}
```

Every creative is sanitized before it is shown:

- `href` is accepted **only** if it is `https://` on `felixschaller.com` or a
  subdomain — the remote feed can never point the slot at an arbitrary site.
- `image` is accepted only as a same-origin relative asset or an `https` image on
  `felixschaller.com`. Note the default CSP sets `img-src 'self' data:`; to show
  remote images, extend `img-src` via the `csp` key in `config.php`.
- Links open in a new tab with `rel="noopener noreferrer"`.

## Telemetry (Community only)

With each **feed refresh** (so at most once per `cache_ttl`, not once per page
view) the server attaches a small, anonymized usage signal:

| Field | Meaning | Config key |
|-------|---------|------------|
| `v`   | App version, e.g. `0.6.0` | `banners.telemetry.version` |
| `iid` | Random UUID generated once on first run, stored in `data/settings.json` | `banners.telemetry.install_id` |
| `hid` | `sha256(install_id + host + hash_salt)` — lets an instance behind dynamic DNS be recognized across IP changes | `banners.telemetry.hashed_id` |
| `ip`  | Request IP, **truncated** (IPv4 last octet zeroed, IPv6 reduced to a /48) | `banners.telemetry.ip` |

**No time-tracking data is ever sent** — no entries, hours, clients, projects or
tasks. The only data that leaves your server is the table above.

## Turning it off

- **Whole feature (operator / Pro):** set `banners['enabled'] => false` in
  `config.php`. The column and all telemetry are removed; the admin toggles have
  no effect.
- **Admin, at runtime:** **Admin → System → Community-Banner & Telemetrie**
  - *Banner-Spalte anzeigen* — show/hide the column (`banner_visible`).
  - *Anonymisiertes Nutzungssignal senden* — master telemetry opt-out
    (`banner_telemetry`). With it off, the feed is fetched with no usage signal
    attached.
  - *Banner-Endpoint* — point the fetch at your own feed, or leave blank for the
    `config.php` default.
- **Per field:** disable any individual telemetry field under
  `banners['telemetry']` in `config.php`.
- **Per browser:** each viewer can collapse the column with the toggle in its
  header; the collapsed state is remembered in their browser's `localStorage`.

## Privacy notice

If you run a Community instance for others, your privacy notice / imprint should
describe the telemetry above: what is sent, why, and that it can be turned off.
A consent banner for the third-party fetch is tracked separately in issue #23.
