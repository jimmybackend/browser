# Tareas del proyecto

## Fase 1: Diagnóstico

- [x] Revisar estructura del repositorio.
- [x] Identificar versión de PHP (>=8.3 en `composer.json`).
- [x] Identificar si usa Composer.
- [x] Identificar framework o arquitectura (MVC propio).
- [x] Identificar punto de entrada de la aplicación (`public/index.php`, `bin/browser`).
- [x] Identificar configuración requerida (`.env.example`, `config/*`).
- [x] Identificar dependencias (`phpdotenv`, `phpunit`).
- [x] Identificar base de datos requerida (MySQL/MariaDB con migraciones SQL).
- [x] Identificar archivos sensibles (no se detectaron `.env` reales ni llaves por nombre).
- [x] Crear reporte inicial de estado (documentado en `docs/SPEC.md`).

### Hallazgos técnicos (diagnóstico real)

- [x] Script `scripts/validate.sh` actualizado y ejecutado con fallback seguro para PHPUnit con configuración explícita.
- [x] Lint PHP completo pasa sin errores de sintaxis.
- [x] `composer validate` pasa.
- [x] Existe workflow de GitHub Actions para validación (`push`, `pull_request`, `workflow_dispatch`) con PHP 8.3 y `bash scripts/validate.sh`.
- [x] Existe `.env.example` y no hay `.env` comprometido.
- [x] Hay Dockerfile, docker-compose y nginx config para entorno contenedorizado.
- [x] Rutas web definidas manualmente en `public/index.php` (sin framework tipo Laravel/Symfony).

### Pendientes críticos detectados

- [ ] Generar y versionar `composer.lock` para builds reproducibles (bloqueado por conectividad a Packagist; `composer update --no-interaction --no-progress` falló el 2026-05-07 con `curl error 56` y `CONNECT tunnel failed, response 403`).
- [ ] Agregar pruebas funcionales reales más allá de `tests/BootstrapTest.php` (auth, rutas protegidas, marketing).
- [x] Agregar pruebas base de estructura de proyecto (rutas/archivos críticos y checks de `composer.json` sin dependencia de DB).
- [ ] Incorporar/anclar PHPStan y configuración (`phpstan.neon`) si será obligatorio.
- [ ] Validación real de base de datos (migraciones/seed contra instancia ejecutándose) en entorno verificable.
- [ ] Fortalecer escaneo de secretos por contenido (no solo por nombres de archivo).

## Fase 2: Instalación

- [x] Crear o actualizar README con pasos verificables.
- [x] Existe `.env.example`.
- [x] Documentar variables de entorno obligatorias y comandos principales.
- [x] Documentar instalación local (Docker y sin Docker).
- [x] Documentar comandos de validación y posibles fallos de red.
- [ ] Verificar conexión a base de datos en ejecución real de app.

## Fase 5: Pruebas y validación

- [x] Existe validación de sintaxis PHP (`scripts/validate.sh`).
- [x] Ajustar ejecución de PHPUnit para no correr nunca sin configuración explícita o ruta de tests.
- [x] Agregar pruebas baseline de estructura (sin DB) en `tests/BootstrapTest.php`.
- [ ] Agregar pruebas funcionales mínimas si el proyecto tiene estructura para pruebas.
- [ ] Agregar PHPStan si aplica.
- [x] Workflow de GitHub Actions presente y alineado a script de validación.
- [ ] Documentar pruebas manuales por módulo.


## Fase 6: Persistencia de sesiones autenticadas

- [x] Crear modelo `app/Models/UserSession.php` para persistir sesiones en la tabla real `user_sessions`.
- [x] Registrar sesión persistida al hacer login, usando `session_token_hash = hash('sha256', session_id())`.
- [x] Registrar sesión persistida después de register + autenticación.
- [x] Revocar sesión persistida en logout antes de destruir la sesión PHP.
- [x] Guardar `ip_address` en binario con `inet_pton()` cuando la IP sea válida.
- [x] Limitar `user_agent` a 500 caracteres.
- [x] Definir `expires_at` con expiración razonable por defecto (+2 horas).
- [x] Agregar pruebas PHPUnit mínimas sin dependencia de MySQL real para `UserSession`.


## Fase 7: Enforce de sesión persistida en rutas protegidas

- [x] Actualizar `AuthMiddleware` para exigir `user_id` en sesión PHP y `session_id()` no vacío antes de permitir acceso.
- [x] Validar sesión persistida con `UserSession::isActive(session_id())` en rutas protegidas.
- [x] Si la sesión persistida está revocada o expirada, cerrar sesión PHP y redirigir a `/login`.
- [x] Si falla la validación por error de DB, aplicar fail-closed: cerrar sesión, redirigir y registrar error genérico con `error_log`.
- [x] Agregar pruebas PHPUnit mínimas sin DB real para cubrir el comportamiento esperado del middleware.

## Fase 8: Gestión de sesiones activas por usuario

- [x] Extender `UserSession` con `listForUser`, `revokeForUserById` y `revokeOtherSessions` usando PDO + prepared statements.
- [x] Crear `SecurityController` con acciones para listar sesiones, revocar sesión específica y cerrar otras sesiones.
- [x] Crear vista `app/Views/security/sessions.php` con listado seguro (sin exponer `session_token_hash`) y acciones con CSRF.
- [x] Agregar rutas protegidas para `/security/sessions`, `/security/sessions/revoke` y `/security/sessions/revoke-others`.
- [x] Agregar acceso desde perfil a la pantalla de seguridad de sesiones.
- [x] Mantener comportamiento seguro: si se revoca la sesión actual, se cierra sesión y redirige a `/login`.
- [x] Agregar pruebas PHPUnit mínimas de existencia y validaciones de no exposición directa de hash.
- [x] Confirmar que no se modifica la estructura de `user_sessions` ni migraciones.
