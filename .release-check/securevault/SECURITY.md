# SecureVault security model

**Status: unverified source build.** Do not store confidential production data until dependency installation, runtime tests, security review, ZIP installation tests, and actual hosting checks are complete. See BUILD_STATUS.md.

## Access boundaries

Authentication uses Laravel's session guard and hashed passwords. Verification links are signed and time limited; password reset tokens use Laravel's hashed token repository, expire, and are deleted on successful use. A session version on the user invalidates previously issued sessions after password or account status changes. Password changes also rotate remember tokens. Login, registration, reset, verification resend, upload, and installer requests have route throttles.

Active-account middleware checks status, verification policy, session version, and idle timeout before vault routes. Super admin routes additionally require the administrator role. File policies allow only the owner or a role with `files.manage` to inspect content; deletion requires `files.manage`. UUIDs are identifiers, not an authorization substitute.

The app exposes no user download, edit, delete, rename, share, or export route. This restricts application actions. Authorized previews necessarily deliver original media bytes to the browser. A user can save these bytes or capture the screen despite hidden controls and context-menu suppression. Watermarks and viewer hints do not provide DRM. PDF browser toolbars and operating-system capture are outside application control.

## Uploads and storage

Files are stored using randomized `.bin` paths under `storage/app/private/vault`, outside `public/`. The database stores the real MIME/extension and SHA-256; storage paths are hidden from model serialization. Protected routes resolve storage paths only from authorized database records, never from user-supplied paths. The default local storage server route is disabled.

Both extension and server-detected MIME must match a hard allowlist and category/global rules. Raster images must parse as images. SVG, HTML, JS, PHP, scripts, archives, and executables cannot be enabled by web settings. TXT/CSV can be enabled explicitly. Executable markers are rejected, but content checks do not prove files harmless. Optional ClamAV scanning uses INSTREAM; when configured, connection/scanning failures reject uploads. `REQUIRE_ANTIVIRUS=true` blocks all uploads if no scanner is configured. Restrict the ClamAV service to trusted private access and align its stream-size limits with application limits.

PDFs and media may exercise vulnerabilities in client preview software. Keep browsers, PHP, Laravel, operating system, and antivirus patched. MIME validation and basic marker inspection are not substitutes for antivirus or a professional security assessment.

Global/user quota accounting uses MySQL transactions and row locks. Logical deletion retains bytes and quota until the security purge command succeeds. The application does not encrypt ordinary uploads at rest; use encrypted hosting storage and an appropriate host backup policy. SMTP credentials use authenticated Laravel encryption with the application key. Private backups use a separate 32-byte backup key and authenticated encryption.

## Audit and retention

Web interfaces only append and read logs. Neither users nor super admins can edit/delete log entries through routes. Application identity, email, IP, user agent, device class, and event timestamps are recorded. Failed login descriptions do not include entered passwords or reset tokens. Device classification is approximate, not fingerprinting.

Only a security operator with OS/server access can invoke `vault:security-purge --execute --logs`. Configure restricted SSH/cron access accordingly. Web roles do not confer operating-system access. Database superusers and host operators can alter data; application controls do not make records tamper-proof against a compromised database account. For stronger integrity, forward logs to an independent append-only audit system and restrict DB privileges under your operational policy.

User deletion is soft deletion with login disabled, version revocation, and audit preservation. Files must be removed from the active vault first. This implementation intentionally retains identity and file metadata rather than claiming legal erasure. Retention periods, lawful basis, privacy notices, offsite backup lifecycle, and erasure procedures must be set by the operator.

## Browser and deployment controls

CSRF protection covers POST/PATCH/PUT/DELETE. Blade escapes user output, query builder/Eloquent bind query inputs, and settings validate lengths and known values. Headers include nosniff, same-origin framing, a local-assets CSP, restrictive permissions, private no-store cache policy, and HSTS on secure requests. Protected content uses inline disposition and byte-range-capable file responses.

Use HTTPS and secure HttpOnly SameSite cookies. Do not trust arbitrary forwarded headers or configure all proxies as trusted. If your host terminates TLS through a proxy, configure its documented trusted proxy addresses and verify Laravel recognizes HTTPS. Never enable `APP_DEBUG` on a public deployment.

Fresh deployments generate their own key and installer claim token under a filesystem lock. The token is read through trusted filesystem access. The installer checks an empty database before migrations, serializes final provisioning, then writes a private lock and clears its environment token. All installer routes return 404 once installed. Do not remove locks to upgrade or repair a running installation.

The application core must be outside the document root wherever possible. The fallback root `.htaccess` is defense in depth only and requires target-host verification. On Nginx, configure a public-only root and equivalent denied paths; Nginx does not read `.htaccess`.

## Mandatory hosting acceptance checks

Use an unauthenticated browser or HTTP client and verify these never return file contents:

```text
/.env
/.env.install
/.env.example
/INSTALL_ACCESS.txt
/composer.json
/composer.lock
/vendor/autoload.php
/storage/logs/laravel.log
/storage/app/private/vault/example.bin
/bootstrap/cache/config.php
/database/migrations/
/app/Models/User.php
/tools/decrypt-backup.php
/dist/SecureVault.zip
```

Also verify HTTP redirects to HTTPS, secure cookies are present, the certificate is valid, `/install` is disabled after setup, CSRF rejects cross-origin writes, and another user's UUID cannot retrieve a preview or content. Test `Range: bytes=0-1023` and seeking for authenticated videos, plus guest and suspended-account requests to the same endpoint. Ensure media responses are never publicly cached by a CDN or reverse proxy.

Back up application keys separately. Losing `APP_KEY` makes encrypted SMTP settings and existing sessions unreadable; rotating it requires a planned credential/session migration. Losing `BACKUP_KEY` makes encrypted backups unrecoverable. Never publish either key in an issue, email, source repository, or release ZIP.
