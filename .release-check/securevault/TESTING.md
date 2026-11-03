# Validation and release gates

No tests have run successfully yet: Composer hit a full C: drive, and the shell service cannot start until that drive has free space. The following is a test plan and authored suite, not a record of passed checks.

## Development prerequisites

Free at least approximately 1 GB on C: for the execution service, then run `tools/resume-build.ps1`. The script redirects Composer home/cache/temp to D: and installs the pinned Bootstrap 5 CSS. Keep testing dependencies installed while running the suite.

Create an empty, dedicated **MySQL/MariaDB** database named `securevault_test`. The test suite runs migrations and rolls back data; never point it at an existing application database. Supply DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and DB_PASSWORD through the test process environment if the defaults in `phpunit.mysql.xml` do not match your setup. Existing external environment values take precedence. The runtime does not use SQLite for application tests.

```sh
php artisan test --configuration=phpunit.mysql.xml
composer audit
```

Lint every PHP source file excluding vendor/runtime caches, compile Blade views, inspect `php artisan route:list`, and fix failures before releasing. If development `.env` includes cached config, clear that through a trusted local command first. Use an isolated build/staging directory for installer testing; the installer intentionally modifies its `.env` and locks itself.

## Authored integration coverage

- Registration without a phone field, hashing, duplicate-address response, audit entry, verification notification.
- Valid, tampered, and expired signed email verification links.
- Failed/successful login and login audit history.
- Verification and pending-approval access gates.
- Email password recovery notification, generic missing-address response, password hashing, single-use reset token, session-version revocation.
- Private upload, correct owner access, cross-user IDOR denial on both preview page and media bytes, unauthorized-access logs.
- Missing user delete/edit/download/share actions and admin-route denial.
- Executable extension, MIME mismatch, quota, and disabled-category rejection without orphaned stored files.
- Admin retained deletion, protected account deletion, configured minimum file retention.
- Suspension/session invalidation, current-password verification, password change revocation.
- Encrypted SMTP persistence without rendering saved secrets.
- Read-only audit endpoints and primary member/admin view rendering.
- Installer token gating, step sequencing, uninstalled redirects, and post-install lockout.

The suite is not yet executed. It does not replace full wizard, SMTP provider, Apache, browser codec/range, backup restore, or real hosting testing.

## Full installer acceptance run

1. Build/extract a separate release staging copy with complete `vendor` and assets; remove local secrets/runtime caches through the release builder, not ad hoc production deletion.
2. Serve its `public/` through a test HTTPS domain with writable private storage. Supply a separate empty MySQL database and a test SMTP mailbox.
3. Confirm the first request generates a new unique application key/token. Confirm a second deployment generates different values.
4. Confirm a wrong token, expired/missing CSRF token, skipped stages, malformed URL, invalid timezone, database failure, nonempty database, and SMTP failure produce safe responses without secrets.
5. Complete all eight stages. Verify migrations/defaults/admin in MySQL, sign in, register another account, verify mail, and exercise reset/approval workflows.
6. Confirm `installed.lock` exists and `/install` GET/POST both fail after completion. Check concurrent install attempts cannot provision twice.
7. Verify two-user ownership, authenticated video ranges and HEAD, PDF/image previews, category filters, sizes/quotas, admin file deletion, activity and retention behavior.
8. Run the sensitive-path HTTP checklist from SECURITY.md under the actual root layout and fallback layout if supported.

## Production release gates

Do not label an artifact production-ready until all of these are satisfied:

- Complete dependency installation, pinned lockfile, passing PHP lint/Blade rendering/tests, and resolved Composer security audit.
- No local credentials, data, sessions, caches, debug pages, install tokens, or test database inside the ZIP.
- Real SMTP delivery and reset/verification tested against a provider mailbox, including token expiry and resend limits.
- Apache/hosting root restrictions and HTTPS/cookies checked externally; direct source/storage access denied.
- Browser behavior checked on supported desktop/mobile browsers, including PDF controls, video seek/range support, full-screen/zoom, and honest capture limitations.
- Backup creation/decryption/tamper rejection and an isolated restoration procedure tested. Scheduled operations, offsite copies, retention ownership, and monitoring are configured.
- A review of access policies, upload scanning policy, concurrency, logging, operator permissions, privacy terms, and failure recovery.
- Fresh extracted ZIP installed on the intended cPanel/Hostinger environment without Composer/Node/SSH during installation.
