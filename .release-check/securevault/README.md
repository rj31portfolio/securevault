# SecureVault

Private media and document storage built with Laravel 12, PHP 8.2+, MySQL/MariaDB, Blade, Bootstrap 5, and local CSS/JavaScript. No Node runtime is needed on hosting.

**Build status:** source has been authored, but dependency installation was interrupted by a full C: drive. This build is not tested or approved for production. There is no completed release ZIP yet. Read [BUILD_STATUS.md](BUILD_STATUS.md) before running or deploying it.

## Included implementation

- Email/password registration with terms acceptance, signed email verification, resend throttling, optional approval, and email password resets through Laravel's password broker.
- Login history, session-version revocation, current-password verification for password changes, and account status enforcement.
- User dashboards, private uploads, category filtering, activity history, profile updates, and protected image/video/PDF/text previews.
- A policy checks ownership or administrator file permission for both preview pages and media requests. Users have no file edit, rename, delete, share, export, or download routes.
- MIME/extension allowlists, category size rules, user/global quotas with database locks, randomized storage paths, SHA-256 hashes, optional ClamAV INSTREAM scanning, and private storage outside `public/`.
- Super admin account management, retained file deletion, category administration, encrypted SMTP settings, email tests, branding uploads, watermark controls, quotas, session timeout, and retention settings.
- Append-only audit interfaces. Retention deletion is available only through a server operator command; there is no web route that edits or deletes audit logs.
- Browser installer with a per-deployment claim token, server checks, empty-database connection testing, site/SMTP/admin setup, migrations, defaults, and an installation lock.
- Release builder that excludes local secrets, runtime data, caches, and test databases while bundling installed Composer dependencies.
- Private encrypted backups and separate server security retention commands. Backups and retention are optional hosting cron jobs, independent of browser installation.

## Start development after resolving disk space

On this Windows workspace, run `tools/resume-build.ps1`. It keeps Composer home/cache/temp on D: and downloads the pinned Bootstrap CSS. Then follow [TESTING.md](TESTING.md).

For a fresh development checkout:

```sh
composer install --prefer-dist
cp .env.install .env
```

Set `APP_URL=http://127.0.0.1:8000`, `SESSION_SECURE_COOKIE=false`, and `APP_ENV=local` for local HTTP testing. Download Bootstrap using the pinned URL in `tools/resume-build.ps1`. Start `php artisan serve`, open `/install`, and read the generated `INSTALL_ACCESS.txt` using the local filesystem. The first browser request generates the key and access token. No default administrator or password is shipped.

The installer uses file sessions/cache before the database exists. It does not depend on a queue worker, symlink, Redis, SMS, Firebase, MongoDB, or external database service. MySQL connection settings are persisted in `.env`; SMTP credentials are encrypted in MySQL using `APP_KEY`.

## Project map

| Location | Purpose |
| --- | --- |
| `app/Http/Controllers` | Authentication, dashboards, media, admin, installer |
| `app/Http/Middleware` | Install gate, active-account gate, admin gate, headers |
| `app/Models` | Users, roles, permissions, files, categories, settings, storage |
| `app/Policies/VaultFilePolicy.php` | Ownership and administrator permissions |
| `app/Services` | Upload validation, private storage, audit, mail, antivirus, backups |
| `database/migrations` | MySQL schema, foreign keys, indexes, timestamps, soft deletion |
| `database/seeders/VaultSeeder.php` | Default categories, roles, permissions, settings |
| `resources/views` | Blade pages and local interface layouts |
| `public/assets` | Bootstrap CSS, custom CSS, and JavaScript |
| `bootstrap/first-run.php` | Per-deployment secret initialization |
| `tools/build-release.php` | Clean ZIP packaging |
| `tests/Vault` | MySQL integration and authorization regression tests |

Required tables include `users`, `roles`, `permissions`, `categories`, `files`, `file_views`, `user_activities`, `login_histories`, `security_logs`, `user_sessions`, `storage_usage`, `settings`, `password_reset_tokens`, and `email_verification_logs`. Laravel session/cache/job tables and a permission-role pivot are also included.

## Operational boundaries

Browser viewing sends media bytes to the viewer. Hiding controls and adding watermarks do not prevent saving, screenshots, recordings, or external cameras. Do not promise DRM. Administrators can inspect users' media; this is not end-to-end encryption.

Files are private on disk and over authenticated HTTPS routes, but ordinary uploaded media is not encrypted at rest by the application. Use hosting-volume encryption when required. Backups are encrypted with a separate key. Deleting a file removes access immediately and retains bytes until a server security operator applies the retention policy. Retained bytes count toward quota.

Default approval can be disabled without disabling verification. A super admin is created as verified during installation because they control installation and SMTP setup. Other accounts must follow the configured verification/approval flow.

Branding accepts raster images only. TXT and CSV are optional admin-enabled formats; executable formats can never be enabled. PDFs and MOV playback depend on the browser. Native PDF viewers may expose controls despite toolbar hints. Watermarks are overlays, not burned into source media.

Email delivery requires real provider credentials and must be verified at deployment. The installer can submit a test message but cannot guarantee inbox delivery. Review the default terms/privacy notice and organization-specific retention obligations before enabling registration.

See [INSTALLATION.md](INSTALLATION.md), [HOSTINGER_DEPLOYMENT.md](HOSTINGER_DEPLOYMENT.md), [SECURITY.md](SECURITY.md), and [TESTING.md](TESTING.md).
