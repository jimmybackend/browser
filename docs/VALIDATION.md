# Validación del proyecto

## Validación local (rápida)

Ejecutar:

```bash
bash scripts/validate.sh
```

El script valida, en este orden:

1. Sintaxis PHP (`php -l` en archivos del repo, excluyendo `vendor`/`node_modules`).
2. Composer (si existe `composer.json`): requiere Composer, ejecuta `composer validate` y `composer install`; si falla cualquier paso, termina con error.
3. PHPUnit en modo estricto de CI (si existe `composer.json`):
   - exige `vendor/bin/phpunit` después de `composer install`;
   - exige `phpunit.xml.dist`;
   - ejecuta `vendor/bin/phpunit --configuration phpunit.xml.dist`;
   - si algo falta, falla con exit code != 0 (sin skips silenciosos).
4. PHPStan (`vendor/bin/phpstan analyse`) solo si está instalado.
5. Escaneo básico de nombres de archivos sensibles (`.env`, `*.pem`, `*.key`, etc.).

## Validación local (manual)

Si no se puede usar el script, ejecutar lo que aplique:

### Sintaxis PHP

```bash
find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l
```

### Composer

```bash
composer validate --no-check-publish
composer install --no-interaction --prefer-dist --no-progress
```

### PHPUnit (con configuración explícita)

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist
```

### PHPStan

```bash
vendor/bin/phpstan analyse
```

## Cobertura baseline de PHPUnit (sin DB)

La suite actual incluye pruebas mínimas de estructura en `tests/BootstrapTest.php` para validar que existan:

- `app/`
- `public/index.php`
- `bin/browser`
- `database/migrations/`
- `.env.example`
- `AGENTS.md`
- `phpunit.xml.dist`
- `scripts/validate.sh`

También valida que `composer.json` mantenga:

- `autoload.psr-4.Browser\\` apuntando a `app/`
- script `test` con `vendor/bin/phpunit --configuration phpunit.xml.dist`

Estas pruebas no requieren MySQL ni conexión externa.

## Validación en CI

Workflow: `.github/workflows/ci.yml`

- Triggers: `push`, `pull_request`, `workflow_dispatch`.
- Entorno: Ubuntu + PHP 8.3.
- Extensiones: `mbstring`, `intl`, `pdo`, `pdo_mysql`.
- Paso principal: `bash scripts/validate.sh`.

## Qué hacer si Composer falla por red/proxy

Síntoma típico:

- `curl error 56 while downloading https://repo.packagist.org/packages.json`
- `CONNECT tunnel failed, response 403`

Estado verificado en este repositorio (2026-05-07):

- `composer update --no-interaction --no-progress` falló con los errores anteriores, por lo que `composer.lock` sigue pendiente.
- Mientras exista este bloqueo, la instalación no es totalmente reproducible entre entornos.
- En cuanto haya acceso a Packagist, generar `composer.lock` con `composer update`, versionarlo y volver a instalar con `composer install` para validación determinística.

Esto significa un problema de conectividad (proxy/firewall/red) del entorno y no necesariamente un error de código del repositorio.

Acciones recomendadas:

1. Verificar salida a `repo.packagist.org` en el entorno CI/local.
2. Configurar proxy corporativo correctamente para Composer si aplica.
3. Usar mirror interno o caché de paquetes en CI.
4. Generar `composer.lock` en un entorno con conectividad estable.

## Qué significa que PHPStan se omita

Si `vendor/bin/phpstan` no existe, el script reporta PHPStan como omitido (skip).

Esto no implica que el análisis estático haya pasado; solo indica que la herramienta no está instalada/configurada en el entorno actual.

## Qué significa que PHPUnit/PHPStan no corran por falta de `vendor`

Si `composer install` no se ejecuta correctamente, no se crea `vendor/bin/phpunit` ni `vendor/bin/phpstan`.

En ese caso:

- `composer install` falla y el script termina con error.
- Si el bloqueo es de red/proxy, debe documentarse explícitamente en el PR.
- No debe afirmarse que pruebas/unit/static analysis pasaron si no se ejecutaron.


## Validación específica de persistencia de sesiones

Cambios cubiertos en esta iteración:

- Se agrega `app/Models/UserSession.php` con consultas preparadas PDO para crear/revocar/consultar sesiones activas en `user_sessions`.
- Se persiste sesión autenticada en login/register desde `AuthController` inmediatamente después de `Auth::login(...)`.
- En logout se revoca primero la sesión persistida y después se destruye la sesión PHP.
- Nunca se guarda `session_id()` en texto plano: se almacena únicamente `hash('sha256', session_id())`.

Pruebas automáticas agregadas (sin DB real):

- `tests/UserSessionTest.php` valida existencia del modelo.
- `tests/UserSessionTest.php` valida formato SHA-256 (64 hex) del hash de sesión.


## Validación específica de middleware de autenticación

Cambios cubiertos en esta iteración:

- `AuthMiddleware` ahora exige tres condiciones para rutas protegidas: usuario autenticado (`user_id`), `session_id()` presente y sesión persistida activa en `user_sessions` mediante `UserSession::isActive(...)`.
- Si la sesión persistida está revocada o expirada, se ejecuta cierre de sesión y redirección a `/login`.
- Si ocurre error durante la validación en DB, la política es fail-closed: cierre de sesión, redirección a `/login` y registro de error genérico sin datos sensibles.

Pruebas automáticas agregadas (sin DB real):

- `tests/AuthMiddlewareTest.php` verifica que el middleware invoque `UserSession::isActive($sessionId)`.
- `tests/AuthMiddlewareTest.php` verifica que, para sesión inválida, se cierre sesión y se redirija a `/login`.

## Validación específica de gestión de sesiones activas

Cambios cubiertos en esta iteración:

- Se añadieron métodos de gestión en `app/Models/UserSession.php` para listar sesiones por usuario, revocar por id de registro y revocar otras sesiones, siempre con consultas preparadas.
- Se creó `app/Controllers/SecurityController.php` con endpoints autenticados para:
  - listar sesiones (`GET /security/sessions`),
  - revocar una sesión (`POST /security/sessions/revoke`),
  - revocar todas las demás (`POST /security/sessions/revoke-others`).
- Si el usuario revoca su sesión actual, se ejecuta logout y redirección a `/login`.
- La vista `app/Views/security/sessions.php` no imprime `session_token_hash` y muestra estado, metadata y acciones con CSRF.
- No se realizaron cambios de esquema ni migraciones de `user_sessions`.

Pruebas automáticas agregadas (sin DB real):

- `tests/SecuritySessionsFeatureTest.php` valida existencia de controlador/vista.
- `tests/SecuritySessionsFeatureTest.php` valida presencia de los métodos nuevos en `UserSession`.
- `tests/SecuritySessionsFeatureTest.php` valida que la vista no contenga renderizado directo de `session_token_hash`.

## Validación específica de auditoría de autenticación y sesiones

Cambios cubiertos en esta iteración:

- Se creó `app/Models/AuditLog.php` para persistir eventos en la tabla real `audit_logs` con consultas preparadas.
- `ip_address` se guarda con `inet_pton()` solo cuando la IP es válida; si no, se guarda `NULL`.
- `user_agent` se limita a 500 caracteres.
- `metadata` se sanitiza y serializa como JSON válido o `NULL`.
- No se persisten `password`, `_csrf_token`, `session_id` ni `session_token_hash` en metadata.
- Se agregaron eventos: `register_success`, `login_success`, `login_failed`, `logout`, `session_revoked`, `other_sessions_revoked`, `persisted_session_invalid`, `persisted_session_validation_error`.
- Ante fallos de auditoría, el flujo principal continúa y solo se registra `error_log` genérico.

Pruebas automáticas agregadas (sin DB real):

- `tests/AuditLogTest.php` valida existencia de modelo y uso de tabla `audit_logs`.
- `tests/AuditLogTest.php` valida sanitización de metadata sensible.
- `tests/AuditLogTest.php` valida codificación JSON/NULL de metadata.


## Validación específica de visor protegido de auditoría

Cambios cubiertos en esta iteración:

- Se revisó el esquema SQL real en `database/migrations/001_initial_schema.sql` y el inventario SQL del repositorio (`database/migrations/*.sql`, `database/seeders/*.sql`) para evitar asumir tablas/campos inexistentes.
- Se agregó `GET /admin/audit-logs` en `public/index.php`, protegida por login y verificación de rol `admin` usando el sistema real de roles (`user_roles`/`roles`).
- `app/Models/AuditLog.php` ahora consulta `audit_logs` con prepared statements, filtros por `action`, `user_id`, `date_from`, `date_to`, límite acotado y decodificación segura de `ip_address` con `inet_ntop()`.
- La vista `app/Views/audit/index.php` muestra `created_at`, `user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `user_agent` truncado y metadata sanitizada con salida escapada.
- No se exponen `session_id`, `session_token_hash`, `password` ni `_csrf_token` en metadata mostrada.
- No se modificaron migraciones ni la estructura de `audit_logs`.

Pruebas automáticas agregadas (sin DB real):

- `tests/AuditLogViewerFeatureTest.php` valida existencia de controlador y vista.
- `tests/AuditLogViewerFeatureTest.php` valida presencia de `listRecent` en `AuditLog`.
- `tests/AuditLogViewerFeatureTest.php` valida ausencia de labels sensibles en la vista de auditoría.

## Validación específica de normalización de URLs del crawler

Cambios cubiertos en esta iteración:

- Se revisaron los SQL reales `database/migrations/001_initial_schema.sql` y `database/migrations/004_crawl_urls.sql` antes de modificar lógica del crawler.
- `CrawlerService::normalizeUrl()` ahora resuelve correctamente URLs relativas root-relative y sibling-relative cuando la base termina en archivo o carpeta.
- Se corrige resolución de query relativa (`?lang=en`) preservando la ruta base y reemplazando query, sin concatenaciones inválidas.
- Se mantienen descartes de fragmentos y esquemas no permitidos (`javascript`, `data`, `file`, `ftp`).
- Se agrega normalización de espacios en path/query para evitar `Error CURL: URL rejected: Malformed input to a URL function`, sin romper rutas ya codificadas.
- `parseHtml()` ahora trata `title`, `description` y `language` de forma null-safe para no invocar `mb_substr()` con `null`.
- No se realizaron cambios en migraciones, tablas ni columnas.

Pruebas automáticas agregadas (sin red y sin DB real):

- `tests/CrawlerServiceTest.php` valida resolución de casos reales reportados (`/signup.php`, `contact-us.php`, `?lang=en`) y evita patrones malformados.
- `tests/CrawlerServiceTest.php` valida rechazo de fragmentos/esquemas inseguros.
- `tests/CrawlerServiceTest.php` valida encoding seguro de espacios y preservación de segmentos ya codificados.
- `tests/CrawlerServiceTest.php` valida null-safety de `parseHtml()` sin `<title>`, sin meta description y sin `lang`.
