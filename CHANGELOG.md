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

### Changed
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

### Added
- `LoginThrottle::retryAfterSeconds($username, $ip)` — precise upper
  bound in seconds until whichever locked bucket clears first; used to
  fill the `Retry-After` header.

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

[Unreleased]: https://github.com/freshNfunky/Timeminator-Community/compare/v0.5.0...HEAD
[0.5.0]: https://github.com/freshNfunky/Timeminator-Community/compare/v0.2.0...v0.5.0
[0.2.0]: https://github.com/freshNfunky/Timeminator-Community/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/freshNfunky/Timeminator-Community/releases/tag/v0.1.0
