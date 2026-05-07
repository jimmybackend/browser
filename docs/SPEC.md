# Especificación del proyecto

## Nombre del proyecto

Browser

## Objetivo

Aplicación web MVC en PHP para una plataforma interna de búsqueda, autenticación de usuarios, correo interno y módulos de marketing (clientes y campañas), con enfoque en seguridad base y despliegue en Docker o servidor Linux.

## Stack real detectado

- PHP >= 8.3 (`composer.json`)
- Composer
- `vlucas/phpdotenv` para carga de entorno
- MySQL/MariaDB vía PDO (`pdo`, `pdo_mysql`)
- PHPUnit 11 (`phpunit/phpunit` en `require-dev`)
- Frontend server-rendered con vistas PHP + CSS/JS estático
- Docker + Nginx (`docker-compose.yml`, `docker/Dockerfile`, `docker/nginx.conf`)

## Estado actual de CI/validación

- Workflow en `.github/workflows/ci.yml` con eventos `push`, `pull_request` y `workflow_dispatch`.
- CI usa PHP 8.3 con extensiones `mbstring`, `intl`, `pdo`, `pdo_mysql`.
- La validación central corre con `bash scripts/validate.sh`.
- PHPUnit usa configuración explícita (`phpunit.xml.dist`) y fallback seguro en el script.

## Punto de entrada de la aplicación

- HTTP: `public/index.php`
- CLI: `bin/browser`

## Base de datos

- Configuración en `config/database.php` por variables `DB_*`.
- Conexión centralizada en `app/Core/Database.php` usando PDO con prepared statements habilitados.
- Esquema versionado con SQL en `database/migrations/*.sql`.
- Datos semilla en `database/seeders/*.sql`.

## Configuración requerida

Variables mínimas detectadas en `.env.example`:

- App: `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_DESCRIPTION`
- DB: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Crawler: `CRAWL_AUTO_QUEUE_*`

## Riesgos técnicos actuales

1. No existe `composer.lock`: instalaciones no reproducibles.
2. Dependencia de conectividad de Composer para instalar dependencias.
3. Suite de pruebas limitada (sin pruebas funcionales reales).
4. PHPStan opcional/no instalado en entornos donde no exista `vendor/bin/phpstan`.
5. Validación de DB no ejecutada contra instancia real en esta corrida.
