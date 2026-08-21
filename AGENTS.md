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

## Namespaces / entrypoints

- PSR-4: `App\` → `app/`, `App\Lib\` → `libs/` (note: maps to `libs/`, not a subpath of `app/`), `Tests\` → `tests/`.
- Composer `autoload.files` auto-loads `app/Config/config.php` (loads `.env`, defines `env()` and `APP_URL`). `app/Config/autoload.php` is the runtime bootstrap (cache + DB + secure session + `Auth::restoreFromCookie()`) — do not treat it as config-only.
- Routes: `routes/web.php` — each URL maps to `[Controller::class, 'method']`; dispatch on HTTP method + path in `app/Core/Router.php`.

## Conventions that differ from defaults

- Mutations (delete, logout, revoke, session end) are **POST-only** and carry a CSRF token; `Csrf::verify()` uses `hash_equals()` and **rotates** the token after each success.
- POST detection uses `isset($_POST['btnXXX'])` — hidden `<button type="submit">` (no `value`) submits an empty string that `!empty()` would misread.
- Auth views (`views/auth/`) are standalone with their own `<head>`, load only `main.js`+`main2.js` (no jQuery/Bootstrap JS), and `sweetalert2.all.min.js` loads **only** when a flash message exists (in `views/layouts/messages.php`). Auth copy is English by design (Spanish dashboard is fine).
- Security headers are in `public/.htaccess` (mod_headers) — modify there.
- Cache: file-backed (`libs/Cache/FileCache.php`), key `users.all`, `CACHE_ENABLED=false` in `.env.testing` and forced by `phpunit.xml`.
- Flash notifications travel via `$_SESSION['message']`+`$_SESSION['icon']`, never URL params.
- Old-input retention on validation errors (`UserController::create()`/`edit()`): non-password fields go in `$_SESSION['old']` before the redirect, read once and `unset()` on the next GET — same one-shot pattern as flash messages.

## Browser / e2e

- Use the `playwright-cli` skill (globally installed, browsers in `~/.cache/ms-playwright`): `playwright-cli open --browser=firefox --headed http://localhost/Encriptacion_PHP/public/login`. System **Brave Origin** via CDP (`attach --cdp=http://localhost:9222`); system Firefox cannot be automated (Playwright needs its own build, already installed).
