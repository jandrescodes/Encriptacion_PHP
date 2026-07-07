<div align="center">

# SecureAuth — PHP MVC Authentication System

[![Version](https://img.shields.io/badge/version-1.14.0-blue.svg?style=flat-square)](https://github.com/Jandres25/Encriptacion_PHP/releases/tag/1.14.0)
[![Tests](https://github.com/Jandres25/Encriptacion_PHP/actions/workflows/tests.yml/badge.svg)](https://github.com/Jandres25/Encriptacion_PHP/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/badge/PHP->=8.2-777BB4.svg?style=flat-square&logo=php)](https://php.net/)
[![PHPMailer](https://img.shields.io/badge/PHPMailer-^6.9-1F3B5F.svg?style=flat-square)](https://github.com/PHPMailer/PHPMailer)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE)

Custom PHP MVC authentication system built with Composer, a lightweight router, and role-based access control.

</div>

## Features

- Custom MVC architecture — `App\Core\Router`, abstract `Controller` and `Model`, PSR-4 autoloading via Composer
- Secure login with bcrypt password hashing (`password_hash()` / `password_verify()`)
- **CSRF protection** on all POST forms via `App\Core\Csrf` — `hash_equals()` token comparison
- **Session fixation prevention** — `session_regenerate_id(true)` on every successful login
- Persistent login via **Remember Me** — `HttpOnly` / `SameSite=Strict` cookie; token stored as SHA-256 hash in DB
- Automatic **session timeout** on inactivity with remember cookie cleanup
- Password recovery via email with expiring single-use tokens stored as SHA-256 hash (PHPMailer + STARTTLS)
- **User profile** — authenticated users can edit their name, email and username, or change their password, at `/profile`; each action has its own CSRF-protected form
- Admin user management — full CRUD with role-based access control (`AuthMiddleware`)
- `App\Config\Database` singleton — single `\mysqli` connection per request
- File-based cache for the users listing with automatic invalidation on writes
- **Account lockout** — automatic account lock after N failed login attempts; configurable threshold and duration via `.env`
- **HTTP Security Headers** — `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Content-Security-Policy` and more via `mod_headers` in `.htaccess`; HSTS ready for HTTPS
- **Secure session cookie** — `session_start_secure()` helper enforces `HttpOnly`, `SameSite=Strict` and conditional `Secure` flag on every session start
- **Custom error pages** — styled 404, 403 and 500 views matching the app's design; standalone (no DB dependency)
- **Audit log** — all security and admin events (logins, logouts, password changes, user CRUD) recorded in `activity_logs`; admin-only view at `/activity-logs` with DataTables server-side processing; filterable by event type, username (partial match), and date range via collapsible filter form
- **Dashboard with real metrics** — home page shows 4 live stat-cards (total users, successful logins today, failed logins today, locked accounts) and a Bootstrap table of the 5 most recent audit events; queries via `User::getTotalCount()`, `ActivityLog::getCountTodayByEvent()`, `ActivityLog::getRecentEvents()`, `LoginAttempt::getLockedCount()`
- **DataTables Buttons + ColVis** — export buttons (Copy, PDF, Excel, CSV, Print) and column visibility toggle on `/users` and `/activity-logs`; PDF/Excel with custom title, subtitle, date and footer; Actions column excluded from all exports; assets self-hosted (Buttons 2.4.2)
- **Active sessions management** — every login (password or remember-me) is tracked in `user_sessions`; users can view all their active sessions at `/sessions` (device/browser, IP, created, last activity) and revoke any of them individually or all-but-current; revoked sessions are force-logged-out on their next request via `AuthMiddleware::session()`; controlled by `ACTIVE_SESSIONS_ENABLED`
- **Integration test suite** — 71 PHPUnit tests against a real MySQL DB; CI via GitHub Actions
- SweetAlert2 toast notifications for all CRUD and authentication actions
- Per-page asset injection — `$pageStyles` / `$pageScripts` arrays in shared layouts
- Shared layout system — `header.php` / `footer.php` accept `$pageTitle`, `$favicon`, `$bodyClass`, `$useDataTables` (loads DataTables core + Buttons + ColVis when `true`)
- App version displayed in footer via `APP_VERSION` env var

## Requirements

- PHP >= 8.2
- MySQL / MariaDB
- Apache with `mod_rewrite` (XAMPP recommended)
- Composer
- Gmail account with an App Password (or any SMTP provider)

## Installation

1. Clone the repository:

```bash
git clone https://github.com/Jandres25/Encriptacion_PHP.git
cd Encriptacion_PHP
```

2. Install dependencies:

```bash
composer install
```

3. Copy and configure the environment file:

```bash
cp .env.example .env
```

Edit `.env` with your credentials:

```
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=
DB_DATABASE=login

SMTP_HOST=smtp.gmail.com
SMTP_USERNAME=your@gmail.com
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

4. Import the database schema:

```bash
mysql -u root -p < database/schema.sql
```

5. (Optional) Load sample data:

```bash
mysql -u root -p < database/seeds.sql
```

6. Place the project in your server's web root (e.g. `htdocs/` in XAMPP) and open `APP_URL` in your browser.

## Project Structure

```
├── app/
│   ├── Config/
│   │   ├── autoload.php       # Bootstrap: timezone, cache, DB, session, restoreFromCookie()
│   │   ├── cache.php          # Cache bootstrap + appCache() helper
│   │   ├── config.php         # Loads .env via phpdotenv; defines APP_URL + env()
│   │   └── database.php       # Database singleton — Database::getConnection()
│   ├── Controller/
│   │   ├── ActivityLogController.php # Audit log viewer — GET /activity-logs, admin only
│   │   ├── AuthController.php    # login, logout, forgotPassword, resetPassword
│   │   ├── HomeController.php    # Dashboard — applies timeout + auth middleware
│   │   ├── ProfileController.php # profile(), changePassword() — any authenticated user
│   │   ├── SessionController.php # Active sessions — index(), revoke(), revokeOthers()
│   │   └── UserController.php    # Full user CRUD — guarded by admin middleware
│   ├── Core/
│   │   ├── Auth.php            # Credential verify, remember-me tokens, password reset tokens
│   │   ├── Controller.php      # Abstract base — render(), redirect(), verifyCsrf()
│   │   ├── Csrf.php            # CSRF token generation and verification
│   │   ├── Model.php           # Abstract base — holds protected \mysqli $db
│   │   └── Router.php          # GET/POST route registration and dispatch
│   ├── Middleware/
│   │   └── AuthMiddleware.php  # Static guards: auth(), admin(), timeout(), session()
│   ├── Model/
│   │   ├── ActivityLog.php     # Audit log — log(), logTo(), getAll(); event constants
│   │   ├── LoginAttempt.php    # Account lockout — atomic insert/update, lock check, clear
│   │   ├── User.php            # All DB queries via MySQLi prepared statements
│   │   └── UserSession.php     # Active sessions — create, touch, existsActive, revoke, revokeAllExcept
│   └── Service/
│       └── MailerService.php   # PHPMailer encapsulation — SMTP via STARTTLS
├── database/
│   ├── schema.sql              # Table definitions (users, password_resets, login_attempts, activity_logs, user_sessions)
│   ├── schema_test.sql         # Table-only schema for test DB (no CREATE DATABASE)
│   └── seeds.sql               # Sample data with bcrypt-hashed passwords
├── libs/
│   └── Cache/                  # File-based cache implementation
├── public/
│   ├── css/                    # bootstrap.css, estilo.css, all.min.css, layout-protected.css
│   ├── DataTables/             # DataTables JS bundle + Bootstrap 4 skin
│   ├── img/                    # Images and icons
│   ├── js/                     # jQuery, Bootstrap JS, Popper, SweetAlert2, users-*.js, activity-logs-table.js, sessions-revoke.js
│   ├── webfonts/               # FontAwesome webfonts
│   ├── .htaccess               # Apache rewrite rules for clean URLs
│   └── index.php               # Front controller
├── routes/
│   └── web.php                 # All route definitions
├── storage/
│   ├── .htaccess               # Require all denied — blocks direct web access to cache files
│   └── cache/                  # Runtime cache files (*.cache)
├── views/
│   ├── auth/                   # login, forgot_password, reset_password (standalone, self-hosted assets)
│   ├── errors/                 # 404.php, 403.php, 500.php + layout.php (standalone, no DB dependency)
│   ├── home/                   # index.php — dashboard content (wrapped by shared layout)
│   ├── layouts/                # header.php, footer.php, messages.php
│   ├── activity-log/           # index.php — audit log table (admin only)
│   ├── profile/                # index.php — unified profile + change password view
│   ├── session/                # index.php — active sessions table + revoke actions
│   └── user/                   # index, create, edit (wrapped by shared layout)
├── tests/
│   ├── bootstrap.php           # Test bootstrap — loads .env.testing, never starts session
│   ├── TestCase.php            # Abstract base — DB connection, truncate, createUser()
│   ├── Unit/
│   │   ├── ActivityLogTest.php # 18 tests for App\Model\ActivityLog
│   │   ├── LoginAttemptTest.php # 7 tests for App\Model\LoginAttempt
│   │   ├── UserSessionTest.php # 13 tests for App\Model\UserSession
│   │   └── UserTest.php        # 14 tests for App\Model\User
│   └── Integration/
│       └── AuthTest.php        # 19 integration tests for App\Core\Auth
├── .env.example                # Environment variable template
├── phpunit.xml                 # PHPUnit 11 configuration
└── composer.json               # Composer dependencies and PSR-4 autoload
```

## Usage

1. Open `http://localhost/Encriptacion_PHP/public/` in your browser
2. Log in with a seeded user (e.g. username `Admin`, password `Admin1234`)
3. Click your username in the nav to access your **profile** — edit info or change password
4. Visit `/sessions` to view your active sessions and revoke any device individually or all-but-current
5. Admin users (`is_admin = 1`) see the **Users** and **Activity Log** links in the nav → full CRUD and audit history
6. To recover a password, click "Forgot your password?" on the login page

## URL Routing

All routes are declared in `routes/web.php` and dispatched by `App\Core\Router`:

| URL                         | Controller method                     |
| --------------------------- | ------------------------------------- |
| `/`                         | `HomeController::index()`             |
| `/login`                    | `AuthController::login()`             |
| `POST /logout`              | `AuthController::logout()`            |
| `/forgot-password`          | `AuthController::forgotPassword()`    |
| `/reset-password?token=...` | `AuthController::resetPassword()`     |
| `/profile`                  | `ProfileController::profile()`        |
| `POST /profile/password`    | `ProfileController::changePassword()` |
| `/users`                    | `UserController::index()`             |
| `/users/create`             | `UserController::create()`            |
| `/users/edit?id=X`          | `UserController::edit()`              |
| `POST /users/delete`        | `UserController::delete()`            |
| `/activity-logs`            | `ActivityLogController::index()`      |
| `/activity-logs/data`       | `ActivityLogController::data()`       |
| `/sessions`                 | `SessionController::index()`          |
| `POST /sessions/revoke`     | `SessionController::revoke()`         |
| `POST /sessions/revoke-others` | `SessionController::revokeOthers()` |

## Security

- Passwords hashed with bcrypt (`PASSWORD_DEFAULT`)
- Session set only after successful `password_verify()`; `session_regenerate_id(true)` called immediately after to prevent session fixation
- **Secure session cookie** — `session_start_secure()` helper enforces `HttpOnly`, `SameSite=Strict`, and `Secure` (on HTTPS) on every session start — including after logout and session timeout
- **HTTP Security Headers** — `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Content-Security-Policy`, `Permissions-Policy` set in `public/.htaccess` via `mod_headers`; HSTS commented out, ready for HTTPS
- **CSRF tokens** on all POST forms — generated via `App\Core\Csrf::token()`, validated with `hash_equals()` in every controller; **token rotated** after each successful verification
- **Logout is POST-only** — protected by CSRF token; prevents logout CSRF via `<img>` or link
- Reset tokens: 256-bit (`bin2hex(random_bytes(32))`), 1-hour expiry, single-use, stored as SHA-256 hash in DB
- **User enumeration prevention** — `forgot-password` always returns the same generic response regardless of whether the email is registered
- All DB queries via MySQLi prepared statements
- **Self-hosted assets** — no external CDN in any view; eliminates supply-chain risk and `Referer` header token leakage
- Email validated with `filter_var()` before DB lookup
- SMTP with STARTTLS (port 587)
- Remember-me: raw token in cookie, SHA-256 hash in DB — cookie is `HttpOnly`, `SameSite=Strict`, `Secure` on HTTPS
- Session timeout enforced on every protected request; clears remember cookie to prevent silent re-login
- User delete requires POST — not exploitable via `<img>` or link prefetch
- **Admin self-protection** — admins cannot delete their own account or remove their own `is_admin` flag
- **Account lockout** — 5 consecutive failed logins lock the account for 15 min (configurable); only tracked for existing usernames; lockout cleared on successful login or password reset
- **Custom error pages** — 404, 403, 500 views are standalone (no DB/session dependency); DB errors logged via `error_log()`, never exposed to the browser
- **Audit log** — `ActivityLog::log()` wrapped in try/catch so a logging failure never aborts the main flow; IP from `$_SERVER['REMOTE_ADDR']` only; records preserved via FK `ON DELETE SET NULL` when user is deleted
- **Active sessions** — every login stores only the SHA-256 hash of a random session token in `user_sessions` (never the raw token); `AuthMiddleware::session()` checks the hash on protected requests and force-logs-out the browser if the row was revoked; revoke/revoke-others queries are always scoped to `user_id`, so one user cannot terminate another's session

## Cache

- Cached endpoint: `/users` listing (`App\Model\User::getAll()`)
- Cache key: `users.all`
- Invalidation: on create, edit, delete, and password update
- Controls: `CACHE_ENABLED=true|false`, `CACHE_TTL_USERS=<seconds>`
- Storage: `storage/cache/*.cache`
- If the directory is not writable, cache is disabled for the request and a warning is logged (no HTTP 500)

## Testing

The project includes an integration test suite (PHPUnit 11) that runs against a real MySQL database.

### Local setup

```bash
# 1. Create the test database
mysql -u root -p -e "CREATE DATABASE login_test;"
mysql -u root -p login_test < database/schema_test.sql

# 2. Copy and configure the test environment
cp .env.testing.example .env.testing   # or create it manually from .env.testing section in docs
# Set DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE=login_test

# 3. Run all tests
composer test

# Run by suite
composer test:unit         # App\Model\User + App\Model\LoginAttempt + App\Model\ActivityLog + App\Model\UserSession — 52 tests
composer test:integration  # App\Core\Auth — 19 tests
```

### CI

Tests run automatically on every push and PR to `master` via GitHub Actions (`.github/workflows/tests.yml`).

## Contributing

1. Fork the project
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes following [Conventional Commits](https://www.conventionalcommits.org/)
4. Push and open a Pull Request

<div align="center">

## License

MIT License — see the `LICENSE` file for details.

---

Jandres25 — jandrespb4@gmail.com

[https://github.com/Jandres25/Encriptacion_PHP](https://github.com/Jandres25/Encriptacion_PHP)

</div>
