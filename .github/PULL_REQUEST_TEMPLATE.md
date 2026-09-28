<!--
Thanks for the PR. A few reminders:
- Base against `develop`, not `main`.
- Community stays Community — no Composer, no build step, no framework, no CDN.
- Keep the diff focused. Split large refactors from small fixes.
-->

## Summary

<!-- What this PR does, in one or two sentences. -->

## Motivation

<!-- Why? Link the issue(s) this closes or references. Use `Fixes #123` to auto-close. -->

Fixes # <!-- or -->
Refs #

## Changes

- <!-- one bullet per meaningful change -->

## Manual smoke test

<!--
There is no test suite yet. Describe what you actually ran:
- OS / PHP version:
- Database (SQLite / MySQL / MariaDB):
- Web server (built-in / Apache / Nginx):
- Steps you exercised and what you observed.
-->

- OS / PHP:
- Database:
- Web server:
- Steps:

## Checklist

- [ ] Base branch is `develop`.
- [ ] No Composer / build step / external CDN introduced.
- [ ] Prepared statements for every new SQL query.
- [ ] CSRF token on every new form.
- [ ] All output is escaped.
- [ ] MySQL **and** SQLite both work with the change.
- [ ] `VERSION` bumped if this PR is a release candidate (otherwise leave as-is).
- [ ] Docs and the wiki updated if user-facing behavior changed.
- [ ] No secrets, keys, or personal data in the diff.

## Screenshots (UI changes only)

<!-- Drag in before / after screenshots. -->
