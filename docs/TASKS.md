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

## Fase 14: Documentación y diagnóstico de cron real del crawler

- [x] Revisar SQL real antes de documentar tablas del crawler (`database/migrations/001_initial_schema.sql`, `database/migrations/004_crawl_urls.sql`).
- [x] Documentar diferencia entre auto-queue (crea jobs) y cron (procesa jobs) en `docs/CRAWLER_CRON.md`.
- [x] Documentar cron recomendado con `flock` ejecutando `php bin/browser crawl:run --limit=1` cada 5 minutos.
- [x] Agregar script diagnóstico no destructivo `scripts/crawler-cron-check.sh`.
- [x] Aclarar que no se automatiza producción ni se instala cron automáticamente.
- [x] Aclarar que la instalación de cron debe ser manual (`crontab -e`) por humano.
- [x] Confirmar que no se modifican migraciones, estructura de BD, `.env` ni Nginx.

## Fase 15: Actualización manual segura de VM post-merge

- [x] Crear `scripts/deploy-update.sh` como flujo manual único post-merge.
- [x] Mantener ejecución conservadora sin comandos destructivos de Git.
- [x] Validar repo Git, mostrar usuario/rama/último commit antes de actualizar.
- [x] Respaldar `.env` sin exponer contenido.
- [x] Intentar backup de config Nginx solo si hay permisos (sin modificar Nginx).
- [x] Ejecutar secuencia segura: fetch/pull ff-only, composer install, migrate, doctor, auth:doctor, index:status y chequeo de cron.
- [x] Asegurar directorios `storage/logs`, `storage/cache`, `storage/sessions`.
- [x] Ajustar permisos de `storage` solo si hay permisos.
- [x] Recargar `php8.5-fpm` y `nginx` solo con permisos suficientes; si no, mostrar comandos manuales.
- [x] Confirmar que no se modifican migraciones, estructura de BD, `.env` ni Nginx.

## Fase 16: Runbook operativo diario y plan de automatización gradual con Codex

- [x] Crear `docs/OPERATIONS_RUNBOOK.md` con checklist diario operativo en VM.
- [x] Documentar checklist post-merge con `scripts/deploy-update.sh` y chequeos de estado.
- [x] Documentar respuesta a incidentes de crawler y fallos de deploy-update.
- [x] Definir comandos prohibidos en producción sin revisión humana.
- [x] Crear `docs/CODEX_AUTOMATION_PLAN.md` con fases 0 a 5.
- [x] Dejar explícito estado actual entre Fase 3 y Fase 4.
- [x] Definir condiciones de avance a Fase 4 y Fase 5.
- [x] Mantener explícito: sin auto-merge, sin despliegue autónomo de Codex en producción.
- [x] Confirmar que esta fase es solo documental (sin cambios funcionales, migraciones, `.env` ni Nginx).

## Fase 17: Workflow manual de validación pre-deploy

- [x] Crear `.github/workflows/manual-predeploy-validation.yml` con trigger `workflow_dispatch`.
- [x] Agregar inputs `reason` y `target_ref` (default `main`).
- [x] Configurar job en `ubuntu-latest` con checkout del ref indicado.
- [x] Configurar PHP 8.3 e instalar extensiones requeridas (`pdo_mysql`, `mbstring`, `curl`, `zip`, `intl`, `gd`, `bcmath`, `soap`).
- [x] Ejecutar validaciones: `composer validate`, `composer install`, `bash scripts/validate.sh`, `bash -n` de scripts operativos y `php bin/browser help`.
- [x] Mantener límites: sin deploy, sin conexión a VM, sin uso de secretos, sin cambios de BD ni `.env`.
- [x] Actualizar documentación operativa y plan de automatización para aclarar que Jimmy mantiene merge y deploy manual.

## Fase 18: Rollback manual seguro de VM (documentación + diagnóstico)

- [x] Crear `docs/ROLLBACK_VM.md` con procedimiento seguro de rollback manual.
- [x] Dejar explícito que rollback requiere aprobación humana para producción y BD.
- [x] Documentar checklist previo (commit actual, `git status`, backup `.env` sin exponer contenido, logs, DB si aplica, backups existentes).
- [x] Documentar comandos prohibidos sin revisión (`git reset --hard`, `git clean -fdx`).
- [x] Documentar ruta preferida: revert PR en GitHub + merge manual + `bash scripts/deploy-update.sh` en VM.
- [x] Documentar contingencias por fallos de reload de servicios, Composer y migraciones.
- [x] Agregar script diagnóstico no destructivo `scripts/rollback-check.sh` (sin rollback automático).
- [x] Actualizar runbook y plan de automatización para incluir rollback como requisito previo de Fase 5.
- [x] Confirmar que esta fase no modifica código funcional, migraciones, `.env` ni Nginx.


## Fase 19: Diagnóstico CLI de errores recientes del crawler

- [x] Revisar SQL real previo (`database/migrations/001_initial_schema.sql`, `database/migrations/004_crawl_urls.sql`).
- [x] Agregar comando `php bin/browser crawl:errors` en `Kernel::handle()` y `help`.
- [x] Implementar `crawlErrors()` usando `Env::load(BASE_PATH)` + `Database::connection()` y solo `SELECT`.
- [x] Mostrar últimos errores de `crawl_urls` y `crawl_jobs` sin modificar datos.
- [x] Agregar resumen por tipo aproximado de error para operación diaria.
- [x] Actualizar `docs/OPERATIONS_RUNBOOK.md` y `docs/VALIDATION.md` con uso del comando.
- [x] Confirmar que no se modifican migraciones, estructura de BD, `.env` ni Nginx.

## Fase 20: Comandos CLI no interactivos para siembra del crawler

- [x] Revisar SQL real previo (`database/migrations/001_initial_schema.sql`, `database/migrations/004_crawl_urls.sql`).
- [x] Agregar comando `crawl:queue` en `Kernel::handle()` y `help`.
- [x] Agregar comando `crawl:queue-file` en `Kernel::handle()` y `help`.
- [x] Validar URL con la misma lógica segura existente (`normalizeSafeSeedUrl`).
- [x] Rechazar URL vacía/ inválida, localhost, rangos privados/reservados y esquemas no HTTP/HTTPS.
- [x] Crear jobs `queued` exclusivamente vía `CrawlJob::create()`.
- [x] Limitar creación por ejecución en `crawl:queue-file` con `--limit`.
- [x] Actualizar documentación operativa y de validación.
- [x] Confirmar que no se ejecuta `crawl:run`, no se modifica `indexed_pages`, no se modifica `crawl_urls` y no hay migraciones nuevas.


## Fase 21: Sitemap discovery para siembra de crawler

- [x] Revisar SQL real previo (`database/migrations/001_initial_schema.sql`, `database/migrations/004_crawl_urls.sql`).
- [x] Agregar comando `crawl:sitemap` en `Kernel::handle()` y `help`.
- [x] Parsear `urlset` y `sitemapindex` básico en modo seguro (sin XXE).
- [x] Crear jobs `queued` exclusivamente vía `CrawlJob::create()`.
- [x] Respetar límite con `--limit` y omitir URLs inválidas.
- [x] Actualizar documentación operativa/validación.
- [x] Confirmar que no se modifica `crawl_urls`/`indexed_pages` directamente ni migraciones.

## Fase 22: Descubrimiento de sitemaps desde robots.txt

- [x] Agregar comando `crawl:robots-sitemaps` en `Kernel` y `help`.
- [x] Normalizar `--url` a `/robots.txt` y aplicar validación segura de URL.
- [x] Extraer líneas `Sitemap:` tolerando mayúsculas/minúsculas y espacios.
- [x] Reutilizar `SitemapDiscoveryService` para parsear sitemaps y sembrar jobs.
- [x] Respetar `--limit` total entre múltiples sitemaps.
- [x] Tratar `robots.txt` sin `Sitemap:` o con 404 como no fatal.
- [x] Mantener que no se ejecuta `crawl:run` ni se escriben `crawl_urls`/`indexed_pages` directo.


## Nota: deduplicación de jobs de siembra

- `crawl:queue`, `crawl:queue-file`, `crawl:sitemap` y `crawl:robots-sitemaps` ahora omiten seeds duplicadas cuando ya existe un `crawl_jobs.seed_url` con estado `queued` o `running`.
- Mensaje esperado por URL duplicada: `[SKIP] Job duplicado: URL`.
- Resumen final separa: `jobs creados`, `URLs inválidas`, `URLs duplicadas`, `errores controlados`.
- El procesamiento real del crawler sigue siendo exclusivo de `crawl:run` (cron/manual), sin ejecución directa desde comandos de siembra.
- No se agregaron migraciones ni cambios de esquema de BD en esta mejora.

## Fase 23: Crawl budget/rate limit por dominio

- [x] Agregar `CrawlRateLimiter` con cooldown conservador por dominio (15s).
- [x] Integrar rate limit en `CrawlerService::runJob` para diferir URLs del mismo dominio en la misma corrida.
- [x] Mantener URLs diferidas en estado `queued` (no `failed`) para siguiente ejecución de cron.
- [x] Emitir salida informativa en `crawl:run` cuando existan URLs diferidas por rate limit.
- [x] Mantener compatibilidad con `--limit` y `--job` en `crawl:run`.
- [x] Sin cambios de migraciones ni esquema de BD.


## Fase 23: Observabilidad CLI de crawler por dominio (solo lectura)

- [x] Revisar SQL real previo (`database/migrations/001_initial_schema.sql`, `database/migrations/004_crawl_urls.sql`).
- [x] Agregar comando `crawl:domains` en `Kernel::handle()` y `help`.
- [x] Implementar servicio `CrawlDomainStatusService` con solo `SELECT` + prepared statements.
- [x] Soportar opciones `--limit=20`, `--domain=example.com`, `--errors`.
- [x] Mostrar mensaje claro cuando no hay datos de crawler por dominio.
- [x] Agregar pruebas PHPUnit para resumen vacío, agrupación, filtro por dominio, errores recientes y comportamiento solo lectura.
- [x] Actualizar documentación operativa/validación.
- [x] Confirmar que no se modifican migraciones, esquema de BD, `.env` ni Nginx y que no se ejecuta crawler directamente.


## Control manual de dominio pausado

- Comando operativo: `php bin/browser crawl:domain-policy <list|pause|resume|status>`.
- Persiste políticas en `storage/crawler/domain-policy.json` (se crea solo en `pause`/`resume`).
- `crawl:run` difiere URLs de dominios pausados sin marcarlas `failed`; permanecen `queued`.
- `crawl:domains` muestra `paused=true/false` por dominio.
- El procesamiento real sigue siendo por cron con `php bin/browser crawl:run --limit=1`.


## crawl:domain-advice (solo lectura)

- Comando: `php bin/browser crawl:domain-advice [--limit=20] [--domain=example.com] [--threshold=5]`.
- Analiza señales recientes por dominio (`429`, `403`, `503`, `timeouts`, `robots/disallow`, errores genéricos) usando solo consultas `SELECT`.
- **No pausa dominios automáticamente** y **no modifica** `storage/crawler/domain-policy.json`.
- Muestra recomendaciones operativas y comando sugerido para pausa manual: `php bin/browser crawl:domain-policy pause --domain=... --reason="..."`.
- Si el dominio ya está pausado, lo informa y no sugiere pausa duplicada.


## Fase 24: Reporte operativo unificado del crawler (solo lectura)

- [x] Agregar comando `crawl:report` en `Kernel` y `help`.
- [x] Implementar `CrawlOperationalReportService` con agregaciones de solo lectura (`SELECT`).
- [x] Incluir resumen de jobs, URLs, total de indexed pages, dominios en cola, dominios pausados, recomendaciones y errores recientes.
- [x] Soportar `--domain`, `--limit` y `--json`.
- [x] Mantener comportamiento no destructivo: sin `crawl:run`, sin siembra de jobs, sin pausa automática, sin cambios en `domain-policy.json`.
- [x] Agregar pruebas PHPUnit para reporte vacío, agregaciones y contrato de solo lectura.
