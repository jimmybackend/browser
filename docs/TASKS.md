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

- [x] Script `scripts/validate.sh` endurecido: en CI no permite omitir PHPUnit cuando existe `composer.json` (requiere `composer install`, `vendor/bin/phpunit` y `phpunit.xml.dist`).
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
- [x] Ajustar ejecución de PHPUnit en CI para fallar si no existe `vendor/bin/phpunit` o `phpunit.xml.dist` tras `composer install`.
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

## Fase 9: Auditoría de autenticación y sesiones

- [x] Crear modelo `app/Models/AuditLog.php` para insertar eventos en `audit_logs` usando PDO + prepared statements.
- [x] Registrar `register_success`, `login_success`, `login_failed` y `logout` desde `AuthController`.
- [x] Registrar `session_revoked` y `other_sessions_revoked` desde `SecurityController`.
- [x] Registrar `persisted_session_invalid` y `persisted_session_validation_error` desde `AuthMiddleware`.
- [x] Sanitizar metadata para no persistir `password`, `_csrf_token`, `session_id` ni `session_token_hash`.
- [x] Mantener resiliencia: errores de auditoría no bloquean login/logout y solo registran `error_log` genérico.
- [x] Agregar pruebas PHPUnit mínimas sin DB real para `AuditLog`.
- [x] Confirmar que no se modifica la estructura de `audit_logs` ni migraciones.


## Fase 10: Consulta protegida de auditoría

- [x] Revisar esquema real en `database/migrations/001_initial_schema.sql` y SQL complementario del repositorio antes de programar.
- [x] Reutilizar tabla real `audit_logs` sin cambios de estructura ni migraciones.
- [x] Extender `AuditLog` con listado filtrado seguro (`listRecent`), conteo (`countByFilters`), decodificación de IP y sanitización de metadata para salida.
- [x] Crear `AuditLogController` con ruta protegida para admins en `GET /admin/audit-logs`.
- [x] Crear vista `app/Views/audit/index.php` con filtros GET (`action`, `user_id`, `date_from`, `date_to`) y salida escapada.
- [x] Evitar exposición de `session_id`, `session_token_hash`, contraseñas y tokens CSRF en metadata mostrada.
- [x] Agregar pruebas PHPUnit mínimas sin DB real para existencia de controlador/vista y checks de no exposición de labels sensibles.
- [x] Confirmar que no se modificó la estructura de `audit_logs`.

## Fase 11: Hardening de normalización de URLs del crawler

- [x] Revisar esquema SQL real previo (`database/migrations/001_initial_schema.sql`, `database/migrations/004_crawl_urls.sql`) sin cambios de BD.
- [x] Corregir `CrawlerService::normalizeUrl()` para resolución segura de relativas (`/`, `?query`, rutas hermanas, carpetas).
- [x] Mantener rechazo de esquemas inseguros (`javascript:`, `data:`, `file:`, `ftp:`) y fragmentos `#`.
- [x] Evitar URLs corruptas observadas en datos reales (`pagina.php/otra.php`, `?lang=es/signup.php`).
- [x] Normalizar paths/queries con espacios para evitar errores CURL por URL malformada, preservando segmentos ya codificados.
- [x] Asegurar null-safety en `parseHtml()` para `title`, `description` y `language` antes de `mb_substr()`.
- [x] Agregar pruebas PHPUnit unitarias sin red/DB para cubrir casos reales de normalización y null-safety.


## Fase 12: Protección de rama `main` (documentación y checklist)

- [x] Crear documentación `docs/BRANCH_PROTECTION.md` con reglas recomendadas para proteger `main`.
- [x] Definir paso a paso manual en GitHub UI (`Settings -> Branches -> Add branch ruleset/rule -> main`).
- [x] Dejar explícito que Codex crea PRs y el merge lo realiza Jimmy manualmente.
- [x] Dejar explícito que no se habilita auto-merge todavía.
- [x] Confirmar en documentación que producción y base de datos siguen bajo aprobación humana.
- [x] Agregar checklist previo y posterior a activación de branch protection.
- [x] Confirmar que esta fase no introduce cambios funcionales de código, migraciones ni credenciales.

## Fase 13: Plantillas de Issues/PR para flujo seguro con Codex

- [x] Crear `.github/pull_request_template.md` con checklist de seguridad, alcance, pruebas, riesgos y validación manual.
- [x] Crear plantillas de issues en `.github/ISSUE_TEMPLATE/` para bug, feature, tarea Codex y tarea sensible de base de datos.
- [x] Asegurar en plantillas confirmaciones explícitas: sin auto-merge, sin reutilizar ramas y sin actualizar PRs viejos.
- [x] Crear `docs/CODEX_WORKFLOW.md` con el flujo `1 tarea = 1 chat nuevo = 1 PR = 1 merge manual`.
- [x] Referenciar aprobación humana obligatoria para producción y base de datos.
- [x] Confirmar que esta fase no modifica código funcional, migraciones, `.env` ni configuración Nginx.
