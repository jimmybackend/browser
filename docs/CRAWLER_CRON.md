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
