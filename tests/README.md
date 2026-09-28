# Tests

Small PHPUnit suite for the high-value invariants — stats math, helpers,
login-throttle behaviour. Kept Composer-free so the project ships without a
`vendor/` directory.

## Run locally

Download the PHPUnit phar once (any recent 10.x or 11.x works with PHP 8.1+):

```bash
curl -L https://phar.phpunit.de/phpunit-11.phar -o phpunit.phar
chmod +x phpunit.phar
```

Then, from the repo root:

```bash
./phpunit.phar --colors=always
```

Or with a specific PHP binary:

```bash
php phpunit.phar --colors=always
```

`phpunit.phar` is git-ignored so each contributor can update it independently.

## What is covered

- **`Stats`** — work-day boundary, track fallback, aggregate sums, day series.
- **helpers.php** — `h()`, `cfg()`, `client_ip()` proxy hardening.
- **`LoginThrottle`** — per-user and per-IP lockout thresholds, `clearForUser`,
  `prune()` horizon.

Each test that needs a database boots its own fresh in-memory SQLite via
`TestSupport::bootInMemoryDb()` (drops the previous connection, re-applies
the base schema). No shared fixtures across tests.

## What is not covered yet

- Installer end-to-end. Needs a temp document root + `php -S` fixture; open a
  separate issue if you want to add it.
- Updater apply / rollback. Non-trivial because it moves files inside
  `APP_ROOT`. Sandbox-then-copy is doable — good follow-up for someone with
  the appetite.
- Controllers. The router expects a live `$_SERVER` and `$_SESSION`; a small
  helper that fakes a request would let these become unit-testable.

The GitHub Actions workflow in `.github/workflows/ci.yml` runs `php -l` across
the codebase and this test suite on PHP 8.1, 8.2, 8.3, and 8.4 for every push
and PR, plus a smoke check that a fresh SQLite install boots the login screen.
