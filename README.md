# Browser

Browser es una plataforma web independiente enfocada en búsqueda, correo, privacidad, usuarios y herramientas de marketing digital.

El proyecto iniciará como un MVP accesible desde un dominio propio. No será todavía un navegador nativo como Chrome, Brave o Edge, sino una aplicación web preparada para crecer hacia un ecosistema más amplio.

## Objetivo del proyecto

Crear una plataforma web segura, escalable y modular que permita:

- Registrar usuarios.
- Iniciar sesión de forma segura.
- Administrar perfiles.
- Manejar roles y permisos.
- Tener correo interno.
- Crear una base para servicios de correo reales.
- Tener un buscador inicial.
- Preparar un futuro sistema de indexación.
- Administrar clientes de marketing.
- Administrar campañas.
- Administrar leads.
- Registrar eventos importantes del sistema.
- Preparar el proyecto para AWS.

## Stack técnico real

- PHP 8.3 o superior
- MySQL 8 o MariaDB
- PDO
- Composer
- HTML/CSS/JS
- Docker + Nginx

## Requisitos previos

### Con Docker

- Docker Engine
- Docker Compose

### Sin Docker

- PHP 8.3+
- Extensiones PHP: `mbstring`, `intl`, `pdo`, `pdo_mysql`
- Composer 2
- MySQL 8+ o MariaDB

## Variables de entorno

El proyecto usa `.env` local cargado por `vlucas/phpdotenv`.

1. Copia el ejemplo:

```bash
cp .env.example .env
```

2. Ajusta al menos estas variables:

- `APP_NAME`
- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- `APP_DESCRIPTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `CRAWL_AUTO_QUEUE_ENABLED`
- `CRAWL_AUTO_QUEUE_FOR_GUESTS`
- `CRAWL_AUTO_QUEUE_MAX_PAGES`
- `CRAWL_AUTO_QUEUE_MAX_DEPTH`

## Instalación con Docker

1. Copia `.env` desde el ejemplo y ajusta credenciales.
2. Construye y levanta servicios:

```bash
docker compose up --build -d
```

3. Instala dependencias (si no se instalaron automáticamente en `app`):

```bash
docker compose exec app composer install --no-interaction --prefer-dist --no-progress
```

4. Ejecuta migraciones:

```bash
docker compose exec app php bin/browser migrate
```

5. Ejecuta seeders:

```bash
docker compose exec app php bin/browser seed
```

6. Crea o promueve usuario admin inicial:

```bash
docker compose exec app php bin/browser admin:create
```

7. Abre la aplicación:

- http://localhost:8080

## Instalación sin Docker

1. Copia `.env` desde el ejemplo:

```bash
cp .env.example .env
```

2. Instala dependencias PHP:

```bash
composer install --no-interaction --prefer-dist --no-progress
```

3. Configura y levanta MySQL/MariaDB con base y usuario equivalentes a `.env`.

4. Ejecuta migraciones:

```bash
php bin/browser migrate
```

5. Ejecuta seeders:

```bash
php bin/browser seed
```

6. Crea o promueve admin inicial:

```bash
php bin/browser admin:create
```

7. Levanta servidor local:

```bash
composer run serve
```

8. Abre:

- http://localhost:8080

## Comandos CLI útiles

```bash
php bin/browser help
php bin/browser doctor
php bin/browser auth:doctor
php bin/browser migrate
php bin/browser seed
php bin/browser admin:create
```

## Validaciones

Validación recomendada:

```bash
bash scripts/validate.sh
composer test
```

Validación manual (si aplica):

```bash
find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l
composer validate --no-check-publish
composer install --no-interaction --prefer-dist --no-progress
vendor/bin/phpunit --configuration phpunit.xml.dist
vendor/bin/phpstan analyse
```

## CI

Este repositorio incluye workflow de GitHub Actions en `.github/workflows/ci.yml`.

- Ejecuta en `push`, `pull_request` y `workflow_dispatch`.
- Usa PHP 8.3 con extensiones `mbstring`, `intl`, `pdo`, `pdo_mysql`.
- Ejecuta `bash scripts/validate.sh`.

## Composer lockfile (estado actual)

Actualmente no se versiona `composer.lock` porque en este entorno el acceso a Packagist falla por red/proxy. En la ejecución del **2026-05-07** el comando `composer update --no-interaction --no-progress` devolvió `curl error 56` y `CONNECT tunnel failed, response 403`.

Cuando exista conectividad, generar lockfile con:

```bash
composer update
git add composer.lock
git commit -m "build: add composer lockfile"
```

Luego instalar dependencias con `composer install --no-interaction --prefer-dist --no-progress` para respetar exactamente las versiones fijadas en el lockfile.

## Seguridad

- No subir `.env` real, llaves privadas ni credenciales.
- Revisar `docs/SECURITY_CHECKLIST.md` antes de cerrar tareas sensibles.

## Instalación en Ubuntu + Nginx (referencia)

Para una instalación productiva en Ubuntu con Nginx, PHP-FPM y MySQL remoto:

- [`docs/UBUNTU_NGINX_PHP_INSTALL.md`](docs/UBUNTU_NGINX_PHP_INSTALL.md)

## Licencia

Apache 2.0.


## Qué hacer si Composer falla por red/proxy

Si `composer install` falla con errores como `curl error 56` o `CONNECT tunnel failed, response 403`, normalmente es un problema de conectividad/proxy del entorno.

Pasos sugeridos:

1. Verificar acceso de red a `repo.packagist.org`.
2. Configurar proxy corporativo para Composer si aplica.
3. Reintentar en una red con salida abierta o con mirror interno.
4. Generar `composer.lock` cuando exista conectividad estable.
