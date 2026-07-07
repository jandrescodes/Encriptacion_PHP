# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Custom PHP MVC authentication system built with Composer, a lightweight router, and role-based access control.

- **Server:** XAMPP (Apache + MySQL) at `http://localhost/Encriptacion_PHP/public`
- **PHP:** >= 8.2
- **Database:** MySQL/MariaDB (import `database/schema.sql` to initialize)
- **Dependencies:** Composer — `phpmailer/phpmailer`, `vlucas/phpdotenv`, `phpunit/phpunit` (dev)

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Copy environment config
cp .env.example .env
# Edit .env with your DB credentials and SMTP settings

# 3. Import database schema
mysql -u root -p < database/schema.sql

# 4. (Optional) Load seed data
mysql -u root -p < database/seeds.sql

# 5. Start XAMPP Apache and MySQL services
```

No build step needed — PHP files are served directly by Apache.

## Environment Variables (.env)

```
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=
DB_DATABASE=login

SMTP_HOST=smtp.gmail.com
SMTP_USERNAME=your@email.com
SMTP_PASSWORD=your_app_password
SMTP_PORT=587

APP_URL=http://localhost/Encriptacion_PHP/public
APP_TIMEZONE=America/Bogota
APP_VERSION=1.14.0

CACHE_ENABLED=true
CACHE_TTL_USERS=60

REMEMBER_ME_ENABLED=true
REMEMBER_ME_TTL=2592000

SESSION_TIMEOUT=1800

ACTIVE_SESSIONS_ENABLED=true

LOGIN_LOCKOUT_ENABLED=true
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
```

The `.env` file is loaded by `vlucas/phpdotenv` in `app/Config/config.php`, which also defines the `APP_URL` constant and the `env()` helper.

- `APP_VERSION` — displayed in the shared footer
- `REMEMBER_ME_ENABLED` — enable/disable persistent login via cookie (default `true`)
- `REMEMBER_ME_TTL` — cookie + token lifetime in seconds (default `2592000` = 30 days)
- `SESSION_TIMEOUT` — inactivity expiry in seconds (default `1800` = 30 min)
- `ACTIVE_SESSIONS_ENABLED` — enable/disable active-session tracking and server-side revocation (default `true`)
- `LOGIN_LOCKOUT_ENABLED` — enable/disable account lockout (default `true`)
- `LOGIN_MAX_ATTEMPTS` — failed attempts before lock (default `5`)
- `LOGIN_LOCKOUT_MINUTES` — lock duration in minutes (default `15`)

## Architecture

### Request Flow

```
Browser → public/index.php → App\Core\Router → Controller::method()
       → App\Core\Controller::render() → views/layouts/header.php + view + views/layouts/footer.php
```

`public/index.php` bootstraps autoload, creates the router with the DB connection, loads `routes/web.php`, and dispatches by HTTP method + URI path.

### URL Scheme

| URL                         | Controller method                     |
| --------------------------- | ------------------------------------- |
| `/`                         | `HomeController::index()`             |
| `/login`                    | `AuthController::login()`             |
| `/logout`                   | `AuthController::logout()`            |
| `/forgot-password`          | `AuthController::forgotPassword()`    |
| `/reset-password?token=...` | `AuthController::resetPassword()`     |
| `/profile`                  | `ProfileController::profile()`        |
| `POST /profile/password`    | `ProfileController::changePassword()` |
| `/users`                    | `UserController::index()`             |
| `/users/create`             | `UserController::create()`            |
| `/users/edit?id=X`          | `UserController::edit()`              |
| `POST /users/delete`        | `UserController::delete()`            |
| `/activity-logs`            | `ActivityLogController::index()`      |
| `GET /activity-logs/data`   | `ActivityLogController::data()`       |
| `/sessions`                 | `SessionController::index()`          |
| `POST /sessions/revoke`     | `SessionController::revoke()`         |
| `POST /sessions/revoke-others` | `SessionController::revokeOthers()` |

### Key Files

| Path                                       | Purpose                                                                                                                                                                                                                                                                                                                                                                                                |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `public/index.php`                         | Front controller — bootstraps autoload, router, and dispatches requests                                                                                                                                                                                                                                                                                                                                |
| `public/.htaccess`                         | Apache rewrite rules + HTTP Security Headers (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `CSP`, etc.) via `mod_headers`                                                                                                                                                                                                                                                           |
| `routes/web.php`                           | All route definitions — GET/POST mapped to controller methods                                                                                                                                                                                                                                                                                                                                          |
| `app/Config/config.php`                    | Loads `.env` via phpdotenv; defines `APP_URL` constant and `env()` helper                                                                                                                                                                                                                                                                                                                              |
| `app/Config/database.php`                  | `App\Config\Database` singleton — `Database::getConnection()` returns the shared `\mysqli` instance                                                                                                                                                                                                                                                                                                    |
| `app/Config/cache.php`                     | Cache bootstrap; exposes `appCache()` with graceful fallback when cache dir is not writable                                                                                                                                                                                                                                                                                                            |
| `app/Config/autoload.php`                  | Bootstrap entry point: sets timezone, loads cache + database, defines `session_start_secure()` helper (httponly + samesite + secure), starts session, calls `Auth::restoreFromCookie()`                                                                                                                                                                                                                |
| `app/Core/Router.php`                      | `App\Core\Router` — registers GET/POST routes, strips APP_URL base path, dispatches to controller                                                                                                                                                                                                                                                                                                      |
| `app/Core/Controller.php`                  | Abstract base — `render(string $view, array $data, bool $protected)` and `redirect(string $path)`                                                                                                                                                                                                                                                                                                      |
| `app/Core/Model.php`                       | Abstract base — holds `protected \mysqli $db`                                                                                                                                                                                                                                                                                                                                                          |
| `app/Core/Auth.php`                        | `App\Core\Auth` — credential verify, remember-me token issue/consume/clear, password reset tokens (stored as SHA-256 hash), `restoreFromCookie()`, lockout methods                                                                                                                                                                                                                                     |
| `app/Model/LoginAttempt.php`               | `App\Model\LoginAttempt` — atomic `registerFailure()` via `INSERT ... ON DUPLICATE KEY UPDATE`, `lockedSecondsRemaining()`, `clear()`, `getLockedCount(): int`; `identifier` is the PK (no surrogate id)                                                                                                                                                                                               |
| `app/Core/Csrf.php`                        | `App\Core\Csrf` — `token()` generates/returns session CSRF token; `verify()` validates `$_POST['_csrf']` with `hash_equals()`                                                                                                                                                                                                                                                                          |
| `app/Middleware/AuthMiddleware.php`        | Static guards: `auth()`, `admin()`, `timeout(\mysqli)`, `session(\mysqli)` — validates the current `session_token` against `user_sessions`, destroys the session and redirects to `/login` if revoked                                                                                                                                                                                                  |
| `app/Controller/AuthController.php`        | All auth logic: login, logout, forgotPassword, resetPassword                                                                                                                                                                                                                                                                                                                                           |
| `app/Controller/HomeController.php`        | Dashboard: applies timeout + auth middleware, instancia `User`/`ActivityLog`/`LoginAttempt` para pasar métricas reales a la vista                                                                                                                                                                                                                                                                      |
| `app/Controller/ProfileController.php`     | User profile: `profile()` (edit info) and `changePassword()` — any authenticated user; `$id` always from `$_SESSION['user_id']`                                                                                                                                                                                                                                                                        |
| `app/Controller/UserController.php`        | Full user CRUD — guarded by `admin()` middleware                                                                                                                                                                                                                                                                                                                                                       |
| `app/Controller/ActivityLogController.php` | Audit log viewer — `index()` GET `/activity-logs` (HTML, guarded by `admin()`); `data()` GET `/activity-logs/data` (JSON endpoint for DataTables server-side); private `sanitizeFilters(array): array`                                                                                                                                                                                                 |
| `app/Controller/SessionController.php`     | Active sessions management — `index()` lists the authenticated user's sessions; `revoke()` deletes one session by id (scoped to `user_id`); `revokeOthers()` deletes all sessions except the current one; private `guard()` (timeout + session + auth) and `currentTokenHash()`                                                                                                                      |
| `app/Model/User.php`                       | `App\Model\User` — all DB queries via MySQLi prepared statements; `updateProfile()` (info only, no password/is_admin), `getPasswordById()`, `updatePasswordProfile()` (by id), `getTotalCount(): int`                                                                                                                                                                                                  |
| `app/Model/ActivityLog.php`                | `App\Model\ActivityLog` — `log()` static (singleton DB), `logTo(\mysqli)` (test-injectable), `getAll(array $filters = [], ?int $limit = null, ?int $offset = null): array` (LEFT JOIN users, dynamic WHERE, prepared statements), `getTotalCount(array $filters = []): int`, `getCountTodayByEvent(string): int`, `getRecentEvents(int=5): array`; private `buildWhere(array): array`; event constants |
| `app/Model/UserSession.php`                | `App\Model\UserSession` — `create()`/static `createTo(\mysqli, ...)` (stores SHA-256 hash of the session token, never raw), `touch(hash)` (updates `last_activity`), `existsActive(hash, ttl): bool`, `getForUser(userId, currentHash): array` (marks `is_current` via hash comparison in SQL), `revoke(id, userId): bool` (scoped to owner), `revokeAllExcept(userId, currentHash): int`, `deleteByTokenHash(hash)`, `purgeExpired(ttl): int`                                                                                                                                                                    |
| `app/Service/MailerService.php`            | PHPMailer encapsulation — SMTP via STARTTLS                                                                                                                                                                                                                                                                                                                                                            |
| `views/layouts/header.php`                 | Shared `<head>` + nav for all protected pages; accepts `$pageTitle`, `$favicon`, `$bodyClass`, `$useDataTables`, `$pageStyles`                                                                                                                                                                                                                                                                         |
| `views/layouts/footer.php`                 | Shared footer with version; accepts `$useDataTables`, `$pageScripts`                                                                                                                                                                                                                                                                                                                                   |
| `views/layouts/messages.php`               | Centralized SweetAlert2 toast notification logic                                                                                                                                                                                                                                                                                                                                                       |
| `views/home/index.php`                     | Dashboard — hero + 4 stat-cards (usuarios totales, logins hoy, fallos hoy, cuentas bloqueadas) + tabla Bootstrap de últimos 5 eventos del audit log; sin DataTables; wrapped by shared layout via `protected: true`                                                                                                                                                                                    |
| `views/auth/`                              | Standalone auth views (login, forgot-password, reset-password) — include their own `<head>`; assets self-hosted (no external CDN)                                                                                                                                                                                                                                                                      |
| `views/profile/index.php`                  | Unified profile view — two independent forms: edit info (`POST /profile`) and change password (`POST /profile/password`); each with its own `_csrf` token                                                                                                                                                                                                                                              |
| `views/activity-log/index.php`             | Audit log view — Bootstrap table with DataTables server-side; `<tbody>` vacío (AJAX); formulario de filtros colapsable (evento, username, date_from, date_to); badge warning cuando hay filtros activos; badges por tipo de evento; wrapped by shared layout                                                                                                                                           |
| `views/user/`                              | Protected user CRUD views — wrapped by shared layout                                                                                                                                                                                                                                                                                                                                                   |
| `views/session/index.php`                  | Active sessions view — table of the user's sessions (device/browser, IP, created, last activity, current-session badge); "Close all other sessions" form shown only when more than one session exists; per-row revoke button disabled for the current session                                                                                                                                       |
| `views/errors/`                            | Standalone error views (`404.php`, `403.php`, `500.php`) sharing `layout.php` — no app layout dependency; used by Router, AuthMiddleware and Database on failure                                                                                                                                                                                                                                       |
| `storage/.htaccess`                        | `Require all denied` — prevents direct web access to cache files                                                                                                                                                                                                                                                                                                                                       |
| `public/css/estilo.css`                    | Global styles + CSS palette variables (`--color-dark`, `--color-accent`)                                                                                                                                                                                                                                                                                                                               |
| `public/css/layout-protected.css`          | Full-height flex layout for protected pages                                                                                                                                                                                                                                                                                                                                                            |
| `public/js/users-table.js`                 | DataTables initialization — loaded only in `UserController::index()` via `pageScripts`                                                                                                                                                                                                                                                                                                                 |
| `public/js/users-delete.js`                | SweetAlert2 delete confirmation — loaded only in `UserController::index()` via `pageScripts`                                                                                                                                                                                                                                                                                                           |
| `public/js/activity-logs-table.js`         | DataTables initialization for audit log — server-side processing (`serverSide: true`); `ajax.data` callback pasa filtros del formulario al endpoint `/activity-logs/data`; `searching: false`; order by date DESC, pageLength 25; Buttons + ColVis conservados                                                                                                                                         |
| `public/js/sessions-revoke.js`             | SweetAlert2 confirmation for `.js-revoke-session` buttons — builds and submits a hidden POST form (`_csrf`, `session_id`, `btnrevoke`) to `/sessions/revoke` on confirm                                                                                                                                                                                                                               |
| `database/schema.sql`                      | Current DB schema — `users`, `password_resets`, `login_attempts`, `activity_logs`, `user_sessions` tables                                                                                                                                                                                                                                                                                              |
| `database/seeds.sql`                       | Sample users with bcrypt-hashed passwords                                                                                                                                                                                                                                                                                                                                                              |

### Session Variables

Set on login (only after successful `password_verify()`), required for all protected pages:

- `$_SESSION['user_id']` — user ID
- `$_SESSION['name']` — display name (first_name)
- `$_SESSION['is_admin']` — boolean, controls admin menu visibility
- `$_SESSION['last_activity']` — Unix timestamp; updated on every request; used by `AuthMiddleware::timeout()` to enforce inactivity expiry
- `$_SESSION['session_token']` — raw random token (`bin2hex(random_bytes(32))`) issued at login; SHA-256 hash is the row identity in `user_sessions`; used by `AuthMiddleware::session()` to check revocation and by `SessionController` to identify the current session (`is_current`)

Flash notifications (rendered by `views/layouts/messages.php`):

- `$_SESSION['message']` — toast message text
- `$_SESSION['icon']` — SweetAlert2 icon type (`success`, `error`, `warning`, `info`)

### Database Tables

- **users**: `id, first_name, last_name, email, username, password` (bcrypt), `is_admin (DEFAULT 0)`, `remember_token` (sha256 hash, nullable), `remember_token_expires` (datetime, nullable)
  - `email` and `username` have UNIQUE constraints; `remember_token` has an index (`idx_remember_token`)
- **password_resets**: `id, email, token, created_at, expires_at, used`
- **login_attempts**: `identifier` (PRIMARY KEY — varchar, normalized lowercase), `attempts`, `locked_until` (datetime, nullable), `last_attempt`
  - No surrogate id — `identifier` is the natural key; one row per username tracked
  - Only created for usernames that exist in `users`; deleted on successful login or password reset
- **activity_logs**: `id, user_id` (INT NULL, FK → `users.id` ON DELETE SET NULL), `event` (VARCHAR 50), `description` (VARCHAR 255), `ip_address` (VARCHAR 45, supports IPv6), `created_at` (DATETIME DEFAULT CURRENT_TIMESTAMP)
  - Indexes on `created_at` and `user_id`; `user_id` NULL for unauthenticated events (e.g. failed login)
  - Immutable — no update/delete endpoints; records preserved when user is deleted via ON DELETE SET NULL
- **user_sessions**: `id, user_id` (INT, FK → `users.id` ON DELETE CASCADE), `token_hash` (CHAR 64, SHA-256 of the session token, UNIQUE), `ip_address` (VARCHAR 45, nullable), `user_agent` (VARCHAR 255, nullable), `via_remember` (TINYINT default 0), `created_at`, `last_activity` (both DATETIME DEFAULT CURRENT_TIMESTAMP)
  - Indexes on `user_id` and `last_activity`; one row per active login (created on password login and on remember-me auto-login)
  - Deleted on logout (`deleteByTokenHash`), on explicit revoke, and cascades when the user is deleted

## Frontend / Assets

### Color Palette

Defined as CSS variables in `public/css/estilo.css`:

- `--color-accent: #04a1fc` — blue (buttons, gradient end, hover)
- `--color-dark: #142e3d` — navy (navbars, card headers, gradient start)

Utility classes: `.btn-app-primary` (accent button with hover), `.hero` (full-height gradient section), `.feature-icon` (circular icon container), `body.dashboard` (flex column layout for dashboard).

### FontAwesome

Use only `public/css/all.min.css` (CSS + webfonts in `public/webfonts/`). The JS version (`fontawesome.js`) was removed — do not re-add it.

### Bootstrap

`public/css/bootstrap.css` + `public/js/bootstrap.min.js` + `public/js/popper.min.js`. `bootstrap.bundle.js` and `bootstrap.js` were removed.

**Load order:** `bootstrap.css` must be loaded before `estilo.css` so that `.btn-app-primary` overrides Bootstrap's `.btn` defaults correctly.

### DataTables

Loaded **only** on pages that set `$useDataTables = true`. The flag loads the full bundle in `header.php` (CSS) and `footer.php` (JS) — DataTables core + Buttons 2.4.2 + ColVis. App-specific init scripts are passed via `$pageScripts`.

- `/users` — `users-table.js` (Buttons collection: Copy, PDF, Excel, CSV, Print + ColVis; `no-export` class excludes the Actions column) + `users-delete.js`
- `/activity-logs` — `activity-logs-table.js` (same Buttons collection; all columns exported)
- PDF exports use `customize` with project palette (`#142e3d` header), title, subtitle, date and per-page footer
- **Never add new per-page Buttons assets via `$pageScripts`** — they are already bundled inside the `$useDataTables` block in `footer.php`

### Per-page assets in layouts

`header.php` and `footer.php` support opt-in per-page assets:

- `$pageStyles` — array of CSS paths (relative to `APP_URL`) injected in `<head>` after DataTables CSS
- `$pageScripts` — array of JS paths (relative to `APP_URL`) injected in footer after DataTables JS
- `$useDataTables` — bool (default `false`) — enables DataTables CSS + JS bundle (core + Buttons 2.4.2 + ColVis); no additional variables needed for export buttons
- `$pageTitle` — string (default `'SecureAuth'`) — browser tab title
- `$favicon` — filename in `public/img/` (default `'usuario.png'`)
- `$bodyClass` — string added to `<body class="...">` (e.g. `'dashboard'`); also suppresses `mt-3` on `<main>`

### Cache

- Implementation: `libs/Cache/FileCache.php` + `app/Config/cache.php`
- Cached query: users listing (`App\Model\User::getAll()`) with key `users.all`
- TTL: `CACHE_TTL_USERS` (seconds)
- Enable/disable: `CACHE_ENABLED=true|false`
- Invalidation: on `create`, `update`, `delete`, `updatePassword` in `app/Model/User.php`
- Runtime files: `storage/cache/*.cache`
- If the cache directory is not writable, cache is disabled for the request and a warning is logged (no HTTP 500)

## Security Patterns

- Passwords hashed with `password_hash($pass, PASSWORD_DEFAULT)` (bcrypt)
- Session variables assigned only after successful `password_verify()` — never on failed login
- `session_regenerate_id(true)` called immediately after login to prevent session fixation
- **Secure session cookie**: `session_start_secure()` (defined in `app/Config/autoload.php`) applies `httponly=true`, `samesite=Strict`, `secure=true` (on HTTPS) before every `session_start()` — called on bootstrap, logout, and session timeout
- **HTTP Security Headers**: set in `public/.htaccess` via `mod_headers` — `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-XSS-Protection: 1; mode=block`, `Permissions-Policy`, `Content-Security-Policy` (`default-src 'self'`, `form-action 'self'`, `frame-ancestors 'none'`); HSTS commented out, ready for HTTPS
- **CSRF**: `App\Core\Csrf::token()` generates a `bin2hex(random_bytes(32))` token stored in `$_SESSION['csrf_token']`; all POST forms include `<input type="hidden" name="_csrf">` with this value; controllers call `$this->verifyCsrf($redirectPath)` which uses `hash_equals()` to compare — prevents timing attacks; token is **rotated** after each successful verification (`unset($_SESSION['csrf_token'])` in `Csrf::verify()`)
- **Logout is POST-only** — `/logout` route only accepts POST; `header.php` renders a `<form>` with CSRF token; `AuthController::logout()` calls `verifyCsrf()` before processing — prevents logout CSRF via `<img>` or link
- Reset tokens: `bin2hex(random_bytes(32))` raw token sent in email URL; SHA-256 hash stored in `password_resets.token` — 1-hour expiry, single-use (`used = 1` after consumption)
- **User enumeration prevention**: `AuthController::forgotPassword()` always returns the same generic message regardless of whether the email is registered — email is sent silently if the token was created
- All DB queries in `app/Model/User.php` use MySQLi prepared statements
- Email sanitized with `filter_var($email, FILTER_SANITIZE_EMAIL)` before DB queries
- SMTP uses STARTTLS encryption (port 587)
- **Self-hosted assets**: all JS and fonts in auth views use local files — no external CDN (eliminates supply-chain risk and `Referer` token leakage to third parties)
- Remember-me tokens: `bin2hex(random_bytes(32))` stored raw in cookie; SHA-256 hash stored in DB. Cookie is `HttpOnly`, `SameSite=Strict`, `Secure` on HTTPS. TTL controlled by `REMEMBER_ME_TTL`. Cleared on logout and on session expiry
- Session timeout: `AuthMiddleware::timeout()` called on every protected request; destroys session + clears remember cookie if `SESSION_TIMEOUT` seconds of inactivity exceeded
- User delete uses POST — not exploitable via `<img>` tags or link prefetch; `users-delete.js` dynamically creates and submits a form with the CSRF token after SweetAlert2 confirmation
- **Admin self-protection**: `UserController::delete()` blocks deletion of the authenticated admin's own account; `UserController::edit()` blocks removing one's own `is_admin` flag
- Flash messages rendered via `json_encode()` in `views/layouts/messages.php` — prevents XSS from user-controlled values (e.g. `first_name`) injected into the JavaScript SweetAlert2 call
- **Account lockout**: `LoginAttempt::registerFailure()` uses `INSERT ... ON DUPLICATE KEY UPDATE` with `attempts + 1` evaluated in SQL — atomic, no read-modify-write race; `locked_until` set when `attempts >= LOGIN_MAX_ATTEMPTS`; all date math done in MySQL (`NOW()`, `DATE_ADD`, `TIMESTAMPDIFF`) to avoid PHP/MySQL drift; only tracked for existing usernames (`Auth::userExists()` checked before registering)
- **Custom error pages**: `views/errors/404.php`, `403.php`, `500.php` — standalone (no DB/session dependency), rendered by Router (404), AuthMiddleware::admin() (403) and Database::getConnection() (500); DB errors logged via `error_log()`, never exposed to the browser
- **Active sessions / server-side revocation**: every login (password or remember-me) creates a `user_sessions` row keyed by SHA-256 hash of a random `session_token` (never stored raw); `AuthMiddleware::session()` runs on every protected request, checks `existsActive()` against `SESSION_TIMEOUT`, and force-logs-out (destroy session + redirect `/login`) if the row was deleted (i.e. revoked from another device); `SessionController` lets a user list/revoke their own sessions — `revoke()`/`revokeOthers()` always scope `DELETE` to `user_id` so one user cannot revoke another's session; gated by `ACTIVE_SESSIONS_ENABLED`

## Testing

### Stack

- **PHPUnit ^11.0** — integration tests against a real MySQL database (`login_test`)
- **71 tests total:** 14 in `tests/Unit/UserTest.php` (User model), 7 in `tests/Unit/LoginAttemptTest.php` (LoginAttempt model), 18 in `tests/Unit/ActivityLogTest.php` (ActivityLog model), 13 in `tests/Unit/UserSessionTest.php` (UserSession model), 19 in `tests/Integration/AuthTest.php` (Auth class)
- **CI:** `.github/workflows/tests.yml` — runs on push/PR to `master` with a MySQL 8.0 service

### Running tests locally

```bash
# Prerequisites: create login_test DB and import schema
mysql -u root -p -e "CREATE DATABASE login_test;"
mysql -u root -p login_test < database/schema_test.sql

# Create .env.testing with DB_DATABASE=login_test (never login)
# Then run:
composer test              # full suite
composer test:unit         # User model only
composer test:integration  # Auth class only
```

### Key conventions

- **Never mock `\mysqli`** — all tests hit real MySQL
- **Never load `app/Config/autoload.php`** in tests — it starts a session, reads cookies, and connects the DB singleton
- `tests/bootstrap.php` populates `$_ENV` via `parse_ini_file('.env.testing')` before Composer autoload (so `config.php` picks up test vars when it runs as part of `autoload.files`)
- `tests/TestCase.php` creates a direct `\mysqli` connection — does NOT use `App\Config\Database` singleton
- Tables are truncated in `setUp()` per test; schema is applied once per process via a static flag
- **Safeguard:** `TestCase` throws if `DB_DATABASE === 'login'` to prevent running against production DB
- Timezone-sensitive date comparisons use `DATE_SUB(NOW(), INTERVAL X HOUR)` in SQL — never PHP-computed timestamps — to avoid PHP/MySQL timezone drift
- `CACHE_ENABLED=false` in `.env.testing` and forced via `phpunit.xml` `<env>` — `appCache()` short-circuits before checking directory writability

### Files

| Path                              | Purpose                                                      |
| --------------------------------- | ------------------------------------------------------------ |
| `phpunit.xml`                     | PHPUnit 11 config — suites, bootstrap, env overrides         |
| `.env.testing`                    | Test environment vars (gitignored)                           |
| `database/schema_test.sql`        | Table-only schema for `login_test` (no `CREATE DATABASE`)    |
| `tests/bootstrap.php`             | Minimal bootstrap — `.env.testing` → `$_ENV`, then autoload  |
| `tests/TestCase.php`              | Abstract base — connection, schema, truncate, `createUser()` |
| `tests/Unit/UserTest.php`         | 14 tests for `App\Model\User`                                |
| `tests/Unit/LoginAttemptTest.php` | 7 tests for `App\Model\LoginAttempt`                         |
| `tests/Unit/ActivityLogTest.php`  | 18 tests for `App\Model\ActivityLog`                         |
| `tests/Unit/UserSessionTest.php`  | 13 tests for `App\Model\UserSession`                         |
| `tests/Integration/AuthTest.php`  | 19 tests for `App\Core\Auth`                                 |
| `.github/workflows/tests.yml`     | GitHub Actions CI workflow                                   |

## Notes

- PHPMailer is loaded via Composer autoload (`phpmailer/phpmailer`)
- All asset paths use the `APP_URL` constant via `<?= APP_URL ?>` short-tag syntax
- `App\Config\Database::getConnection()` is a singleton — the same `\mysqli` instance is reused across all controllers and models within a request
- `views/home/index.php` contains only content markup (no `<html>`/`<head>`/`<body>`) — dashboard with 4 stat-cards and a recent-events table; wrapped by the shared layout via `Controller::render(..., protected: true)`
- Error/success messages use unified session flash: `$_SESSION['message']` and `$_SESSION['icon']`. Rendered via `views/layouts/messages.php`. Never pass them via URL query params
- Auth views use `<button type="submit">` (not `<input type="submit">`); POST detection uses `isset($_POST['btnXXX'])` — not `!empty()` — since `<button>` without a `value` attribute submits an empty string
- User delete flow uses `.js-delete-user` buttons with `data-delete-url`, `data-name`, `data-username`; confirmation handled in `public/js/users-delete.js`
- `session_start_secure()` is called in `app/Config/autoload.php` — always use this helper instead of bare `session_start()` to ensure `httponly`/`samesite`/`secure` options are applied; `Auth::restoreFromCookie()` runs immediately after on every request
- `AuthMiddleware::session()` is currently only wired into `SessionController::guard()` (after `timeout()`, before `auth()`) — other protected controllers do not yet check session revocation; if that changes, keep `timeout() → session() → auth()` order since `session()` needs `$_SESSION['session_token']` to still be present
