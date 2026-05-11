# OPERATIONS_RUNBOOK

## Propósito

Guía operativa diaria para mantener `jimmybackend/browser` estable en VM, con despliegues manuales y control humano de producción y base de datos.

## Alcance y límites

- Este runbook es **operativo** (no cambia código funcional).
- No modifica Nginx desde Git.
- No modifica migraciones ni estructura de base de datos.
- No automatiza producción de forma autónoma.
- Mantiene aprobación humana para cambios de producción y BD.

## Checklist diario (VM)

Ejecutar en `/var/www/browser`:

1. Verificar repo limpio:
   ```bash
   git status --short
   ```
2. Salud general de app:
   ```bash
   php bin/browser doctor
   ```
3. Salud de autenticación:
   ```bash
   php bin/browser auth:doctor
   ```
4. Estado de indexación:
   ```bash
   php bin/browser index:status
   ```
5. Diagnóstico de errores recientes del crawler:
   ```bash
   php bin/browser crawl:errors
   ```
6. Estado de crawler con usuario real del cron:
   ```bash
   sudo -u www-data php bin/browser crawl:status
   ```
7. Revisar log reciente del crawler:
   ```bash
   tail -n 100 storage/logs/crawler.log
   ```
7. Confirmar cron de `www-data`:
   ```bash
   sudo crontab -u www-data -l
   ```

## Checklist post-merge (despliegue manual)

Después de fusionar PR manualmente en GitHub:

```bash
cd /var/www/browser
bash scripts/deploy-update.sh
php bin/browser index:status
sudo -u www-data php bin/browser crawl:status
```

## Si falla el crawler

1. Revisar estado operativo:
   ```bash
   sudo -u www-data php bin/browser crawl:status
   ```
2. Revisar errores en log:
   ```bash
   tail -n 200 storage/logs/crawler.log
   ```
3. Verificar cron/configuración:
   ```bash
   bash scripts/crawler-cron-check.sh
   ```
4. Revisar permisos de `storage`:
   ```bash
   ls -ld storage storage/logs storage/cache storage/sessions
   ```
5. Revisar `.env` local en VM sin exponer secretos (solo presencia/formato de claves).

## Si falla `deploy-update.sh`

1. Revisar estado del repositorio:
   ```bash
   git status --short
   ```
2. Revisar permisos de escritura/ejecución del proyecto y `storage`.
3. Reintentar dependencias:
   ```bash
   composer install --no-interaction --prefer-dist --no-progress
   ```
4. Revisar estado de migraciones (sin aplicar migraciones no aprobadas).
5. Ejecutar diagnóstico de aplicación:
   ```bash
   php bin/browser doctor
   php bin/browser auth:doctor
   ```

## Comandos prohibidos en producción sin revisión

- `git reset --hard`
- `git clean -fdx`
- Borrar `.env`
- Modificar Nginx desde Git
- Correr migraciones manuales no revisadas

## Qué sí se puede ejecutar normalmente

- Comandos de diagnóstico:
  - `php bin/browser doctor`
  - `php bin/browser auth:doctor`
  - `php bin/browser index:status`
  - `sudo -u www-data php bin/browser crawl:status`
- Verificación de cron:
  - `bash scripts/crawler-cron-check.sh`
- Despliegue manual post-merge:
  - `bash scripts/deploy-update.sh`
- Revisión de logs:
  - `tail -n 100 storage/logs/crawler.log`

## Escalamiento

Escalar a revisión humana inmediata cuando ocurra cualquiera de estos casos:

- Fallos repetidos de `doctor` / `auth:doctor`.
- Crawler detenido por más de un ciclo operativo.
- Errores de permisos persistentes en `storage`.
- Dudas sobre migraciones o posibles impactos en datos de producción.

## Paso opcional pre-deploy (GitHub Actions)

Antes de actualizar la VM, se puede ejecutar validación manual pre-deploy:

1. Ir a **GitHub -> Actions -> Manual pre-deploy validation**.
2. Click en **Run workflow**.
3. Completar:
   - `reason`: motivo corto de la ejecución.
   - `target_ref`: rama/ref a validar (por defecto `main`).
4. Esperar estado verde.

Si pasa, continuar con despliegue manual en VM:

```bash
cd /var/www/browser
bash scripts/deploy-update.sh
```

## Rollback manual

Ante deploy fallido o incidente post-actualización:

1. Ejecutar diagnóstico no destructivo:
   ```bash
   bash scripts/rollback-check.sh
   ```
2. Seguir el procedimiento documentado en:
   - `docs/ROLLBACK_VM.md`
3. Mantener aprobación humana para rollback en producción y cualquier acción sobre base de datos.
4. Priorizar revert de PR en GitHub + `bash scripts/deploy-update.sh` en VM como ruta recomendada.


## Cuando el crawler no indexa páginas nuevas

Ejecutar este bloque de diagnóstico (solo lectura):

```bash
php bin/browser index:status
php bin/browser crawl:status
php bin/browser crawl:errors
tail -n 200 storage/logs/crawler.log
```

`crawl:errors` ayuda a separar errores históricos de fallos recientes (SSL, HTTP, malformed URL, `mb_substr/null`, robots/disallowed y otros).

## Sembrar nuevas URLs

Recomendación: empezar con pocos dominios confiables y monitorear resultados antes de ampliar volumen.

Comandos:

```bash
php bin/browser crawl:queue --url=https://example.com --max-depth=1 --max-pages=10
php bin/browser crawl:queue-file --file=storage/crawler-seeds.txt --max-depth=1 --max-pages=10 --limit=20
```

Después de sembrar, revisar:

```bash
php bin/browser index:status
php bin/browser crawl:status
```


## Sembrar desde sitemap

Para cargar múltiples URLs desde un sitemap XML en cola:

```bash
php bin/browser crawl:sitemap --url=https://example.com/sitemap.xml --max-depth=1 --max-pages=10 --limit=20
```

Recomendación operativa: empezar con `--limit` bajo (por ejemplo 10-20) y luego aumentar gradualmente según capacidad de la VM.

## Siembra desde robots.txt (sin ejecutar crawler)

Comando:

```bash
php bin/browser crawl:robots-sitemaps --url=https://example.com --max-depth=1 --max-pages=10 --limit=50
```

Notas operativas:

- Solo crea jobs en `crawl_jobs` usando `CrawlJob::create()`.
- El cron mantiene la ejecución real con `crawl:run`.
- No toca `.env`, no modifica Nginx, no despliega y no cambia esquema de BD.


## Nota: deduplicación de jobs de siembra

- `crawl:queue`, `crawl:queue-file`, `crawl:sitemap` y `crawl:robots-sitemaps` ahora omiten seeds duplicadas cuando ya existe un `crawl_jobs.seed_url` con estado `queued` o `running`.
- Mensaje esperado por URL duplicada: `[SKIP] Job duplicado: URL`.
- Resumen final separa: `jobs creados`, `URLs inválidas`, `URLs duplicadas`, `errores controlados`.
- El procesamiento real del crawler sigue siendo exclusivo de `crawl:run` (cron/manual), sin ejecución directa desde comandos de siembra.
- No se agregaron migraciones ni cambios de esquema de BD en esta mejora.

## Operación segura de crawl budget por dominio

- Cooldown activo por dominio: 15s por ejecución.
- Si aparecen mensajes `[INFO] ... rate-limit ... diferidas`, no es fallo: el cron reintentará en la siguiente corrida.
- Ajuste operativo recomendado:
  - Externos: mantener `crawl:run --limit=1` y jobs con `max_pages` bajo (10-25).
  - Propios: puede subirse `max_pages` gradualmente (25-50) monitoreando errores.
- Señales para pausar temporalmente si aumentan:
  - 429, 403, 503, timeouts cURL.


### Resumen por dominio (antes de sembrar)

```bash
php bin/browser crawl:domains --limit=20
php bin/browser crawl:domains --errors
```

Usar esta vista para decidir si sembrar más URLs o pausar dominios con señales de riesgo (429/403/503, timeouts repetidos, robots disallow).

> `crawl:domains` es solo lectura y no ejecuta crawler. El cron con `crawl:run --limit=1` sigue siendo el único procesador real.


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


## Diagnóstico unificado del crawler (solo lectura)

Antes de sembrar URLs, pausar dominios o esperar al siguiente ciclo de cron, ejecutar:

```bash
php bin/browser crawl:report
```

Variantes útiles:

```bash
php bin/browser crawl:report --domain=example.com
php bin/browser crawl:report --limit=10
php bin/browser crawl:report --json
```

Este comando es estrictamente de lectura: no ejecuta crawler, no siembra jobs y no cambia políticas de dominio.

## Histórico operativo del crawler

- Generar snapshot manual: `php bin/browser crawl:report --save` (opcional `--json`, `--domain`, `--limit`).
- Archivo generado: `storage/crawler/reports/crawl-report-...json`.
- Uso: trazabilidad histórica sin sembrar jobs ni ejecutar crawler.


## Historial de snapshots de crawler

- `php bin/browser crawl:report --save` guarda snapshots JSON en `storage/crawler/reports/`.
- `php bin/browser crawl:report-history` lista snapshots guardados (solo lectura).
- `php bin/browser crawl:report-show --file=...` muestra contenido de un snapshot específico guardado.
- `php bin/browser crawl:report-show --latest [--domain=example.com]` muestra el snapshot más reciente (global o por dominio).
- `crawl:report-show` es **solo lectura**: no ejecuta crawler, no siembra jobs, no pausa dominios y no modifica BD.
- Soporta `--json`, `--limit` y `--domain` para filtrar resultados sin modificar BD ni ejecutar crawler.
- No pausa dominios, no siembra jobs y no hay auto-deploy.

- `php bin/browser crawl:report-prune --days=30` (dry-run por defecto para snapshots viejos).
- `php bin/browser crawl:report-prune --keep=50 --confirm` (borra snapshots excedentes manteniendo los 50 más recientes).
- Seguridad: trabaja únicamente en `storage/crawler/reports/` y evita path traversal usando nombres seguros `crawl-report-*.json`.
