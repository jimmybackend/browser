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

- [ ] Generar y versionar `composer.lock` para builds reproducibles (no generado en este cambio).
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
