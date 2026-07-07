# PROMPTS.md — Encriptacion_PHP

> Plantillas de prompts para planificar features. Úsalas como base — adapta los bloques
> `[Tarea]` y `[Contexto]` a lo que necesites en cada sesión.
> El `CLAUDE.md` siempre debe estar disponible para el agente como contexto base.

---

## Cómo usar este archivo

Cada plantilla sigue la estructura de 5 ejes del prompt profesional:

| Eje                   | Pregunta          | Para qué sirve                                    |
| --------------------- | ----------------- | ------------------------------------------------- |
| **Rol**               | ¿Quién eres?      | Define el nivel y especialidad que asume la IA    |
| **Contexto**          | ¿Dónde estamos?   | El proyecto, stack y módulo activo                |
| **Tarea exacta**      | ¿Qué necesitas?   | Concreto y específico — nunca genérico            |
| **Restricciones**     | ¿Qué límites hay? | Convenciones del proyecto que NO se pueden romper |
| **Formato de salida** | ¿Cómo lo quieres? | Estructura del output esperado                    |

> **Regla de oro:** Cuanto más específico sea el bloque `[Tarea]`,
> menos correcciones necesitarás después.

**Reglas de uso:**

- **Siempre carga el CLAUDE.md** al inicio de la sesión si la herramienta no lo carga automáticamente.
- **Un prompt por subtarea.** Pedir "el módulo completo" en un solo prompt produce resultados genéricos.
- **Si el output no encaja**, no corrijas manualmente primero — ajusta `[Restricciones]` y repite.
- **El spec antes que el código.** Define qué debe hacer antes de pedir que lo implemente.
- **Guarda los prompts que funcionen bien** en este archivo como nuevas plantillas.
- **Los "Ejemplos reales" son curados, no un log histórico.** Se mantienen solo 3-4 que cubran patrones distintos (feature simple, testing, DataTables server-side, nueva tabla + middleware). Al agregar un ejemplo nuevo, si cubre el mismo patrón que uno existente, reemplázalo en vez de sumarlo — no documentar cada feature aquí, para eso está el CHANGELOG.

---

## Plantilla base (copia esto y rellena)

```
[Rol]
Actúa como desarrollador PHP Senior especializado en arquitectura MVC
y seguridad web (autenticación, hashing, sesiones).

[Contexto]
Proyecto: Encriptacion_PHP — PHP MVC con Composer (sin framework).
Stack: Bootstrap 4, DataTables, SweetAlert2, FontAwesome, MySQL/MariaDB, PHPMailer.
Servidor: XAMPP (Apache + MySQL) en http://localhost/Encriptacion_PHP/public
Módulo activo: _______________

[Tarea]
_______________

[Restricciones]
- Arquitectura MVC: App\Core\Router despacha a Controller::method(); rutas declaradas en routes/web.php
- Controladores en app/Controller/ extienden App\Core\Controller (render + redirect)
- Modelos en app/Model/ extienden App\Core\Model (protected \mysqli $db)
- Conexión DB: App\Config\Database::getConnection() — singleton, MySQLi con prepared statements siempre
- Variables de entorno via env() definido en app/Config/config.php
- Guards: AuthMiddleware::auth(), AuthMiddleware::admin(), AuthMiddleware::timeout() — llamar al inicio de cada método
- Flash notifications: $_SESSION['message'] + $_SESSION['icon'] renderizados en views/layouts/messages.php — nunca pasar mensajes por URL
- Vistas protegidas: Controller::render($view, $data, protected: true) — wrappea con header.php + footer.php
- Assets via APP_URL — nunca rutas relativas
- FontAwesome: solo public/css/all.min.css (CSS) — no re-agregar la versión JS
- Bootstrap: bootstrap.css (antes de estilo.css) + bootstrap.min.js + popper.min.js
- DataTables: pasar useDataTables: true + pageScripts en el render del controller que lo necesite
- No introducir librerías nuevas sin aprobación
- Passwords: password_hash() al guardar, password_verify() al validar — nunca MD5/SHA1

[Formato de salida]
_______________
```

---

## Plantilla 1 — Generar código nuevo (feature)

Usar cuando: implementar un requerimiento nuevo.

```
[Rol]
Actúa como desarrollador PHP Senior especializado en arquitectura MVC
y seguridad web (autenticación, hashing, sesiones).

[Contexto]
Proyecto: Encriptacion_PHP — PHP MVC con Composer (sin framework).
Stack: Bootstrap 4, DataTables, SweetAlert2, FontAwesome, MySQL/MariaDB, PHPMailer.
Módulo activo: [nombre del módulo — ej: auth, user, home]

Archivos relevantes:
- app/Controller/[Módulo]Controller.php   ← lógica del módulo
- app/Model/[Módulo].php                  ← queries con prepared statements
- views/[modulo]/[vista].php              ← HTML de la vista (solo contenido, sin <html>)
- public/js/[script].js                   ← JS del módulo (si aplica, pasado via pageScripts)
- routes/web.php                          ← registro de rutas GET/POST

[Tarea]
Implementar [nombre exacto del requerimiento].

Descripción: [criterios de aceptación]

[Restricciones]
- Métodos de controlador: manejan GET (renderizar vista) y POST (procesar formulario) en el mismo método
- Toda query DB en el Model, nunca en el Controller
- Flash notifications con $_SESSION['message'] + $_SESSION['icon'] — nunca por URL
- Detección de POST: isset($_POST['btnXXX']) — no !empty() — porque <button> sin value envía string vacío
- Guards al inicio del método: AuthMiddleware::timeout() + AuthMiddleware::admin() o auth()
- CSRF: todos los formularios POST deben incluir `<input type="hidden" name="_csrf" value="<?= \App\Core\Csrf::token() ?>">` y el controller debe llamar `$this->verifyCsrf($redirectPath)` al inicio del bloque POST — el token **se rota tras cada verificación exitosa** (`Csrf::verify()` elimina el token de sesión, forzando regeneración en el siguiente `token()`)
- Invalidar caché en operaciones write: appCache()->delete('users.all') o el key correspondiente
- Vistas protegidas solo tienen contenido (sin <html>/<head>/<body>) — el layout lo pone render()
- No agregar comentarios obvios — solo donde el WHY no sea evidente
- No usar `session_start()` directamente — usar siempre `session_start_secure()` (definido en `app/Config/autoload.php`)
- No cargar assets desde CDN externos — usar siempre archivos self-hosted bajo `APP_URL`
- Logout es POST-only con CSRF — nunca agregar rutas GET para operaciones con side-effects
- Páginas de error: usar `views/errors/404.php`, `403.php`, `500.php` — no `die()` con texto plano ni `echo` del path interno

[Formato de salida]
Devuelve en este orden:
1. Lista de archivos que se crean o modifican
2. SQL si hay cambios en BD (ALTER TABLE o CREATE TABLE)
3. Código de cada archivo
4. Checklist de testing manual (casos exitosos + edge cases)
```

---

## Plantilla 2 — Debuggear un error

Usar cuando: algo no funciona y no está claro por qué.

```
[Rol]
Actúa como desarrollador PHP Senior especializado en debugging
de aplicaciones MVC, sesiones PHP y MySQL.

[Contexto]
Proyecto: Encriptacion_PHP — PHP MVC con Composer (sin framework).
Stack: PHP 8.2+, MySQLi, Bootstrap 4, SweetAlert2.
Archivo donde ocurre el error: [ruta completa]
Método/función afectada: [nombre]

[Tarea]
Tengo este error:
[pega el mensaje de error exacto o el comportamiento inesperado]

Código actual:
[pega el bloque de código relevante — no todo el archivo]

Lo que debería hacer:
[describe el comportamiento esperado]

Lo que intenté que no funciona:
[describe lo que ya probaste]

[Restricciones]
- No cambiar la arquitectura del archivo — solo corregir el problema específico
- Mantener naming conventions del proyecto (camelCase métodos, PascalCase clases)
- Si el fix toca más de un archivo, indicarlo antes de proponer código
- No cambiar prepared statements a queries directas como fix rápido

[Formato de salida]
1. Diagnóstico: causa raíz en 2-3 líneas
2. Fix: código corregido con comentario explicando el cambio
3. Por qué pasó: explicación breve para no repetirlo
```

---

## Plantilla 3 — Code review antes del merge

Usar cuando: antes de hacer merge, o cuando el código funciona pero algo "huele mal".

```
[Rol]
Actúa como Tech Lead PHP con experiencia en code review de sistemas MVC,
seguridad web (OWASP Top 10) y patrones de diseño.

[Contexto]
Proyecto: Encriptacion_PHP — PHP MVC con Composer.
Rama revisada: feature/[nombre]
Feature implementada: [descripción]

[Tarea]
Revisa el siguiente código antes del merge.

[pega el código o el diff]

[Restricciones]
Evalúa específicamente:
- Seguridad: SQL injection (¿prepared statements?), XSS (¿htmlspecialchars en output? ¿json_encode en contexto JS?),
  CSRF (¿campo _csrf en formularios POST? ¿verifyCsrf() al inicio del bloque POST?),
  session fixation (¿session_regenerate_id() tras login?), tokens (¿bin2hex(random_bytes(32))? ¿hash SHA-256 en DB?),
  cookies (¿HttpOnly + Secure + SameSite?)
- Arquitectura: queries solo en Model, lógica solo en Controller, guards al inicio de cada método
- Sesiones: flash messages via $_SESSION['message']+['icon'], nunca por URL params
- Assets: APP_URL usado, no rutas relativas; bootstrap.css antes de estilo.css
- Edge cases que podrían fallar en producción

[Formato de salida]
OK  - Lo que está bien (al menos 2 puntos)
OBS - Observaciones no críticas con sugerencia
FIX - Problemas a corregir antes del merge (con código corregido)
```

---

## Plantilla 4 — Consulta de arquitectura

Usar cuando: hay una decisión técnica importante antes de implementar.

```
[Rol]
Actúa como arquitecto de software PHP con experiencia en sistemas MVC
custom, seguridad de autenticación y diseño de base de datos.

[Contexto]
Proyecto: Encriptacion_PHP — PHP MVC con Composer (sin framework).
BD implementada: users (id, first_name, last_name, email, username, password, is_admin,
                        remember_token, remember_token_expires),
                 password_resets (id, email, token, created_at, expires_at, used).
Core: App\Core\Router, App\Core\Controller, App\Core\Model, App\Core\Auth
Middleware: App\Middleware\AuthMiddleware (auth, admin, timeout)

[Tarea]
Necesito decidir: [describe la decisión técnica]

Opciones que estoy considerando:
- Opción A: [describe]
- Opción B: [describe]

[Restricciones]
- No introducir frameworks (ni Laravel, ni Symfony, ni Slim)
- Mantener el Router en App\Core\Router y las rutas en routes/web.php
- Conexión DB via App\Config\Database::getConnection() — no crear conexiones adicionales
- Cualquier solución debe integrarse con el autoload de Composer (PSR-4 App\ → app/)
- Considerar impacto en el sistema de caché (libs/Cache/FileCache.php)

[Formato de salida]
1. Recomendación directa (cuál opción y por qué en 3 líneas)
2. Trade-offs de cada opción
3. Impacto en el resto del sistema
4. Primeros pasos concretos para implementar la opción recomendada
```

---

## Ejemplo real — Remember Me (implementado en v1.4.0)

> Ejemplo de prompt de feature bien estructurado.

```
[Rol]
Actúa como desarrollador PHP Senior especializado en seguridad de autenticación
y manejo de sesiones/cookies.

[Contexto]
Proyecto: Encriptacion_PHP — PHP MVC con Composer (sin framework).
Stack: PHP 8.2+, MySQLi, Bootstrap 4, SweetAlert2.
Módulo: auth (login).

BD relevante:
- users (id, first_name, last_name, email, username, password bcrypt, is_admin,
         remember_token VARCHAR(64) NULL, remember_token_expires DATETIME NULL)

[Tarea]
Implementar "Recuérdame" en el login.

Criterios de aceptación:
- Checkbox "Recuérdame" en el formulario de login
- Si marcado: genera token con bin2hex(random_bytes(32)), guarda hash SHA-256 en users,
  emite cookie 'remember_me' (HttpOnly, Secure, SameSite=Strict, 30 días)
- En cada request sin sesión activa: app/Config/autoload.php valida cookie y restaura sesión
- Logout: limpia remember_token en DB y elimina la cookie
- Token se regenera en cada login con "Recuérdame"

[Restricciones]
- Token almacenado como hash('sha256') — nunca el token en claro
- Cookie con HttpOnly=true, Secure=true, SameSite=Strict
- Validación en App\Core\Auth::restoreFromCookie() — llamado desde autoload.php
- Limpiar cookie en AuthMiddleware::timeout() cuando la sesión expira

[Formato de salida]
1. SQL: ALTER TABLE para las columnas nuevas
2. app/Core/Auth.php — métodos de token
3. app/Config/autoload.php — llamada a restoreFromCookie()
4. app/Controller/AuthController.php — cambios en login() y logout()
5. views/auth/login.php — checkbox en el formulario
6. app/Model/User.php — métodos nuevos
7. Checklist de testing manual
```

---

---

## Plantilla 5 — Diseñar tests de integración PHPUnit

Usar cuando: añadir tests a un módulo nuevo o ampliar la suite existente.

```
[Rol]
Actúa como desarrollador PHP Senior especializado en testing de integración con PHPUnit,
arquitectura MVC y seguridad web (autenticación, hashing, sesiones).

[Contexto]
Proyecto: Encriptacion_PHP — PHP MVC con Composer (sin framework).
Stack: PHP 8.2+, MySQLi, PHPUnit ^11.0.
DB de prueba: login_test (nunca login).

Infraestructura de tests ya existente:
- tests/bootstrap.php   — carga .env.testing antes del autoload, nunca session_start()
- tests/TestCase.php    — conexión mysqli directa, truncate por test, createUser() helper
- phpunit.xml           — suites Unit + Integration, failOnWarning=true, random order
- database/schema_test.sql — schema sin CREATE DATABASE / USE

Clases ya cubiertas: App\Model\User (tests/Unit/UserTest.php),
                     App\Core\Auth (tests/Integration/AuthTest.php)

[Tarea]
Diseña los tests de integración para [Clase/módulo].

Métodos a cubrir: [lista]

[Restricciones]
- NO mocks de mysqli — conexión real a login_test
- NO cargar app/Config/autoload.php
- Extender Tests\TestCase, no PHPUnit\Framework\TestCase directamente
- PHPUnit 11: usar #[Test] y #[DataProvider] (atributos PHP 8, no anotaciones @)
- Comparaciones de fechas contra MySQL: usar DATE_SUB(NOW(), INTERVAL X HOUR) — no timestamps PHP
- Cada test independiente: no depender del orden de ejecución
- Ubicar en tests/Unit/ si cubre una clase aislada, tests/Integration/ si orquesta varias

[Formato de salida]
1. Archivo tests/[Suite]/[Clase]Test.php completo
2. Casos cubiertos (tabla: método → escenario → aserción clave)
3. Edge cases que podrían fallar en producción
```

---

## Ejemplo real — Tests de Auth + User (implementado en v1.6.0)

> Prompt de infraestructura de tests bien estructurado — ver docs/plan-tests-integracion.md para el plan completo generado con Claude Opus.

```
[Rol]
Actúa como desarrollador PHP Senior especializado en testing de integración con PHPUnit,
arquitectura MVC y seguridad web.

[Contexto]
Proyecto: Encriptacion_PHP. PHPUnit ^11.0 en require-dev.
Clases: app/Core/Auth.php + app/Model/User.php
DB de prueba: login_test (MySQL real, no mocks).

[Tarea]
Diseña el plan COMPLETO de infraestructura de tests de integración:
bootstrap, phpunit.xml, .env.testing, TestCase base, tests de User y Auth,
y GitHub Actions workflow.

[Restricciones]
- NO mocks de mysqli, NO cargar autoload.php, NO usar Database singleton en tests
- Bootstrap: parse_ini_file(.env.testing) → $_ENV antes del autoload de Composer
- CACHE_ENABLED=false; salvaguarda si DB_DATABASE === 'login'
- PHPUnit 11 con atributos #[Test]

[Formato de salida]
Plan en 4 fases con objetivo, archivos, decisiones técnicas y código completo.
```

---

---

## Ejemplo real — Filtros server-side en Audit Log (implementado en v1.13.0)

> Prompt de feature con DataTables server-side processing, endpoint JSON y formulario de filtros colapsable.

```
[Rol]
Actúa como desarrollador PHP Senior especializado en arquitectura MVC
y seguridad web (autenticación, hashing, sesiones).

[Contexto]
Proyecto: Encriptacion_PHP — PHP MVC con Composer (sin framework).
Stack: Bootstrap 4, DataTables, SweetAlert2, FontAwesome, MySQL/MariaDB, PHPMailer.
Módulo activo: activity-log (audit log existente)

Archivos relevantes:
- app/Controller/ActivityLogController.php    ← index() con guard admin + render
- app/Model/ActivityLog.php                   ← getAll() client-side, constantes EVENT_*
- views/activity-log/index.php                ← tabla con DataTables client-side
- public/js/activity-logs-table.js            ← init DataTables para /activity-logs
- routes/web.php                              ← ruta GET /activity-logs

[Tarea]
Diseña el plan COMPLETO para migrar /activity-logs a DataTables server-side processing
con filtros por evento, usuario y rango de fechas.

Criterios de aceptación:
- Nuevo endpoint GET /activity-logs/data que devuelve JSON DataTables
  (draw, recordsTotal, recordsFiltered, data)
- Formulario de filtros colapsable (Bootstrap collapse): select evento, input username
  (match parcial), inputs date_from/date_to; badge warning cuando hay filtros activos
- DataTables serverSide:true; searching:false (el formulario propio reemplaza la búsqueda nativa)
- Botones export (Copy, PDF, Excel, CSV, Print) y ColVis conservados; exportan página visible
- Paginación manejada por DataTables; servidor provee solo la página actual

Métodos nuevos:
- ActivityLog::getAll(array $filters=[], ?int $limit=null, ?int $offset=null): array
- ActivityLog::getTotalCount(array $filters=[]): int

[Restricciones]
- Sin concatenación de strings en SQL — prepared statements + WHERE dinámico via buildWhere()
- event: allow-list de constantes EVENT_* — valores arbitrarios descartados
- Fechas: DateTime::createFromFormat('Y-m-d') + format() === input (validación estricta)
- username: trim() + cap 100 chars; los % del LIKE los añade el código
- length: allow-list [10, 25, 50, 100]; draw casteado a int
- XSS en JSON: htmlspecialchars() en cada celda antes de json_encode()
- Ruta /activity-logs/data registrada ANTES de /activity-logs en routes/web.php
- Guards timeout() + admin() en ambos métodos del controller
- Sin _csrf en el endpoint JSON (GET idempotente)
- Export exporta solo la página visible — limitación esperada, documentar

[Formato de salida]
Plan en fases atómicas: 1-Model, 2-Rutas+Controller, 3-Vista, 4-JS, 5-Tests, 6-Docs.
Para cada fase: objetivo, archivos, cambios técnicos, criterio de done.
Al final: queries SQL finales, consideraciones de seguridad, checklist de testing manual.
```

---

## Ejemplo real — Gestión de sesiones activas (implementado en v1.14.0)

> Prompt de feature para permitir a un usuario ver y revocar sus propias sesiones activas desde cualquier dispositivo.

```
[Rol]
Actúa como desarrollador PHP Senior especializado en arquitectura MVC
y seguridad web (autenticación, hashing, sesiones).

[Contexto]
Proyecto: Encriptacion_PHP — PHP MVC con Composer (sin framework).
Stack: Bootstrap 4, SweetAlert2, FontAwesome, MySQL/MariaDB.
Login soporta password + remember-me (cookie con hash SHA-256 en BD).

Archivos relevantes:
- app/Core/Auth.php                    ← login + restoreFromCookie() (remember-me)
- app/Controller/AuthController.php    ← login() y logout()
- app/Middleware/AuthMiddleware.php    ← auth(), admin(), timeout()
- routes/web.php                       ← rutas existentes

[Tarea]
Diseña el plan COMPLETO para una feature de "sesiones activas": cada login crea un
registro de sesión; el usuario puede verlas todas y revocar una o todas menos la actual;
una sesión revocada debe forzar el logout en su próxima petición.

Criterios de aceptación:
- Nueva tabla user_sessions (user_id, token_hash, ip, user_agent, via_remember,
  created_at, last_activity) — token_hash es SHA-256, el token crudo nunca se guarda en BD
- GET /sessions lista las sesiones del usuario autenticado, marcando la sesión actual
- POST /sessions/revoke revoca una sesión por id, SIEMPRE con WHERE user_id = sesión actual
- POST /sessions/revoke-others revoca todas menos la actual
- Middleware que corre en cada request protegido: si el hash de la sesión ya no existe
  en BD, destruye la sesión y redirige a /login
- Flag de entorno para poder desactivar la revocación sin perder el tracking

[Restricciones]
- Nunca guardar el token de sesión en texto plano — solo su hash SHA-256
- DELETE de revoke()/revokeOthers() siempre scoped a user_id — nunca confiar solo en session_id
- MySQLi prepared statements siempre
- Reusar el patrón de remember-me (hash en BD, valor crudo en cookie/sesión)
- No introducir librerías nuevas

[Formato de salida]
Plan en fases atómicas: 1-Migración, 2-Model, 3-Middleware, 4-Rutas+Controller,
5-Vista+JS, 6-Tests, 7-Docs.
Para cada fase: objetivo, archivos, cambios técnicos, criterio de done.
Al final: queries SQL finales, consideraciones de seguridad, checklist de testing manual.
```

---

_Última actualización: 2026-07-07 — v1.14.0_
_Mantener sincronizado con CLAUDE.md al agregar features nuevas._
