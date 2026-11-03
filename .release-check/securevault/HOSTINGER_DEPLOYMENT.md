# Hostinger and cPanel deployment

The package is designed for PHP/MySQL shared hosting. It has not been deployed to a real Hostinger or cPanel account in this workspace; verify your plan's document-root, SMTP, extension, and filesystem capabilities.

1. Add your domain/subdomain through the hosting panel and point DNS to the host. Wait until the domain resolves correctly.
2. Enable an SSL certificate and HTTPS redirection in the panel. Use the final HTTPS origin during installation.
3. Select PHP 8.2 or newer and enable the extensions listed in INSTALLATION.md. Configure upload and request limits for the intended file size. The selected Laravel 12 framework supports the available development PHP 8.2 runtime.
4. Create a MySQL database and a dedicated user. Assign that user access to the database. Copy the exact prefixed database/user names and server host supplied by the panel.
5. Upload the verified `SecureVault.zip` using File Manager. Extract into a private `securevault` directory beside `public_html` where your plan permits it.
6. If the panel allows changing the document root, point the domain at `securevault/public`. Otherwise copy the contents of that `public` folder into `public_html`, including hidden `.htaccess`, then replace its `index.php` with the provided `deployment/public_html-index.php`. Adjust the private `$core` path.
7. Grant the PHP account write access to `storage/`, `bootstrap/cache/`, and setup environment files. Do not make the entire account world writable.
8. Open `/install`. Read the generated token from the private `INSTALL_ACCESS.txt` through File Manager and complete the eight setup stages.
9. Set provider SMTP credentials in the wizard. Test the connection and check inbox receipt. After installation, SMTP can be updated/tested in Settings & SMTP.
10. Remove `INSTALL_ACCESS.txt`. Confirm the installer is locked, assets load, and two different user accounts cannot preview each other's media. Confirm public requests to `.env`, `storage`, `vendor`, and source files fail.
11. Test email verification/reset, image/PDF preview, video seeking, retained admin deletion, quota enforcement, and account suspension on the real host before accepting data.

Do not upload the application core directly into `public_html` unless the host cannot support a private sibling directory and the fallback access controls have passed the full HTTP checklist. Apache `.htaccess` does not protect files when the server ignores overrides or runs a different configuration.

No Composer, npm, Docker, Redis, SSH, or always-running worker is required for a fresh browser installation. Hosting Cron Jobs can run optional backups using the PHP binary path provided by the hosting panel. Retention purge is deliberately reserved for a trusted security operator, not an unprotected browser cron URL.

Common failures:

| Symptom | Check |
| --- | --- |
| 500 before installer | Vendor completeness, PHP version/extensions, private directory permissions, correct `$core` path |
| Session/CSRF errors | HTTPS is enabled, secure cookies match URL, PHP can write `storage/framework/sessions` |
| Database connection fails | Panel-supplied host/port, prefixed names, assigned user privileges, empty database |
| Upload rejected before app validation | PHP `post_max_size`/`upload_max_filesize`, host request limit, execution time |
| Video fails or cannot seek | Browser codec support, Range requests reaching PHP, proxy settings, hosting limits |
| Verification/reset email absent | SMTP credentials, sender authorization, outbound port access, SPF/DKIM, spam folder |
| CSS absent | `public/assets` was copied, Bootstrap downloaded before packaging, correct domain origin |
| Installer still accessible after completion | Stop deployment; inspect install lock and environment configuration privately |
