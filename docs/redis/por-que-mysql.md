# Por qué `login_attempts` y `user_sessions` se quedan en MySQL

Redis se usa para la caché, las sesiones PHP y el límite de intentos por IP. Estas dos tablas se evaluaron y **no se migran**.

## `login_attempts` (bloqueo por usuario)

- Ya es atómico (`INSERT ... ON DUPLICATE KEY UPDATE`) y el volumen es mínimo: no hay problema de rendimiento que resolver.
- Si Redis cae, el bloqueo fallaría abierto, es decir, fuerza bruta sin freno. Es una regresión de seguridad.
- `LoginAttempt::getLockedCount()` alimenta el dashboard; en Redis necesitaría `SCAN` o una estructura adicional.
- La semántica cambiaría: hoy los intentos no caducan por ventana y se borran con un login correcto o con un reset de contraseña.
- El límite por IP (`LoginThrottle`, `INCR` + `EXPIRE`) ya cubre el caso de uso natural de Redis y convive con este bloqueo.

## `user_sessions` (sesiones activas)

- Es la fuente de verdad de la revocación.
- Se lista por usuario con IP, user-agent y fechas, se revoca por id limitado a `user_id` y depende de la FK `ON DELETE CASCADE` al borrar usuarios.
- Con Redis caído solo habría dos opciones malas: fallar abierto (aceptar sesiones revocadas) o fallar cerrado (expulsar a todos). MySQL ya es una dependencia obligatoria.
- Cachear `existsActive()` tampoco sirve: retrasaría la revocación.
- Mejora futura posible, sin Redis: ejecutar `purgeExpired()` de forma programada.
