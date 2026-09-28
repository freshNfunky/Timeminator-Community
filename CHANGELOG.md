# Changelog

All notable changes to Timeminator Community are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Unreleased changes live in the `[Unreleased]` section and move under a
version heading when a release is cut.

## [Unreleased]

### Added
- Dark mode via `prefers-color-scheme`, with a manual override hook
  (`data-theme="light|dark"` on the document root). Palette moved into
  CSS custom properties on `:root`; the canvas chart renderer reads
  `--panel`, `--ink`, `--muted`, `--line`, `--brand` from the theme so
  the charts follow (fixes #18).
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

### Changed
- README no longer claims that CSV imports are rollbackable — no import
  feature ships in this edition yet (fixes #6). The wiki
  [Data Model](https://github.com/freshNfunky/Timeminator-Community/wiki/Data-Model)
  page carries the same correction.
- `DB::applySqlFile` now uses a proper SQL statement splitter that
  respects single-, double- and backtick-quoted strings and both `--`
  and `#` line comments and `/* … */` block comments, so future
  migrations with non-trivial bodies apply correctly (fixes #5).

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

[Unreleased]: https://github.com/freshNfunky/Timeminator-Community/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/freshNfunky/Timeminator-Community/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/freshNfunky/Timeminator-Community/releases/tag/v0.1.0
