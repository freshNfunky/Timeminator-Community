# Changelog

All notable changes to Timeminator Community are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Unreleased changes live in the `[Unreleased]` section and move under a
version heading when a release is cut.

> **Status: pre-1.0 (alpha / beta).**
> Every `0.x` release is considered pre-stable. Data model, config keys,
> permission codes, HTTP routes and the on-disk layout can still change in
> incompatible ways between minor versions — each such change will be
> called out in the changelog. Version `1.0.0` will mark the first
> release with an API and data-model stability commitment. Until then,
> pin an exact version in production and read the release notes before
> updating. The initial commit was mislabelled "v1.0.0"; the `VERSION`
> file is authoritative.

## [Unreleased]

### Added
- **Side-panel brand mark**: the FelixSchallerCOM wordmark now sits above the
  rotating carousel, color-inverting with the theme (dark in light mode, light
  in dark mode via `mask-image` + `var(--ink)` — no second asset needed and no
  inline styles that would trip the strict CSP).

### Changed
- **Side-panel feed endpoint has a sensible default** so a fresh install serves
  HTML creatives out of the box, without needing a `banners` block in
  `config.php`. `Banners::endpoint()` now falls back to
  `https://assets.felixschaller.com/feed.json` (ad-blocker-neutral host) when
  the operator has not configured one; previously it fell back to an empty
  string, which silently kept the carousel on the bundled offline SVG set.
- **Side-panel bleeds flush to the right viewport edge** (240 px wide, no
  right padding, no shell `max-width` cap), so the slot sits in the periphery
  rather than inside the content column. Dead `.sp-head`/`.sp-label`/
  `.sp-toggle` CSS from the retired collapsible header is gone, along with a
  duplicated `.shell.has-aside`/`.sp-*` block left behind by the back-merge in
  #77.

## [0.6.0] — 2026-10-04

### Added
- Side-panel now also renders **HTML banners as sandboxed `<iframe>`s** (in
  addition to image/SVG), so creatives can be fluid (adapt to the slot) and carry
  their own clickable links. A feed item may have an `image` or an `iframe` URL;
  `iframe` URLs are validated against the same first-party allow-list, and the
  CSP gains `frame-src`/`child-src` for the banner host(s) (derived from
  `banners.allowed_hosts`, so HTML banners load without further config).
- **Arbeitspaket (Workpackage) layer** between projects and tasks
  (fixes #65). Hierarchy becomes **Kunde → Projekt → Arbeitspaket →
  Aufgabe**, matching Pro so CSV exports/imports stay portable between
  editions.
  - New `work_packages` table (SQLite + MySQL) + nullable
    `work_package_id` FKs on `tasks` and `time_entries` (denormalized
    for the same reason as `project_id`/`client_id`).
  - Idempotent migration (`schema/migrations/2026-10-04-01-work-
    packages.php`): CREATE TABLE IF NOT EXISTS, column-exists guards
    around ALTER TABLE for upgrading installs, and a transactional
    backfill that seeds a default "Allgemein" work package per project
    and remaps every existing task + time entry to it. Zero-touch for
    live installations.
  - New admin nav entry **Arbeitspakete** between Projekte and
    Aufgaben; CRUD view at `?r=workpackages` under
    `structure.manage`.
  - Task form grows an "Arbeitspaket" select; the entries form
    option-groups tasks by *Kunde / Projekt / Arbeitspaket* so the
    hierarchy is visible when picking.
  - CSV export adds `work_package_code` and `work_package_name`
    columns. CSV import still accepts files without these columns —
    tasks land in the project's default "Allgemein" WP (backwards
    compat for existing exports and third-party CSVs).
- **Pro-feature teaser pages.** Four new nav entries — Rechnungen,
  Angebote, Budget, Rollen Pro — open dimmed read-only mockups of the
  features that only exist in Timeminator Pro. Each page carries a
  sticky upsell banner on the right edge linking to `pro_url`. No data
  is written, nothing is sent; the mockups are seeded from the user's
  real clients / projects where available so the preview looks
  plausible. All inline positioning (budget progress bars) is emitted
  into nonce-authorized `<style>` blocks so the strict CSP stays strict.
- `LoginThrottle::retryAfterSeconds($username, $ip)` — precise upper
  bound in seconds until whichever locked bucket clears first; used to
  fill the `Retry-After` header.
- **Dockerfile + docker-compose.yml** for zero-infrastructure local
  evaluation and self-hosting. Image is `php:8.3-apache` with PDO
  (MySQL and SQLite), zip for the in-app updater and mod_rewrite
  enabled — the same shape as a typical PHP shared host. Two Compose
  profiles: `sqlite` (single service) and `mysql` (app + MariaDB).
  `data/` is persisted on a named volume. The project remains
  Composer-free at install time: `composer.json` and `vendor/` are
  excluded from the build context via `.dockerignore`. README gains a
  "Run it in Docker" section (fixes #19).
- **Update banner.** Admins see a small "new version available" panel
  under the top bar when the release manifest reports a newer version.
  Three actions: **Ignorieren** (dismiss for this session — comes back
  next login), **Ueberspringen** (skip this specific version until a
  newer one appears; persisted globally), **Update** (jump to
  Admin → System to apply). All buttons are forms with CSRF tokens; no
  inline JS. The banner respects `Updater::isDisabled()` and stays
  hidden for non-admins.
- **Opportunistic update check.** The layout partial triggers
  `Updater::opportunisticCheck()` once every 24 h per install (throttled
  by the cache timestamp). Silent on failure — a page never breaks
  because the release manifest is unreachable.
- **`.php` data migrations.** `Updater::runMigrations()` now picks up
  both `.sql` and `.php` files from `schema/migrations/<driver>/`. PHP
  migrations are `require`d in an isolated closure and receive access
  to `DB` and `Settings` — for backfills that cannot be expressed as
  one SQL statement. Applied migrations are tracked by basename in
  `Settings['applied_migrations']` as before, so an existing install is
  unaffected.
- **`docs/schema-migrations.md`.** Documents the migration naming
  convention (`YYYY-MM-DD-NN-slug.<ext>`), the additive-by-default
  policy, the `.php` migration flow and the pre-1.0 compatibility
  contract.
- **`referrer_or_default()` helper.** Same-origin-only redirect helper
  for the new dismiss/skip flow — keeps the admin on whatever page they
  were on instead of jumping to the dashboard.
- Community banner carousel with server-side feed loading, offline fallback and
  opt-outable telemetry (fixes #22). A slim, collapsible banner column on the
  right of the main layout rotates through FelixSchallerCOM services, tools and
  news:
  - Creatives are loaded **server-side** from a configurable subdomain
    (`banners.endpoint`, default `https://banners.felixschaller.com/feed.json`)
    through a first-party route (`index.php?r=banners`), so the strict default
    CSP (`connect-src 'self'`) needs no exception. The fetch is async and never
    blocks page rendering, and results are cached for `banners.cache_ttl`.
  - On any network failure the app falls back to the **bundled default set** in
    `assets/banners/default.json`, so the slot is never empty or broken on an
    isolated server.
  - Every creative is sanitized: links are accepted only as `https`
    `felixschaller.com` URLs and open with `rel="noopener noreferrer"`; images
    only as same-origin assets or `https` `felixschaller.com` URLs.
  - A small anonymized usage signal rides along with each feed refresh (app
    version, a random installation id stored in `data/settings.json`, a salted
    hash of it, and a truncated request IP). **No time-tracking data is ever
    sent.** Each field is switchable under `banners.telemetry` in `config.php`.
  - **Admin → System → Community-Banner & Telemetrie** adds runtime toggles:
    show/hide the column (`banner_visible`, default on), a master telemetry
    opt-out (`banner_telemetry`), and an endpoint override.
  - Set `banners['enabled'] => false` in `config.php` to remove the whole
    feature and its telemetry (the Pro edition ships it off).
  - Documented in `docs/BANNERS.md`.
  - Creatives are portrait "skyscraper" (300x600) SVGs with the real brand
    logos embedded. Bundled set covers the FelixSchallerCOM brand portfolio:
    AI Transformation ("AI Maturity Made Right"), Fractional & Interim
    Deep-Tech, the XIXUM AI-maturity assessment (xixum.ai), AF-AX
    ("the wing that never stalls", af-ax.com, logo + eVTOL-photo variant), and
    Timeminator Pro.
  - The feed link/image allow-list is configurable via `banners.allowed_hosts`
    (default `felixschaller.com`, `xixum.ai`, `af-ax.com`; subdomains included);
    foreign links/images are stripped. Images may be a same-origin asset, an
    `https` first-party URL, or an inline `data:image/*` URI, so remote
    creatives render under the strict default CSP without changes.
  - Default feed endpoint is `https://assets.felixschaller.com/feed.json`.

### Changed
- **Banner slot renamed to a side-panel** (ad-blocker-neutral: no "banner"
  anywhere in element classes, ids, or the fetch URL). Element ids /
  classes are now `#sidepanel` / `.sp-*`, the first-party fetch route is
  `?r=sidepanel`, and the live feed subdomain migrated from
  `banner.felixschaller.com` to `assets.felixschaller.com` so content-
  blocking filter lists stop disappearing the whole column. Config keys
  (`banners.*`) and the admin UI label ("Anzeige") are unchanged.
- **PromoFeed system retired.** The provisional `src/PromoFeed.php` +
  `views/partials/promo_sidebar.php` + `assets/promo-fallback.json`
  wiring (branch-only) is superseded by the `Banners` + `#sidepanel`
  carousel which supports both `image` creatives and sandboxed HTML
  `iframe` banners.
- **LoginThrottle now escalates the lockout window for repeat offenders**
  in a 24 h horizon: 15 min → 1 h → 4 h → 24 h. The first lockout stays
  at the baseline 15 min; every subsequent lockout inside the horizon
  climbs one rung. Message copy reflects the actual wait ("… in 1 Stunde
  erneut versuchen") (fixes #38).
- `ctrl_login` now returns **HTTP 429 Too Many Requests** with a
  `Retry-After: <seconds>` header when `LoginThrottle` refuses a login.
  The German error string in the page body stays unchanged, so the
  human path is identical; the change is for monitoring probes, health
  checks and retry scripts (fixes #37).
- `asset()` now appends a cache-busting `?v=<filemtime>` query string to
  every `assets/*` URL (CSS/JS/images) so browsers pick up a changed file
  immediately after a deploy — including a one-off hotfix via `scp`, not
  just a version bump. Falls back to `app_version()` when the file is not
  found on disk (#78).
- `.gitignore` now excludes runtime artifacts under `data/` (`settings.json`,
  `*.json`, `*.log`) so per-install state and logs stay out of the repo.
- `docs/ISSUES.md` and the README privacy section describe the banner carousel
  and its telemetry.

### Config
- New `banners` block in `config.sample.php` (`enabled`, `endpoint`,
  `cache_ttl`, `telemetry.*`, `hash_salt`). Pre-1.0: this is an additive,
  optional block; existing installs keep working with the Community defaults.

## [0.5.0] — 2026-09-29

Big rollup. All the work under `[Unreleased]` since 0.2.0 lands here in one
release. Highlights: dark mode, login brute-force protection, strict CSP,
CSV import + export, updater integrity + rollback, installer endpoint
control, PHPUnit suite, repo-hygiene docs.

### Added
- Dark mode via `prefers-color-scheme`, with a manual override hook
  (`data-theme="light|dark"` on the document root). Palette moved into
  CSS custom properties on `:root`; the canvas chart renderer reads
  `--panel`, `--ink`, `--muted`, `--line`, `--brand` from the theme so
  the charts follow (fixes #18).
- Login brute-force protection: rolling-window lockout per username
  (5 fails / 15 min) and per IP (20 fails / 15 min), with a progressive
  backoff (300 ms → 3 s) before the lockout trips. Failed attempts are
  logged to a new `login_attempts` table (fresh installs from the
  schema, existing v0.1/v0.2 installations from
  `schema/migrations/<driver>/`), old rows are pruned opportunistically,
  and a successful login clears its user's failure record. New
  `client_ip()` helper respects a `trusted_proxies` allow-list from
  config so `X-Forwarded-For` is only honoured behind a listed peer
  (fixes #7).
- Security response headers on every response: a strict
  Content-Security-Policy (`script-src 'self'`, `style-src 'self'
  'nonce-…'`, `style-src-attr 'none'` — no `'unsafe-inline'`),
  X-Content-Type-Options, X-Frame-Options, Referrer-Policy,
  Permissions-Policy and — under HTTPS — HSTS. Wired into `app_boot`
  and `install.php`. Overridable per-host via a `csp` key in
  `config.php` (fixes #8).
- Installer exposes the update-manifest URL and the registration
  endpoint as editable fields behind an "Erweitert" section (defaults
  pre-filled), plus check boxes to disable the update check and the
  registration outright. Admin → System gains a matching "Endpoints"
  form so the same overrides can be edited later without touching
  `config.php`; overrides live in `settings.json` (fixes #12).
- CSV / JSON export of time entries at `?r=entries_export`, honouring the
  existing filter bar and streaming stable columns (client and project
  codes included so the exported file round-trips back through the
  importer). Admins may pass `all=1` to export every user's entries
  (fixes #15).
- CSV import (Admin → Import): upload with an auto-detected `,`/`;`
  delimiter, a per-row preview showing which entries will be inserted
  and which are skipped (unknown client / project / task / user, or a
  bad timestamp), and confirm-to-write. Each import creates one
  `import_batches` row and tags every inserted `time_entries` row with
  its `batch_id`. Batches can be rolled back as a unit from the same
  screen. New permission `admin.imports` (fixes #16).
- Updater now verifies the SHA-256 of every downloaded release archive
  against a sibling `<zip>.sha256` or a `SHA256SUMS` asset from the
  release before extracting. A mismatch fails the update; a release
  that ships no checksum at all logs a warning by default and hard-
  fails when `require_release_checksum` is set in config (fixes #2).
- Updater now prunes stale files inside `src/`, `views/`, `assets/`
  and `schema/` that used to exist locally but are no longer part of
  the release. Top-level custom files and everything under `data/`,
  `config.php`, `.git`, `vendor/` and `.phpunit.cache/` stay untouched
  (fixes #3).
- Updater takes a full snapshot of every file it is about to overwrite
  or prune into `data/backup_<ts>/` and restores from it on any failure
  during the copy / prune / migration phase, so a half-written update
  never leaves the site broken. On success the backup manifest is kept
  in Settings, and `Updater::rollbackLast()` lets a future admin action
  restore the previous version (fixes #4).
- `SECURITY.md` with a private vulnerability-reporting policy, supported
  versions, response-time expectations, and scope (fixes #9).
- `CONTRIBUTING.md` restating the "no Composer, no build step" constraint,
  the coding conventions, and the branch model (fixes #10).
- GitHub issue templates (`bug_report.yml`, `feature_request.yml`) and
  `config.yml` routing security reports to the private-advisory flow
  (fixes #10).
- `PULL_REQUEST_TEMPLATE.md` with a manual smoke-test section and a
  release-readiness checklist (fixes #10).
- This `CHANGELOG.md`, following Keep a Changelog (fixes #11).
- GitHub Actions CI workflow: a `php -l` sweep across the codebase, the
  PHPUnit test suite on a matrix of PHP 8.1 / 8.2 / 8.3 / 8.4, and a smoke
  check that a fresh SQLite install boots the login screen and emits the
  security response headers (fixes #13).
- Initial PHPUnit test suite under `tests/`: `Stats` math (work-day
  boundary, track fallback, aggregate sums, day series), helpers (`h()`,
  `cfg()`, `client_ip()` proxy hardening) and `LoginThrottle` behaviour
  (per-user and per-IP lockouts, `clearForUser`, `prune()` horizon).
  Kept Composer-free — the phar is downloaded on demand, the app itself
  ships no `vendor/` (fixes #14).

### Changed
- README no longer claims that CSV imports are rollbackable — no import
  feature ships in this edition yet (fixes #6). The wiki
  [Data Model](https://github.com/freshNfunky/Timeminator-Community/wiki/Data-Model)
  page carries the same correction.
- `DB::applySqlFile` now uses a proper SQL statement splitter that
  respects single-, double- and backtick-quoted strings and both `--`
  and `#` line comments and `/* … */` block comments, so future
  migrations with non-trivial bodies apply correctly (fixes #5).
- Views no longer carry `onclick=` / `onsubmit=` / `onchange=` or
  `style=""` attributes; `app.js` no longer sets `element.style.foo`.
  A per-request CSP nonce authorizes one `<style>` block in the layout
  that assigns dynamic client / project colors to `.dot[data-color=…]`
  elements. This lets the default CSP drop `'unsafe-inline'` from both
  `script-src` and `style-src` (finishes #8).

### Fixed
- Administrators can now delete other users' time entries.
  `Repo::deleteEntry` previously enforced `user_id = current_user`,
  silently blocking admins for entries they did not own — inconsistent
  with edit, which already respected the admin bypass (fixes #1).

## [0.2.0] — 2026-09-28

### Added
- `CODE_OF_CONDUCT.md` (Contributor Covenant v2.1) with a private
  enforcement contact.
- Public GitHub repository at
  <https://github.com/freshNfunky/Timeminator-Community>.
- Branch protection on `main` (linear history, PR required) and `develop`
  (PR required).
- Community wiki with installation, updating, security, data-model, Nginx,
  FAQ, troubleshooting, and Community-vs-Pro pages, in English and German.

## [0.1.0] — 2026-09-26

### Added
- First public edition. Self-hosted, project-based time tracking with a
  live start/stop timer and manual bookings.
- Data model: clients → projects → tasks → time entries, with
  denormalized `client_id` / `project_id` on entries for history stability.
- Statistics: hours per project and client, time series (day / week /
  month), two-group comparison, and a saved, fully generic "proof" view
  with an optional cutover date.
- Web installer (`install.php`) that verifies requirements, provisions the
  schema, seeds default roles, and creates the first admin.
- In-app updater against GitHub Releases, preserving `config.php` and
  `data/` across updates.
- One code base for MySQL / MariaDB and SQLite.
- Login with role-based permissions (admin, user, custom roles).
- CSRF tokens, prepared statements, bcrypt password hashing, `.htaccess`
  rules denying `config.php`, `data/`, and schema files from the web.
- Nginx sample configuration under `docs/nginx.conf.sample`.

### Note
- The initial commit message reads "v1.0.0" — the actual release line is
  0.x. This is a cosmetic mislabel; the on-disk `VERSION` file is the
  source of truth.

[Unreleased]: https://github.com/freshNfunky/Timeminator-Community/compare/v0.6.0...HEAD
[0.6.0]: https://github.com/freshNfunky/Timeminator-Community/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/freshNfunky/Timeminator-Community/compare/v0.2.0...v0.5.0
[0.2.0]: https://github.com/freshNfunky/Timeminator-Community/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/freshNfunky/Timeminator-Community/releases/tag/v0.1.0
