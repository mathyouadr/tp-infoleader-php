# InfoLeader — Agent Instructions

Showcase site for an IT hardware company (TP1) + secured admin panel (TP2). French UI, plain PHP, local WAMP.

## Environment

- **WAMP** on Windows, served from `C:\wamp\www\infoleader\` → `http://localhost/infoleader/` (admin: `/infoleader/admin/`).
- **PHP 8.3** (`C:\wamp\bin\php\php8.3.28\php.exe`, not on PATH). Extensions used: `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `curl` (tests).
- **MySQL 8.4** on port 3306. Database `infoleader`, dedicated user `infoleader@localhost` with SELECT/INSERT/UPDATE/DELETE only → schema changes must go through `app/install.php` (runs as root).
- **Apache** with `AllowOverride all`: the `.htaccess` files are a security boundary, never delete them.
- **Composer** (not installed globally; use `composer.phar`) — only dependency: `phpmailer/phpmailer` ^6.9 in `vendor/`.
- Timezone forced to `Europe/Paris` in `app/bootstrap.php` (MySQL uses local time; keep PHP and MySQL aligned).

## Setup & commands

```bash
php composer.phar install                           # PHPMailer
php app/install.php --email=admin@example.com       # DB + user + schema + seed + first admin (prints password once)
php tests/http_test.php                             # end-to-end tests against Apache (98 checks, must stay green)
```

`app/install.php` is CLI-only and re-runnable: reuses `storage/config.php`, applies `app/schema.sql` (`CREATE TABLE IF NOT EXISTS`), seeds `products` from `products.json` only if the table is empty, creates an admin only if none exists.

## Hard Constraints

- **No frameworks / libraries** — no Bootstrap, Tailwind, jQuery, npm. Composer only for PHPMailer.
- **Vanilla JS only**, no complex effects (no parallax, scroll-triggered animations, 3D). Fade-in-on-scroll was removed on purpose.
- **Sans-serif fonts only**, system stack (`Segoe UI Variable` / `system-ui`). **No CDN or external resource**: the site must work offline.
- **Single admin account**, no public registration or user accounts.
- **Real e-mail only through `send_mail()`** in `app/mailer.php`. Never call `mail()` or PHPMailer directly elsewhere.
- All files stay in `C:\wamp\www\infoleader\`.

## Security rules (every change must respect them)

- **CSRF**: `app/bootstrap.php` rejects every POST without a valid token (400). Every form must include `<?= csrf_field() ?>`. An empty session token is rejected too.
- **Access control**: every admin page except `login.php`, `forgot.php`, `reset.php` starts with `$admin = require_login();`. Logout is POST-only.
- **SQL**: PDO prepared statements only (`ATTR_EMULATE_PREPARES = false`). Never concatenate user input into SQL.
- **Output**: escape everything with `e()` (`htmlspecialchars`, ENT_QUOTES, UTF-8).
- **Sessions**: cookie `INFOLEADER_SID`, HttpOnly, SameSite=Strict, strict mode; ID regenerated on login and password change; 30 min idle timeout (`SESSION_IDLE`).
- **Passwords**: `password_hash(PASSWORD_DEFAULT)` / `password_verify()`. Rules in `password_errors()` / `PASSWORD_RULES`: ≥ 20 chars, ≤ 72 bytes (bcrypt limit), lowercase + uppercase, digit, special char. Show the rules with `password_rules_html()`.
- **Rate limiting** (`throttle_check()` / `throttle_fail()` / `throttle_reset()`, table `throttle`, per IP + scope, survives reloads): 3 s after 1st failure, then 6, 12, 24 s (max 60); 5th failure → IP blocked 15 min, logged as `blocked`. Scopes: `login`, `forgot`, `reset`, `password` (current-password checks in Password & User modules), `contact`. For `forgot` and `contact`, **every** request counts. Known limit: no row lock (`ponytail:` comment).
- **Reset codes**: 8 digits, `random_int`, stored hashed, valid 5 min, single use, invalidated after 5 wrong attempts. Same response whether the account exists or not (no enumeration).
- **Secrets**: `storage/config.php` (DB credentials + `app_key`) is generated, git-ignored, never published. The SMTP secret is encrypted with AES-256-GCM (`encrypt_secret()` / `decrypt_secret()`), never re-displayed in forms, never logged. The SMTP debug log masks AUTH and DATA lines (`mail_debug_writer()`).
- **CSP** (sent for every page by `app/bootstrap.php`): `default-src 'self'` → no inline `<script>`, no `style=""`, no `onclick`, on public and admin pages.
- **Uploads**: only via `save_uploaded_image()` — real MIME checked with `finfo` + `getimagesize`, JPEG/PNG/WebP, ≤ 2 MB, random 24-hex filename in `assets/products/uploads/` (its `.htaccess` denies scripts). `delete_uploaded_image()` only deletes files matching that pattern (never the original TP1 images).
- **Backups** (`strip_secrets()`): never export passwords, hashes, SMTP user/secret, tokens or keys.
- **Purge of `storage/tmp`**: deletes only inside the resolved real path, skips links. Never widen it.
- Errors: `display_errors` off; exceptions go to the PHP error log with a generic 500 message.

## Architecture

```
infoleader/
├── index.php            ← home: hero (banner), "Nouveautés" (3 latest products), "Qui sommes-nous ?"
├── products.php         ← catalogue from MySQL `products` + live search (#product-search, #products-grid)
├── contact.php          ← contact form: logs the message (type `contact`) + e-mails it to the configured recipient
├── mentions-legales.php ← legal notice + privacy (fictitious company, data collected, session cookie)
├── style.css            ← single stylesheet for public site AND admin
├── script.js            ← menu, smooth scroll, product filter, auto-hiding alerts, wait countdown, confirm dialogs
├── products.json        ← install seed only (no longer read by pages)
├── composer.json/.lock, vendor/      (web access denied)
├── .htaccess            ← Options -Indexes, denies composer.*, *.md, .gitignore
├── admin/
│   ├── login.php        ← login + brute-force protection
│   ├── logout.php       ← POST only
│   ├── forgot.php       ← sends 8-digit code by e-mail
│   ├── reset.php        ← code check + new password
│   ├── index.php        ← dashboard: logged-in account + module cards
│   ├── products.php     ← Articles: add / edit (?edit=id) / delete, image upload
│   ├── email.php        ← Mailjet SMTP or PHP mail config, save + real test send
│   ├── password.php     ← change password (current + new + confirmation)
│   ├── user.php         ← change admin e-mail (current password + confirmation)
│   └── maintenance.php  ← tabs: system, logs (filters + purge), backup (JSON), purge storage/tmp
├── app/                 (web access denied)
│   ├── bootstrap.php    ← config(), db(), session, CSRF, flash, log_event(), auth, throttle, password rules, settings, crypto
│   ├── layout.php       ← admin_header() / admin_footer() / password_rules_html()
│   ├── site.php         ← site_header() / site_footer() / product_card() for public pages
│   ├── catalog.php      ← products(), product(), format_price(), save/delete_uploaded_image()
│   ├── mailer.php       ← mail_config(), mail_ready(), send_mail($to, $subject, $body, $replyTo = null)
│   ├── install.php      ← CLI installer
│   └── schema.sql       ← migration: admins, password_resets, throttle, settings, logs, products
├── storage/             (web access denied, git-ignored content)
│   ├── config.php       ← generated secrets
│   ├── tmp/             ← temporary files (purgeable from Maintenance)
│   └── logs/smtp-debug.log
├── assets/banniere.png, assets/products/*.jpg (TP1 placeholders), assets/products/uploads/
└── tests/http_test.php  (web access denied)
```

Page pattern:

```php
require __DIR__ . '/../app/bootstrap.php';   // always first (session, CSRF, DB)
require ROOT . '/app/layout.php';            // admin (or ROOT . '/app/site.php' for public pages)
$admin = require_login();                    // admin pages
// handle POST → flash() → redirect() (PRG) ; on validation error, render the same page
admin_header('Titre', $admin, 'Sous-titre');  // renders flashes
// ...markup...
admin_footer();
```

## Database (app/schema.sql)

- `admins` (email unique, password_hash) — one row.
- `password_resets` (admin_id, code_hash, expires_at unix, attempts, used).
- `throttle` (ip, scope) PK, failures, next_allowed, blocked_until, updated_at (unix timestamps).
- `settings` (name, value JSON) — key `mail` holds the e-mail config, `smtp_pass` encrypted.
- `logs` (type ENUM `login_ok|login_fail|blocked|event|contact`, ip, message, created_at). Write with `log_event()`.
- `products` (name ≤ 120, price DECIMAL(10,2), description ≤ 500, image nullable relative path).

## E-mail

- Provider: **Mailjet SMTP** — `in-v3.mailjet.com`, port 587, STARTTLS; username = API Key, password = Secret Key.
- The **sender** (`from_email`) must be a sender address validated in Mailjet, otherwise Mailjet accepts the SMTP transaction (`250 queued`) but never delivers. A `250 OK queued` does **not** prove delivery.
- `to_email` = recipient of test mails **and** of contact-form messages (Reply-To = visitor).
- Reset codes go to the admin account e-mail (module User).
- Under WAMP, PHP `mail()` has no mail server: keep SMTP.

## Design

- Tokens (`:root` in `style.css`): blue `#0056b3`, blue-dark `#003f86`, dark `#2d2d2d`, white, green `#4caf50` (active/success only), mist `#f2f5f9`, line `#dbe2ea`.
- White navbar with green underline on the active link; mist page headers; dark footer with green top rule.
- Signature element: the home "Nouveautés" panel overlapping the hero. Keep the rest quiet: no glass, no card lift, no fade-ins.
- Prices: `format_price()` → `1 299,50 €` (narrow no-break space U+202F, tabular figures).
- Assets are cache-busted with `?v=filemtime(...)` in both layouts — keep it when adding CSS/JS.
- Responsive breakpoints: 900 px (stacked layouts) and 640 px (hamburger menu). Respect `prefers-reduced-motion`.

## Code Conventions

- French for UI text and code comments; code must be commented and readable.
- Full HTML5 documents (`lang="fr"`, UTF-8) via the shared layouts — don't duplicate nav/footer markup.
- Filenames lowercase. Public nav: `index.php`, `products.php`, `contact.php` (+ "Mentions légales" and "Administration" links in the footer).
- Flash messages (`flash()` / `render_flashes()`) auto-hide after ~5.5 s via `.alert[data-autohide]`.
- Forms after a throttled failure get `data-wait="N"` (button countdown, cosmetic — the server enforces the limit).
- Destructive actions: `<form data-confirm="...">` (JS confirm) **plus** a server-side check (confirmation checkbox or explicit action).

## Testing

- `php tests/http_test.php` runs real HTTP requests on `http://127.0.0.1/infoleader/` (Apache must be running).
- It backs up and restores the admin account and the `mail` setting, **disables mail sending during the run** (empty sender) so no real e-mail is sent, and clears throttle rows for `127.0.0.1` / `::1`.
- The Mailjet step connects for real with fake credentials (auth refusal expected).
- Add a check for every new feature; run the suite after every change.

## Key Gotchas

- MySQL evaluates single-table `UPDATE ... SET a = a + 1, b = (a >= ?)` left to right: `b` sees the new `a`.
- The test helper `http()` decodes HTML entities; use its 5th return value (raw body) to assert escaping.
- `post_max_size` = 8M, `upload_max_filesize` = 2M: a POST above 8 MB arrives empty and fails the CSRF check (400).
- Logs, throttle and resets use the client IP from `REMOTE_ADDR` only (never `X-Forwarded-For`).
- Product images from TP1 are placeholders; products without an image show their initial letter.
- Never commit or print `storage/config.php`, Mailjet keys or the admin password.
