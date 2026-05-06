# Crawler en Browser

## Variables `.env`
- `CRAWL_AUTO_QUEUE_ENABLED` (`true|false`): habilita/deshabilita el auto-queue desde búsquedas.
- `CRAWL_AUTO_QUEUE_FOR_GUESTS` (`true|false`): permite que usuarios no logueados creen jobs en cola.
- `CRAWL_AUTO_QUEUE_MAX_DEPTH` (entero): profundidad para jobs creados automáticamente.
- `CRAWL_AUTO_QUEUE_MAX_PAGES` (entero): tope de páginas para jobs creados automáticamente.

## Reglas de auto queue
- Abrir `/` sin `q` **no** encola crawler.
- Solo una búsqueda con `q` no vacío puede intentar encolar job.
- La request web crea únicamente jobs `queued` (no ejecuta crawling).
- El crawling real se ejecuta fuera de la request, por cron/CLI.
- Si `CRAWL_AUTO_QUEUE_FOR_GUESTS=false`, solo usuarios autenticados pueden encolar.
- Se evita duplicar jobs recientes para el mismo dominio en 24h.

## Activar auto queue
Ejemplo:

```env
CRAWL_AUTO_QUEUE_ENABLED=true
CRAWL_AUTO_QUEUE_FOR_GUESTS=false
CRAWL_AUTO_QUEUE_MAX_DEPTH=1
CRAWL_AUTO_QUEUE_MAX_PAGES=10
```

## Ejecutar crawler por cron
Ejemplo cada minuto:

```cron
* * * * * cd /ruta/al/proyecto && php bin/browser crawl:run --limit=10 >> storage/logs/crawler.log 2>&1
```

## Comandos CLI
- Crear job manual:
  ```bash
  php bin/browser crawl:add
  ```
- Ejecutar jobs en cola:
  ```bash
  php bin/browser crawl:run --limit=10
  ```
- Ver estado resumido:
  ```bash
  php bin/browser crawl:status
  ```
