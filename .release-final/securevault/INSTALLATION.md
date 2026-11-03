# Installation and release guide

The deployment candidate passed local PHP/MySQL tests and fresh ZIP installation. Complete the remaining real-hosting release gates in TESTING.md before storing private data.

## Build the ZIP on a development machine

1. Use PHP 8.2+ and Composer. Install dependencies with `composer install --prefer-dist` and download the pinned local Bootstrap asset using `tools/resume-build.ps1` on Windows (or the URL in that script).
2. Run lint, MySQL tests, Composer audit, and the installer acceptance checks. Resolve failures.
3. Run `composer install --no-dev --prefer-dist --optimize-autoloader` on a separate release staging copy, or after completing development tests. Do not use `--ignore-platform-reqs`.
4. Run `php tools/build-release.php`. It creates `dist/SecureVault.zip` and a SHA-256 checksum.
5. Extract this ZIP into a new test directory and install it through the browser against a new empty MySQL database. Test the extracted package before distributing it.

The ZIP contains `vendor/`; Composer and Node are not required on the live server. No `.env`, credentials, installer token, media, runtime sessions, cached configuration, or test database should be in the ZIP. The `.env.install` template contains no secret.

## Hosting requirements

- PHP 8.2+ with ctype, curl, DOM, fileinfo, filter, hash, mbstring, OpenSSL, PCRE, PDO MySQL, session, tokenizer, XML, and ZIP.
- MySQL 8+ or a supported MariaDB release using InnoDB and utf8mb4. Use a dedicated empty database; SQLite is not a supported application database.
- Apache 2.4 with `mod_rewrite` and `.htaccess` overrides, or an equivalent carefully configured server whose document root is exclusively `public/`.
- A working domain and HTTPS certificate. Enable SSL through the hosting panel before setup. Configure an HTTP-to-HTTPS redirect there.
- Writable `storage/`, `bootstrap/cache/`, and application directory during first setup. Prefer directories 0750/0755 and files 0640/0644, with `.env`, tokens, backups, and keys accessible only to the account. Never use 0777.
- Sufficient disk space for uploads, retained deletions, backups, temporary backup ZIPs, and logs. Set PHP `upload_max_filesize`, `post_max_size`, memory/time limits, and the web server request-body limit for your upload policy. `post_max_size` must exceed the maximum file size plus form overhead.
- Outbound SMTP access. Configure SPF/DKIM/DMARC with your provider and use an authorized sender.

PHP and hosting plan compatibility must be checked on the actual target. No real domain or hosting deployment has been tested in this workspace.

## Preferred document-root layout

```text
/home/account/securevault/        application extracted from ZIP
  .env                          generated privately at first request
  app/, vendor/, storage/       never publicly served
  public/                       domain document root
```

Use the hosting domain settings to set the document root to `/home/account/securevault/public`. Upload the ZIP to a private account directory using the hosting file manager, extract it there, and configure the domain. Never run `storage:link`; there are no public media links.

## When the host fixes the root to public_html

Keep the application in `/home/account/securevault`, alongside `public_html`.

1. Copy the **contents** of `securevault/public/`, including `.htaccess`, into `public_html/`.
2. Replace `public_html/index.php` with `securevault/deployment/public_html-index.php` and confirm the `$core` path matches your private application directory.
3. Keep all other application files and private storage in the sibling directory outside `public_html`.
4. Open the site and verify the assets load and `/install` is available.

If absolutely no sibling private directory is available, the root `.htaccess` supplies a fallback that denies application directories, dotfiles, and sensitive file extensions, then routes through `public/`. This depends on Apache actually honoring the file. Do not use this fallback on Nginx/LiteSpeed without equivalent verified access rules. Test every sensitive path listed in SECURITY.md from an unauthenticated client before collecting data. A public-only document root is preferred.

## Browser setup: no server CLI needed

1. Open `https://vault.example.com/install`. The first request creates a fresh `APP_KEY` and random installer token in the private filesystem under a lock.
2. In the hosting file manager, open `securevault/INSTALL_ACCESS.txt`. Paste its token into step 1. Do not send it to other users.
3. Resolve failed server checks in step 2.
4. Supply MySQL host/port/database/user/password in step 3. The connection must work and the database must be empty. Assign the user schema creation/alter/index and normal data privileges during installation.
5. Set site name, the exact HTTPS origin, timezone, and administrator email in step 4. Subdirectory URLs are not supported by this installer.
6. Configure SMTP, test it, then continue at step 5. Passwords are not echoed. STARTTLS and implicit SSL require valid certificates; certificate validation is not disabled.
7. Set the admin name and a strong password at step 6.
8. Confirm deployment layout at step 7 and install. Migrations/defaults/admin creation run on the server. The completion page provides login, registration, and website links.
9. Delete `INSTALL_ACCESS.txt`. Confirm `/install` now returns 404. Protect `.env` and securely back up `APP_KEY`.
10. Sign in, review settings, customize terms/privacy information, test mail and upload/preview from two accounts, then enable registration as desired.

The installer does not reconfigure directory ownership; that must be set through the hosting panel. It checks writability and uses restrictive permissions when creating secrets. Config is not cached during browser setup, so there is no cache-clearing command required for installation.

If setup fails after MySQL starts migrations, reset the dedicated database to empty before retrying. MySQL DDL is not transactional. If the installed lock exists but setup completion failed, treat it as a locked install: inspect through trusted account access and recover from a backup rather than exposing the installer again. Never remove installation locks on an active service.

## SMTP examples

Gmail: `smtp.gmail.com`, port 587, TLS/STARTTLS; port 465 uses SSL. Use an account-specific app password when the provider requires one. Hosting providers typically supply a hostname such as `mail.example.com` plus the mailbox credentials. Follow the provider's current account requirements. Never use a user's login password as a mail credential or send passwords by email.

Changing SMTP in the admin settings encrypts the whole credential object using Laravel encryption. A blank password keeps the saved secret. Send a test message after any change. The verification and reset emails use Laravel's notification templates; welcome, status, and security notices use `VaultNotification`.

## Backups and retention

These optional operations require a hosting cron job or a trusted server operator; browser installation does not require SSH.

Set a **separate**, random 32-byte key encoded as base64 in `.env` as `BACKUP_KEY`. Generate it on a trusted development machine using `php -r "echo base64_encode(random_bytes(32));"`. Store it offline separately from the backups and `APP_KEY`.

Example hosting cron entries (adjust PHP path and account path):

```text
0 2 * * * /usr/bin/php /home/account/securevault/artisan vault:backup --scheduled
```

Enable scheduled backups in admin settings. Archives include a database NDJSON dump and retained/active private media; they are AES-256-GCM authenticated/encrypted in framed chunks and stored outside the web root under `storage/app/private/backups`. They exclude live passwords reset tokens and session payloads. Do not expose a download link. Keep an off-server copy using your hosting backup system.

Backups require temporary space for the unencrypted ZIP before encryption. Restrict filesystem access and use encrypted hosting storage. Schedule backups outside purge windows. Confirm restoration in an isolated environment before relying on this mechanism.

For retained deletions, an OS-level security operator can inspect eligibility with `php artisan vault:security-purge`, then use `--execute` to purge eligible bytes. Add `--logs` only when authorized to delete audit records older than the configured log retention. No ordinary web administrator can call these operations. Database metadata and identity rows remain for audit; account deletion is a soft deletion, not automatic erasure.

To inspect a backup, supply `BACKUP_KEY` in the operator's process environment and run `php tools/decrypt-backup.php source.svb destination.zip` on a trusted offline machine. The tool authenticates every frame and completion marker. The resulting ZIP is confidential plaintext. Restore the NDJSON data and matching media only into an isolated matching-schema environment under database-operator control; an automated restoration wizard is not included. Preserve the original `APP_KEY` to decrypt SMTP settings. Test and document your organization's restoration procedure before launch.

## Optimization and upgrades

Use OPcache and keep Composer dependencies patched. CSS/JS are already served directly from disk; no production Node process is needed. If a server operator uses `config:cache`, `.env` changes require a trusted cache rebuild; avoid config caching until deployment is stable. Blade views compile automatically. Do not route SMTP-dependent operations to an unconfigured asynchronous queue.

Back up the database, media, and keys before upgrades. Test upgrades on a restored staging copy. Use maintenance mode and run migrations with a trusted deployment process. The first-install browser wizard is intentionally unavailable for upgrades.
