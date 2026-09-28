# Contributing to Timeminator Community

Thanks for wanting to help. Bug reports, feature requests, and pull requests
are all welcome.

## Ground rules

1. **No Composer, no build step.** Timeminator installs by unzipping and
   running `install.php`. A change that requires an operator to run
   `composer install` or a bundler is not going to land.
2. **No framework, no CDN.** Plain PHP 8, PDO, server-rendered views, vanilla
   JavaScript, one small canvas-based chart module. Everything must work
   offline.
3. **One code path for MySQL / MariaDB and SQLite.** Both are first-class.
   Anything driver-specific goes through the driver abstraction; do not
   branch on `db_driver` in controllers or views.
4. **Follow the data model.** Bookings live under
   `client → project → task → time entry`, with denormalized `client_id` and
   `project_id` on entries for history stability. See the wiki
   [Data Model](https://github.com/freshNfunky/Timeminator-Community/wiki/Data-Model)
   page.
5. **Community stays Community.** Features that belong in Pro (budget
   planning, quoting, invoicing, accounting connectors, unlimited-role
   inheritance UI) are out of scope for this repo. See
   [Community vs. Pro](https://github.com/freshNfunky/Timeminator-Community/wiki/Community-vs-Pro).

## Working locally

Zero-infrastructure setup with SQLite:

```bash
git clone https://github.com/freshNfunky/Timeminator-Community.git
cd Timeminator-Community
cp config.sample.php config.php     # db_driver is already 'sqlite'
php -S localhost:8000
```

Open <http://localhost:8000/>, finish the short setup, and you have a
working install with a real database file under `data/`.

For MySQL / MariaDB: create a database, set `db_driver = 'mysql'` in
`config.php`, then run `mysql -u USER -p DBNAME < schema/mysql.sql` or let
the installer do it.

## Branching model

- **`develop`** — the working branch. All feature and fix PRs target
  `develop`.
- **`main`** — release-only. Populated by a squash- or rebase-merge from
  `develop`, tagged, and cut into a GitHub Release.

Never open a PR directly against `main` — it will not be merged.

## Coding conventions

- PHP 8.1 syntax. Types where they help, arrays where they don't.
- PSR-12 style, 4-space indent, LF line endings.
- Prepared statements for **every** query — no string interpolation into SQL.
- CSRF token on **every** form.
- Escape all output at the view layer (`htmlspecialchars` or the shipped
  helper).
- File layout mirrors the router: `views/<area>/<action>.php`,
  `src/<Domain>/<Class>.php`.
- No hidden globals. Pass state through the request / response objects.

## Commit messages

- One logical change per commit.
- Subject line under 72 characters, imperative mood ("Add", "Fix", "Remove").
- Body wraps at 72 characters. Explain **why**, not what — the diff is the
  what.
- Reference issues with `Fixes #123` or `Refs #123`.

## Pull requests

- Base against `develop`.
- One PR per issue is preferred; a bundled PR is fine when the changes
  travel together (e.g. a docs sweep).
- Fill out the PR template. If you delete a checklist item, say why.
- Keep the diff small. A large refactor split from a small fix is easier to
  review.

## Testing

There is no test suite yet (see [#14](https://github.com/freshNfunky/Timeminator-Community/issues/14)).
Until it exists, please describe the manual smoke test you ran in the PR
body — at minimum, install fresh on SQLite, log in, and exercise the code
path you touched.

## Reporting bugs

Use the [bug report template](.github/ISSUE_TEMPLATE/bug_report.yml). Include
PHP version, database driver, and web server so we can reproduce.

## Security issues

**Do not open a public issue.** See [SECURITY.md](SECURITY.md).

## License

By contributing, you agree that your contribution is licensed under the MIT
License, the same as the rest of the repository.
