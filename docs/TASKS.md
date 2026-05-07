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

- [x] Script `scripts/validate.sh` ejecutado: falla por `composer install` al no poder descargar desde Packagist (curl error 56 / CONNECT tunnel 403).
- [x] Lint PHP completo pasa sin errores de sintaxis.
- [x] `composer validate` pasa.
- [x] PHPUnit/PHPStan no corren porque no existe `vendor/bin/*` sin instalar dependencias.
- [x] Existe `.env.example` y no hay `.env` comprometido.
- [x] Hay Dockerfile, docker-compose y nginx config para entorno contenedorizado.
- [x] Rutas web definidas manualmente en `public/index.php` (sin framework tipo Laravel/Symfony).

### Pendientes críticos detectados

- [ ] Generar y versionar `composer.lock` para builds reproducibles.
- [ ] Asegurar conectividad de Composer en CI (proxy/firewall) o proveer mirror interno.
- [ ] Agregar pruebas más allá de `tests/BootstrapTest.php` (auth, rutas protegidas, marketing).
- [ ] Incorporar/anclar PHPStan y configuración (`phpstan.neon`) si será obligatorio.
- [ ] Fortalecer escaneo de secretos por contenido (no solo por nombres de archivo).
- [ ] Documentar procedimiento local exacto para migraciones + seed + admin inicial.

## Fase 2: Instalación

- [ ] Crear o actualizar README con pasos 100% verificables.
- [x] Existe `.env.example`.
- [ ] Documentar variables de entorno obligatorias/opcionales y valores por entorno.
- [ ] Documentar instalación local (Docker y sin Docker) con pasos de verificación.
- [ ] Documentar comandos de validación y posibles fallos de red.
- [ ] Verificar conexión a base de datos en ejecución real de app.

## Fase 3: Funcionalidad base

- [ ] Revisar login.
- [ ] Revisar logout.
- [ ] Revisar sesiones.
- [ ] Revisar rutas protegidas.
- [ ] Revisar navegación principal.
- [ ] Revisar formularios principales.
- [ ] Revisar CRUD principal de clientes/campañas.
- [ ] Revisar búsqueda/listado.
- [ ] Revisar mensajes de error y éxito.

## Fase 4: Seguridad

- [ ] Revisar SQL injection en modelos/servicios con datos externos.
- [ ] Revisar XSS en vistas.
- [ ] Revisar CSRF en formularios sensibles fuera de auth.
- [ ] Revisar autenticación y expiración de sesión.
- [ ] Revisar autorización por rol/permiso en panel admin.
- [x] Revisar manejo de contraseñas (`password_hash` / `password_verify`).
- [x] Revisar exposición de errores DB al usuario final (mensaje genérico en excepción).
- [ ] Revisar logs y posibles datos sensibles.

## Fase 5: Pruebas y validación

- [x] Existe validación de sintaxis PHP (`scripts/validate.sh`).
- [ ] Agregar pruebas mínimas funcionales si el proyecto tiene estructura para pruebas.
- [ ] Agregar PHPStan si aplica.
- [x] Existe PHPUnit configurado en composer, pero pendiente ejecutar en entorno con dependencias instaladas.
- [ ] Agregar workflow de GitHub Actions.
- [ ] Documentar pruebas manuales por módulo.

## Fase 6: Finalización

- [ ] Limpiar código muerto.
- [ ] Ordenar documentación.
- [ ] Crear checklist de producción final.
- [ ] Documentar despliegue con variables por entorno.
- [ ] Documentar riesgos residuales.
- [ ] Dejar PR listo para revisión.
