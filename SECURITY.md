# Security Policy

Thanks for helping keep Timeminator Community and its users safe.

## Supported versions

Only the **latest published release** on the `main` branch is supported with
security fixes. Older tags do not receive backports.

| Version | Supported |
| --- | --- |
| Latest release (see [VERSION](VERSION)) | ✅ |
| Previous releases | ❌ |
| `develop` branch (pre-release) | ❌ (fix will land in the next release) |

## Reporting a vulnerability

**Please do not open a public GitHub issue for security problems.**

Report privately through one of these channels:

1. **Preferred — GitHub private security advisory.**
   Open one at
   <https://github.com/freshNfunky/Timeminator-Community/security/advisories/new>.
2. **Email.** `felix@ftc-creative.de` with subject prefix `[timeminator-security]`.
   PGP is not required. If you want an encrypted reply, say so and include your
   public key.

Include, if you can:

- Affected version (from `VERSION` or the URL of the running instance if it is
  public and you have permission to test it).
- Reproduction steps or a minimal proof of concept.
- The impact you observed (data disclosure, privilege escalation, RCE, …).
- Whether you have disclosed the issue to anyone else.

## What to expect

- **Acknowledgement** within **72 hours** on working days.
- A **fix-or-timeline response** within **14 days**. Complex issues get an ETA
  and a status update at least once a week thereafter.
- **Public disclosure** happens together with the fix release. Credit is given
  in the release notes unless you ask to stay anonymous.
- If we decide an issue is **not a vulnerability**, we say so with reasoning
  and, when appropriate, file a public issue for a non-security fix instead.

## Scope

**In scope**

- The code in this repository, at the versions listed above.
- The default configuration produced by `install.php`.
- The web installer itself.

**Out of scope**

- Vulnerabilities in third-party dependencies of the host system (PHP, Apache,
  Nginx, MySQL, MariaDB, SQLite) that are not caused by Timeminator's usage of
  them. Report those upstream.
- Misconfigurations on the operator's side (weak admin passwords, missing
  HTTPS, world-readable `config.php`, unrestricted `.htaccess`).
- Denial-of-service through resource exhaustion where the mitigation is the
  operator's responsibility (rate limiting at the reverse proxy, PHP-FPM
  worker limits, etc.).
- Social engineering, physical attacks, or attacks requiring already-privileged
  filesystem access.

## Coordinated disclosure

We follow **90-day** coordinated disclosure: if a fix is not available within
90 days of the report, the reporter may disclose publicly. We will actively
work to fix issues faster.

## Hall of thanks

Reporters who follow this policy are credited in the release notes of the fix.
If you want a mention, tell us how you would like to be listed
(name / handle / affiliation).
