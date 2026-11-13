# AGENTS.md

Custom PHP MVC auth system (SecureAuth). PHP >= 8.2, MySQL/MariaDB, no build step — Apache serves `public/` directly. Full file-by-file reference: **`CLAUDE.md`** (read it for architecture details); this file is the lean "things you'd get wrong" list.

## Run / serve

- Served at `http://localhost/Encriptacion_PHP/public` (path under Apache docroot via XAMPP `htdocs/`), not a subfolder served as webroot. Requires XAMPP Apache + MySQL up.
- No build/compile step, no npm. Do **not** scaffold `package.json`/`node_modules` into the project for tooling or e2e.
- All asset/URL paths use the `APP_URL` constant (`<?= APP_URL ?>`).

## Tests (high gotcha: real DB, no mocking)

- Do **not** run `phpunit` before requiring: create DB `login_test`, import `database/schema_test.sql`, and have a `.env.testing` with `DB_DATABASE=login_test`.
- `composer test` (all), `composer test:unit`, `composer test:integration` (phpunit.xml suites `Unit`/`Integration`).
- Integration tests hit **real MySQL** — never mock `\mysqli`. `tests/TestCase.php` throws if `DB_DATABASE === 'login'` (protects prod DB).
- **Never load `app/Config/autoload.php` in tests** — it starts a session, reads cookies, and connects the DB singleton. `tests/bootstrap.php` instead fills `$_ENV` from `.env.testing` via `parse_ini_file` BEFORE requiring `vendor/autoload.php` (because Composer's `autoload.files` runs `app/Config/config.php`). It also explicitly `require`s `cache.php` (not in `autoload.files`) and connects `\mysqli` directly.
- Timezone-sensitive date comparisons in SQL use `DATE_SUB(NOW(), INTERVAL X HOUR)` — never PHP-computed timestamps (PHP/MySQL timezone drift).
- Controller tests needing to capture `redirect()` (then `exit`) patterns: subclass the controller with a method that records the target instead of exiting — `tests/Integration/AuthGoogleCallbackTest.php` uses this (`CapturingAuthController` + `RedirectCaptured` exception), plus reflection to exercise private account-resolution helpers. Suite is 177 tests (107 unit + 70 integration). Login/logout are characterized in `AuthLoginTest`; it and `LoginServiceTest` force `LOGIN_LOCKOUT_ENABLED`/`LOGIN_IP_THROTTLE_ENABLED` through `$_ENV` because a local `.env.testing` and the Redis IP throttle (accumulates across runs) would break them.

## Namespaces / entrypoints

- PSR-4: `App\` → `app/`, `App\Lib\` → `libs/` (note: maps to `libs/`, not a subpath of `app/`), `Tests\` → `tests/`.
- Composer `autoload.files` auto-loads `app/Config/config.php` (loads `.env`, defines `env()` and `APP_URL`). `app/Config/autoload.php` is the runtime bootstrap (cache + DB + secure session + `Auth::restoreFromCookie()`) — do not treat it as config-only.
- Routes: `routes/web.php` — each URL maps to `[Controller::class, 'method']`; dispatch on HTTP method + path in `app/Core/Router.php`.

## Conventions that differ from defaults

- Mutations (delete, logout, revoke, session end) are **POST-only** and carry a CSRF token; `Csrf::verify()` uses `hash_equals()` and **rotates** the token after each success.
- POST detection uses `isset($_POST['btnXXX'])` — hidden `<button type="submit">` (no `value`) submits an empty string that `!empty()` would misread.
- Auth views (`views/auth/`) are standalone with their own `<head>`, load only `main.js`+`main2.js` (no jQuery/Bootstrap JS), and `sweetalert2.all.min.js` loads **only** when a flash message exists (in `views/layouts/messages.php`). Auth copy is English by design (Spanish dashboard is fine).
- Security headers are in `public/.htaccess` (mod_headers) — modify there.
- Cache: `CacheInterface` with `FileCache` (default), `NullCache` and `RedisCache` (`CACHE_DRIVER=redis`, phpredis), keys `users.all` and `activity_log.*`, with Activity Log query results using a 60-second TTL. `CACHE_ENABLED=false` in `.env.testing` and forced by `phpunit.xml`. Redis tests (`Tests\Support\InteractsWithRedis`) use a real server on DB 15 with prefix `encphp_test:`; they skip when it is absent unless `REDIS_REQUIRED=true`. Session tests use `#[RunInSeparateProcess]` + `#[PreserveGlobalState(false)]` (`session_start()` and `ini_set` are process-global). Sessions: `SESSION_DRIVER=redis` is configured in `app/Config/session.php` before `session_start_secure()` and falls back to files; the per-IP login throttle (`LoginThrottle`, `LOGIN_IP_THROTTLE_ENABLED`) is separate from the MySQL lockout (`LOGIN_MAX_ATTEMPTS`). `login_attempts`/`user_sessions` stay in MySQL on purpose (`docs/redis/por-que-mysql.md`). Keep the real `docs/redis/redis.conf` out of Git (gitignored).
- Flash notifications travel via `$_SESSION['message']`+`$_SESSION['icon']`, never URL params; write them with `App\Core\Flash` (`success/error/warning/info`) and read with `Flash::pull()`, never by touching the keys.
- **Middleware is declared, not called**: `routes/web.php` groups routes with `$router->group([...], fn)`; the order is `Timeout → ActiveSession → Authenticate [→ Admin]`. A protected route outside the `$authenticated` group skips `ActiveSessionMiddleware` (revoked sessions would keep working) — `RouterTest` fails if that happens. Controllers never call middleware.
- **One place per rule**: opening a session = `SessionIssuer::issue()` (password, Google, remember-me); remember cookie = `RememberCookie`; password login rules = `LoginService`; Google account lookup/link/create = `OAuthAccountResolver`; audit = `$this->audit->record()` (`AuditLog`, `ActivityLog::log()` no longer exists); password length = `PasswordPolicy` (8); admin self-protection = `UserPolicy`. Don't re-inline them in controllers.
- **QA tooling**: `composer stan` (PHPStan level 1 + `phpstan-baseline.neon`; raise one level per round and shrink the baseline) and `composer lint` (Pint psr12, `views/` excluded). Every PHP file in `app/`, `libs/`, `routes/` and `public/index.php` has `declare(strict_types=1)`; nullable DB columns must be cast before string functions.
- `.env.testing.example` is the single template for local and CI; comments use `;` and no quotes/parentheses.
- **Dependency pins (PHP 8.2 minimum, `dependabot.yml` ignores them)**: `phpunit/phpunit` stays on 11.x (13 requires PHP >= 8.4) and `laravel/pint` below 1.32 (needs PHP >= 8.3). Lift both together when the minimum PHP and the CI `php-version` go up. The CI writes `.env` from `.env.testing.example` because `config.php` requires `DB_*`/`APP_URL` when Composer's autoloader loads (before `tests/bootstrap.php`); phpdotenv rejects the `;` comments, so only `KEY=value` lines are copied.
- Old-input retention on validation errors (`UserController::create()`/`edit()`): non-password fields go in `$_SESSION['old']` before the redirect, read once and `unset()` on the next GET — same one-shot pattern as flash messages.
- **Session cookie `SameSite=Lax` (NOT Strict)** in `app/Config/autoload.php::session_start_secure()`. Strict would withhold the cookie on the cross-site top-level navigation Google's OAuth callback makes (`accounts.google.com` → `localhost`), so PHP starts a fresh session, the one-shot `state` no longer matches, and `googleCallback()` bounces back to `/login` with "Invalid OAuth state" — real bug hit on 2026-09-12. `Lax` still blocks cross-site POST (CSRF) while allowing the OAuth GET navigation. The `remember_me` cookie (AuthController) stays `Strict` — it is only set and consumed on same-site requests.
- **Google OAuth (login with Google)**: feature flag `OAUTH_GOOGLE_ENABLED` off by default; routes/button no-op with flash when off. Users with `password = NULL` must never reach `password_verify()` — reject via `User::rejectPasswordLogin()` (`app/Core/Auth::passwordLoginErrorMessage()`). Accounts with `oauth_provider` set can "set a password" from the profile (hybrid account); forgot/reset password refuses OAuth accounts. Tests never use real Google credentials.

## Browser / e2e

- Use the `playwright-cli` skill (globally installed, browsers in `~/.cache/ms-playwright`): `playwright-cli open --browser=firefox --headed http://localhost/Encriptacion_PHP/public/login`. System **Brave Origin** via CDP (`attach --cdp=http://localhost:9222`); system Firefox cannot be automated (Playwright needs its own build, already installed).

### Test lessons

- A fixture/trait that does `DROP`/`CREATE` on a fixed-name resource (DB, file) must suffix the name with a per-process id (`getmypid()`) when tests run in parallel (paratest).
- A test that resets a shared singleton/global (DB connection, config) must restore the test double in `tearDown`, not leave it `null`: with random order, later tests would try to reach the real resource. "Passes locally" proves nothing when local has the real resource; to reproduce CI, run without `.env`/`.env.testing` and with `--order-by=default`.
- `.env.testing` is read with `parse_ini_file`: a syntax error (e.g. `(` in a comment) makes it return `false` and `tests/bootstrap.php` silently loads nothing, so the suite falls back to `.env` (the real DB). The `login`/Redis-DB safeguards catch it; after editing `.env.testing`, check `php -r 'var_dump(parse_ini_file(".env.testing") !== false);'`.
