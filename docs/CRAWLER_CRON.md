# Crawler Cron (jobs -> URLs -> indexed_pages)

## Objetivo

Este documento aclara el cron real que debe procesar el crawler en producción/VM y cómo validarlo sin cambios destructivos.

## Auto-queue vs cron (diferencia crítica)

- **Auto-queue** (variables `CRAWL_AUTO_QUEUE_*`) **solo crea jobs** en `crawl_jobs` con estado `queued`.
- **Cron** ejecuta periódicamente el comando que **procesa** esos jobs queued.

Sin cron, los jobs pueden quedarse indefinidamente en `queued` aunque auto-queue esté activo.

## Comando correcto de ejecución del crawler

El comando que debe ejecutar cron es:

```bash
php bin/browser crawl:run --limit=1
```

> Importante: `crawl:status` solo informa estado, **no procesa** la cola.

## Cron recomendado (cada 5 minutos)

```cron
*/5 * * * * cd /var/www/browser && /usr/bin/flock -n /tmp/browser-crawler.lock /usr/bin/php bin/browser crawl:run --limit=1 >> /var/www/browser/storage/logs/crawler.log 2>&1
```

### Por qué usar `flock`

`flock` evita ejecuciones simultáneas del crawler cuando una corrida tarda más que el intervalo del cron.

### Por qué iniciar con `--limit=1`

`--limit=1` reduce riesgo operativo inicial (CPU/RAM/IO/DB) y facilita estabilizar el procesamiento.

### Cuándo considerar `--limit=2`

Solo considerar `--limit=2` si se cumplen todas:

- VM estable (sin presión sostenida de CPU/RAM).
- `storage/logs` sano (sin errores de escritura/rotación).
- MySQL responde bien y sin saturación.
- La cola (`crawl_jobs`/`crawl_urls`) crece más rápido de lo que se procesa.

## Comandos manuales del CLI

```bash
php bin/browser crawl:status
php bin/browser crawl:run --limit=1
php bin/browser crawl:run --job=ID
```

## Cómo verificar que sí indexa (flujo completo)

1. Revisar `crawl:status` antes y después de ejecutar `crawl:run`.
2. Revisar `storage/logs/crawler.log` para errores y progreso.
3. Revisar conteo por estado de `crawl_jobs` (`queued/completed/failed`).
4. Verificar que `indexed_pages` aumente cuando el job indexa páginas.
5. Verificar que `crawl_urls` reciba URLs descubiertas y cambie estados.

## Instalación manual del cron

### Usuario actual

```bash
crontab -e
crontab -l
```

### Usuario `www-data` (si hay sudo)

```bash
sudo crontab -u www-data -e
sudo crontab -u www-data -l
```

Si se usa `www-data`, `storage/` y `storage/logs/` deben ser escribibles por `www-data`.

## Validación manual

1. Ver estado antes:

   ```bash
   php bin/browser crawl:status
   ```

2. Ejecutar un job manual:

   ```bash
   php bin/browser crawl:run --limit=1
   ```

3. Ver estado después:

   ```bash
   php bin/browser crawl:status
   ```

4. Revisar log:

   ```bash
   tail -n 100 storage/logs/crawler.log
   ```

5. Si se tiene acceso a MySQL, verificar:

   ```sql
   SELECT status, COUNT(*) FROM crawl_jobs GROUP BY status;
   SELECT status, COUNT(*) FROM crawl_urls GROUP BY status;
   SELECT COUNT(*) FROM indexed_pages;
   ```

6. Si no aumentan `indexed_pages`:

- revisar `robots.txt` disallow,
- revisar errores HTTP,
- revisar permisos de `storage`,
- revisar que `.env` cargue `DB_*`,
- revisar que existan jobs `queued`,
- revisar que cron esté instalado en el usuario correcto.

## Alcance y seguridad

- Este documento **no** instala cron automáticamente.
- Este documento **no** modifica `.env` ni credenciales.
- Este documento **no** modifica Nginx.
- Producción y cambios de base de datos permanecen con aprobación humana.

## Siembra manual de jobs (sin ejecutar crawler)

Los comandos de siembra crean registros en `crawl_jobs` con estado `queued`. **No** ejecutan `crawl:run`.

```bash
php bin/browser crawl:queue --url=https://example.com --max-depth=1 --max-pages=10
php bin/browser crawl:queue-file --file=storage/crawler-seeds.txt --max-depth=1 --max-pages=10 --limit=20
```

Flujo recomendado:

1. Sembrar jobs con `crawl:queue` o `crawl:queue-file`.
2. Dejar que el cron (cada 5 minutos) procese la cola con `crawl:run --limit=1`.


## Siembra desde sitemap.xml

También puedes sembrar jobs desde un sitemap sin ejecutar el crawler directamente:

```bash
php bin/browser crawl:sitemap --url=https://example.com/sitemap.xml --max-depth=1 --max-pages=10 --limit=50
```

Este comando solo crea jobs `queued` usando `CrawlJob::create()`. El procesamiento sigue estando a cargo del cron con `php bin/browser crawl:run --limit=1` cada 5 minutos.

## Nuevo comando: crawl:robots-sitemaps

- `php bin/browser crawl:robots-sitemaps --url=https://example.com --max-depth=1 --max-pages=10 --limit=50`
- Lee `robots.txt`, extrae líneas `Sitemap:` y **solo siembra jobs** en `crawl_jobs` vía `CrawlJob::create()`.
- No ejecuta crawler directamente; el procesamiento real sigue en cron con `php bin/browser crawl:run`.
- No escribe directo en `indexed_pages` ni `crawl_urls`.
- Si `robots.txt` no existe (404) o no tiene `Sitemap:`, termina en modo no fatal con resumen claro.


## Nota: deduplicación de jobs de siembra

- `crawl:queue`, `crawl:queue-file`, `crawl:sitemap` y `crawl:robots-sitemaps` ahora omiten seeds duplicadas cuando ya existe un `crawl_jobs.seed_url` con estado `queued` o `running`.
- Mensaje esperado por URL duplicada: `[SKIP] Job duplicado: URL`.
- Resumen final separa: `jobs creados`, `URLs inválidas`, `URLs duplicadas`, `errores controlados`.
- El procesamiento real del crawler sigue siendo exclusivo de `crawl:run` (cron/manual), sin ejecución directa desde comandos de siembra.
- No se agregaron migraciones ni cambios de esquema de BD en esta mejora.

## Crawl budget por dominio (actualización)

- `CrawlerService` aplica cooldown por dominio de **15 segundos** durante una ejecución de `crawl:run`.
- Si todas las URLs pendientes del job están dentro de cooldown, se difieren para el siguiente cron y el comando imprime:
  - `[INFO] ... rate-limit: N URL(s) diferidas por dominio (cooldown 15s)`.
- Las URLs diferidas **no** se marcan como `failed`; permanecen `queued`.
- Recomendaciones:
  - Sitios propios: `--limit=1` cada 1-2 min, `max_pages` 25-50 según capacidad.
  - Sitios externos: `--limit=1` cada 5 min, `max_pages` 10-25 para minimizar 429/403/503.
  - Pausar/ajustar ante señales repetidas: HTTP 429, 403, 503 o timeouts.


## Observabilidad por dominio (solo lectura)

Nuevo comando operativo:

```bash
php bin/browser crawl:domains --limit=20
php bin/browser crawl:domains --domain=example.com
php bin/browser crawl:domains --errors
```

`crawl:domains` es **solo lectura**: consulta estado por dominio usando únicamente `SELECT` (sin `INSERT`, `UPDATE`, `DELETE`, migraciones ni cambios de esquema). Sirve para decidir si conviene sembrar más URLs o pausar temporalmente un dominio.

Señales para pausar un dominio:
- muchos `429`
- muchos `403`
- muchos `503`
- timeouts repetidos
- bloqueos por `robots.txt` (disallow)

El procesamiento real del crawler sigue siendo exclusivo del cron con `php bin/browser crawl:run --limit=1`.


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


## Reporte operativo de solo lectura

Usa `php bin/browser crawl:report` para diagnóstico consolidado (jobs, URLs, indexación, dominios pausados, recomendaciones y errores recientes).

- `crawl:report` **no ejecuta** `crawl:run`.
- `crawl:report` **no siembra** jobs.
- `crawl:report` **no pausa** ni reactiva dominios.
- `crawl:report` **no modifica** `storage/crawler/domain-policy.json`.

Opciones:

- `php bin/browser crawl:report --domain=example.com`
- `php bin/browser crawl:report --limit=10`
- `php bin/browser crawl:report --json`

El procesamiento real sigue siendo exclusivo de cron con `php bin/browser crawl:run --limit=1`.

## Snapshots operativos (`crawl:report --save`)

- `php bin/browser crawl:report --save` guarda un snapshot JSON en `storage/crawler/reports/`.
- Solo exporta estado operativo; no ejecuta `crawl:run`, no pausa dominios y no modifica la base de datos.
- El cron sigue siendo el único procesamiento real: `php bin/browser crawl:run --limit=1`.


## Historial de snapshots de crawler

- `php bin/browser crawl:report --save` guarda snapshots JSON en `storage/crawler/reports/`.
- `php bin/browser crawl:report-history` lista snapshots guardados (solo lectura).
- `php bin/browser crawl:report-show --file=crawl-report-YYYYmmdd-HHMMSS.json` muestra un snapshot guardado (solo lectura).
- `php bin/browser crawl:report-show --latest [--domain=example.com]` muestra el snapshot más reciente (solo lectura).
- `crawl:report-show` solo lee dentro de `storage/crawler/reports/`, valida patrón `crawl-report-*.json`, bloquea path traversal y no toca BD/crawler/jobs/pausas.
- Soporta `--json`, `--limit` y `--domain` para filtrar resultados sin modificar BD ni ejecutar crawler.
- No pausa dominios, no siembra jobs y no hay auto-deploy.

## Limpieza de snapshots (crawl:report-prune)

- Comando: `php bin/browser crawl:report-prune [--days=N|--keep=N] [--domain=example.com] [--confirm] [--json]`.
- Por defecto corre en **dry-run** y usa `--days=30` si no se pasa criterio.
- Solo considera archivos `crawl-report-*.json` dentro de `storage/crawler/reports/`.
- Solo borra con `--confirm`; sin `--confirm` solo lista candidatos.
- No toca base de datos, no ejecuta `crawl:run`, no pausa dominios, no siembra jobs.
