# Schema migrations

Timeminator applies pending migrations automatically after every in-app
update, and any manual deployment goes through the same code path. This
document is the contract between a migration author and the runtime.

## Files

Migrations live under `schema/migrations/<driver>/`, one file per change,
per driver:

```
schema/migrations/mysql/2026-09-27-01-login-attempts.sql
schema/migrations/sqlite/2026-09-27-01-login-attempts.sql
```

Anything under `schema/migrations/` (no driver folder) is a legacy portable
file that both drivers pick up — new migrations should not use that path.

### Naming: `YYYY-MM-DD-NN-slug.<ext>`

- `YYYY-MM-DD` — the day the migration was written (calendar date, UTC or
  local, one file per intent per day is fine).
- `-NN` — a two-digit counter within that day, starting at `01`. If two
  authors ship migrations on the same day, the second one bumps to `02`.
- `-slug` — kebab-case description of what the file does, short and
  boring: `login-attempts`, `entries-add-source`, `backfill-project-code`.
- `<ext>` — `.sql` for DDL/DML, `.php` for data transformations that need
  procedural logic (see below).

The date is the version. We deliberately do **not** use semver x.y.z for
migrations — a migration is a point-in-time event, not a released
package. Semver still applies to `VERSION` and release tags.

### Ordering

`glob()` + `sort()` gives a lexicographic order, and `YYYY-MM-DD-NN`
sorts correctly as strings — so migrations always run in the intended
sequence without any explicit dependency graph.

### Tracking

Applied migrations are recorded by basename (without the driver folder)
in `Settings['applied_migrations']` — a JSON array persisted under
`data/settings.json`. Rerunning the migrator is idempotent: it skips
whatever is already listed there.

## What migrations may do

- **Additive schema changes are the default.** `CREATE TABLE IF NOT EXISTS`,
  `CREATE INDEX IF NOT EXISTS`, `ALTER TABLE … ADD COLUMN` with a default,
  seeding lookup rows.
- **Destructive changes require a matching data migration.** If a column
  moves or a table splits, ship a `.php` migration that copies the old
  data into the new shape *before* dropping the old column or table.
- **Never assume a fresh database.** The migration will run on databases
  that predate every release we have ever cut. Guard every step with
  `IF NOT EXISTS` / `IF EXISTS` / a runtime existence check.
- **Do not rely on the current app code.** A migration file may run
  against a much older app version if the operator delayed updates.
  Import only what you need (`DB`, `Settings`), don't call `Repo::` or
  `Auth::` — their contract may drift.

## PHP data migrations

For anything that cannot be expressed as one `.sql` statement — a
backfill loop, a checksum recomputation, splitting one row into many —
use a `.php` file with the same naming rules. The runtime `require`s the
file, so the top of it runs to completion. `DB::` and `Settings::` are
available, plus every helper from `src/helpers.php`.

```php
<?php
// schema/migrations/sqlite/2026-11-04-01-backfill-project-code.php
declare(strict_types=1);

$rows = DB::all('SELECT id, name FROM projects WHERE code IS NULL OR code = ""');
foreach ($rows as $r) {
    $code = preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $r['name']));
    DB::run('UPDATE projects SET code = ? WHERE id = ?', [$code, (int) $r['id']]);
}
```

A `.php` migration is expected to be idempotent — the runtime records it
as applied only after it completes without throwing.

## Compatibility policy (pre-1.0)

Timeminator is currently `0.x`. Between minor versions, we may need to
change data shape. When we do:

1. The new release contains both the migration files and any application
   code that reads the new shape (write both new and old shape during
   the same release when helpful).
2. If we ever drop support for reading the old shape, the release notes
   say so explicitly, and a data migration ships in the same release
   that converts the last of the old data.
3. A migration never runs in the middle of a request — it runs at
   deploy / update time from `Updater::apply()` (or a one-shot admin
   trigger for manual deploys, if we add one).

## Reserved words

- `schema_migrations` table exists in the schema files but is currently
  unused; the source of truth is `Settings['applied_migrations']`. Do
  not depend on `schema_migrations` from application code.
- `import_batches` is domain state, not migration state — do not reuse
  it for migration tracking.
