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


## Validación específica: documentación de branch protection

Cambios cubiertos en esta iteración:

- Se agregó `docs/BRANCH_PROTECTION.md` con lineamientos para proteger `main` mediante PR obligatorio, checks obligatorios de CI, restricción de force push y restricción de borrado.
- Se documentó explícitamente que Codex trabaja creando PRs y el merge se mantiene manual (Jimmy), sin auto-merge por ahora.
- Se dejó explícito que despliegues a producción y cambios de base de datos continúan bajo aprobación humana.
- No se realizaron cambios en código funcional, migraciones, configuración de Nginx ni archivos `.env`/credenciales.

Validación ejecutada:

- Ejecutar `bash scripts/validate.sh` si el entorno lo permite.
- Si falla por red/proxy/dependencias externas, documentar comando, error y causa probable en el PR.

## Validación específica: plantillas de Issues/PR y flujo Codex

Cambios cubiertos en esta iteración:

- Se agregó `.github/pull_request_template.md` con checklist de resumen, tipo de cambio, controles de secretos, estado de BD, pruebas, limitaciones de entorno, riesgos y validación manual.
- Se agregaron plantillas de issue en `.github/ISSUE_TEMPLATE/`:
  - `bug_report.yml`
  - `feature_request.yml`
  - `codex_task.yml`
  - `database_sensitive_task.yml`
- Se agregó `docs/CODEX_WORKFLOW.md` para formalizar el flujo `1 tarea = 1 chat nuevo = 1 PR = 1 merge manual`, sin auto-merge y con aprobación humana para producción/BD.
- No se modificó código funcional de la aplicación.
- No se modificaron migraciones ni estructura de base de datos.
- No se tocó `.env` ni credenciales.
- No se modificó Nginx.

Validación ejecutada:

- Ejecutar `bash scripts/validate.sh` si el entorno lo permite.
- Validar sintaxis YAML de plantillas con parser local (por ejemplo `python3 -c "import yaml,sys; yaml.safe_load(open(path))"`).
- Si hay fallos de red/proxy en Composer/Packagist, documentar error exacto y causa probable en el PR.

## Validación específica: cron del crawler (documentación + diagnóstico)

Cambios cubiertos en esta iteración:

- Se agregó `docs/CRAWLER_CRON.md` para dejar explícito que auto-queue crea jobs en `crawl_jobs`, pero el procesamiento real lo ejecuta cron con `php bin/browser crawl:run --limit=1`.
- Se agregó `scripts/crawler-cron-check.sh` como diagnóstico no destructivo (sin instalar cron, sin tocar crontab, sin sudo, sin leer contenido de `.env`).
- Se documentó validación manual del flujo jobs -> URLs descubiertas -> páginas indexadas (`crawl_jobs`, `crawl_urls`, `indexed_pages`).

Comandos recomendados:

```bash
bash -n scripts/crawler-cron-check.sh
bash scripts/crawler-cron-check.sh
php bin/browser crawl:status
php bin/browser crawl:run --limit=1
php bin/browser crawl:status
tail -n 100 storage/logs/crawler.log
```

También ejecutar validación general cuando el entorno lo permita:

```bash
bash scripts/validate.sh
```

Si `scripts/validate.sh` falla por conectividad a Packagist/proxy (ej. `curl error 56`, `CONNECT tunnel failed, response 403`), documentar en el PR:

- comando ejecutado,
- error exacto,
- causa probable (red/proxy/firewall),
- alternativa usada en la validación local.

## Validación específica: actualización manual segura de VM (`deploy-update.sh`)

Cambios cubiertos en esta iteración:

- Se agregó `scripts/deploy-update.sh` para actualizar la VM manualmente después de merges.
- El script falla si no está en un repositorio Git válido.
- Muestra usuario/rama/último commit y respalda `.env` sin exponer contenido.
- Intenta backup de `/etc/nginx/sites-available/browser` solo con permisos suficientes.
- Ejecuta secuencia conservadora: `git fetch`, `git pull --ff-only`, `composer install`, `migrate`, `doctor`, `auth:doctor`, `index:status` y `crawler-cron-check`.
- Asegura directorios de `storage` y ajusta permisos solo si se puede.
- Intenta recargar `php8.5-fpm` y `nginx` solo cuando hay permisos; si no, muestra comandos manuales.
- No usa `git reset --hard` ni `git clean -fdx`.

Comandos recomendados:

```bash
bash -n scripts/deploy-update.sh
bash scripts/deploy-update.sh
bash scripts/validate.sh
```

Si `composer install` falla por Packagist/proxy (por ejemplo `curl error 56`, `CONNECT tunnel failed, response 403`), documentar explícitamente en el PR:

- comando ejecutado,
- error exacto,
- causa probable,
- alternativa usada para validar.

## Validación documental (runbook y automatización)

Para cambios solo-documentación como esta iteración:

1. Revisar enlaces internos a documentación existente:
   - `docs/CODEX_WORKFLOW.md`
   - `docs/BRANCH_PROTECTION.md`
   - `docs/DEPLOYMENT_VM.md`
   - `docs/CRAWLER_CRON.md`
2. Ejecutar validación general cuando el entorno lo permita:
   ```bash
   bash scripts/validate.sh
   ```
3. Si falla por Packagist/proxy, documentar explícitamente en PR:
   - comando ejecutado,
   - error exacto,
   - causa probable,
   - mitigación o siguiente paso.
4. Confirmar alcance:
   - sin cambios funcionales,
   - sin cambios en migraciones o estructura de BD,
   - sin cambios en `.env`/credenciales,
   - sin cambios en Nginx.

## Validación manual pre-deploy en GitHub Actions

Workflow: `.github/workflows/manual-predeploy-validation.yml`

### Cómo ejecutarlo

1. GitHub -> **Actions**.
2. Seleccionar **Manual pre-deploy validation**.
3. Click en **Run workflow**.
4. Completar inputs:
   - `reason`: razón corta de la validación manual.
   - `target_ref`: rama/ref a validar (default: `main`).

### Qué valida

- `composer validate --no-check-publish`
- `composer install --no-interaction --prefer-dist --no-progress`
- `bash scripts/validate.sh`
- Sintaxis shell:
  - `bash -n scripts/deploy-check.sh`
  - `bash -n scripts/deploy-after-pull.sh`
  - `bash -n scripts/deploy-update.sh`
  - `bash -n scripts/crawler-cron-check.sh`
- Smoke CLI:
  - `php bin/browser help`

### Límites explícitos

Este workflow:

- No hace deploy.
- No se conecta a la VM.
- No usa secretos.
- No modifica `.env`.
- No ejecuta migraciones sobre producción.
- No modifica estructura de base de datos.
