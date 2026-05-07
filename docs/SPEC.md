# Especificación del proyecto

## Nombre del proyecto

Browser

## Objetivo

Aplicación web MVC en PHP para una plataforma interna de búsqueda, autenticación de usuarios, correo interno y módulos de marketing (clientes y campañas), con enfoque en seguridad base y despliegue en Docker o servidor Linux.

## Stack real detectado

- PHP >= 8.3 (`composer.json`)
- Composer (autoload PSR-4 `Browser\\`)
- `vlucas/phpdotenv` para carga de entorno
- PHPUnit 11 (dependencia de desarrollo)
- MySQL/MariaDB vía PDO
- Frontend server-rendered con vistas PHP + CSS/JS estático
- Docker (Dockerfile, docker-compose, nginx.conf)
- Script CLI propio (`bin/browser`) para doctor/migraciones/seed/admin

## Arquitectura y módulos implementados

- Arquitectura MVC propia:
  - Core: Router, Request, Response, Session, Auth, Database, Validator, Csrf
  - Controladores: Auth, Dashboard, Profile, Search, Mail, Marketing, Admin
  - Modelos: User, Role, UserRole, MarketingClient, MarketingCampaign, CrawlUrl, etc.
  - Servicios: AuthService, MarketingService, SearchService, CrawlerService, AuditLogger
- Módulos visibles por rutas:
  - Público: `/`, `/about`
  - Auth: `/register`, `/login`, `/logout`
  - Usuario: `/dashboard`, `/profile`
  - Productos: `/search`, `/mail`
  - Marketing: `/marketing`, `/marketing/clients/*`, `/marketing/campaigns/*`
  - Administración: `/admin`, `/admin/users*`

## Punto de entrada de la aplicación

- HTTP: `public/index.php`
- CLI: `bin/browser`

## Base de datos

- Configuración en `config/database.php` por variables `DB_*`.
- Conexión centralizada en `app/Core/Database.php` usando PDO con:
  - `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
  - `PDO::ATTR_EMULATE_PREPARES => false`
- Esquema versionado con SQL en `database/migrations/*.sql`.
- Datos semilla en `database/seeders/*.sql`.

## Configuración requerida

Variables mínimas detectadas en `.env.example`:

- App: `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_DESCRIPTION`
- DB: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Crawler: `CRAWL_AUTO_QUEUE_*`

## Estado de validación actual

- `bash scripts/validate.sh` ejecuta:
  - lint de PHP ✅
  - `composer validate` ✅
  - `composer install` ❌ (fallo de red/proxy hacia Packagist en este entorno)
  - PHPUnit y PHPStan omitidos por no existir `vendor/bin/*` al no instalar dependencias
  - escaneo básico de nombres sensibles ✅

## Riesgos técnicos y de seguridad detectados

1. **No existe `composer.lock`**: instalaciones no reproducibles.
2. **Dependencia de red para validación**: el flujo de CI/local falla si no hay acceso a Packagist.
3. **Sin suite real de pruebas funcionales**: solo prueba base de estructura.
4. **Sin PHPStan configurado**: análisis estático no implementado aún.
5. **Verificación de secretos limitada por nombre de archivo**: no hay escaneo de contenido.
6. **Riesgo de exposición de logs en `error_log`** si infraestructura no separa canales correctamente (aunque el controlador evita registrar password/token).

## Criterios de aceptación generales (actualizados)

El proyecto puede considerarse listo para revisión funcional cuando:

- La instalación local funcione con `.env` documentado.
- `composer install` se ejecute con lockfile reproducible.
- La DB pueda migrarse/seedearse con comandos documentados.
- Flujos de auth/marketing/admin tengan pruebas (mínimo integración básica).
- Validaciones de seguridad (CSRF, sesiones, prepared statements) estén verificadas y auditadas por checklist.
- El pipeline de validación (lint + tests + estático) pase en entorno CI.
