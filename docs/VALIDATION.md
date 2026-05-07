# Validación del proyecto

## Validación local (rápida)

Ejecutar:

```bash
bash scripts/validate.sh
```

El script valida, en este orden:

1. Sintaxis PHP (`php -l` en archivos del repo, excluyendo `vendor`/`node_modules`).
2. Composer (`composer validate` + `composer install`).
3. PHPUnit (`vendor/bin/phpunit` si existe).
4. PHPStan (`vendor/bin/phpstan analyse` si existe).
5. Escaneo básico de nombres de archivos sensibles (`.env`, `*.pem`, `*.key`, etc.).

## Validación local (manual)

Si no se puede usar el script, ejecutar lo que aplique:

### Sintaxis PHP

```bash
find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l
```

### Composer

```bash
composer validate --no-check-publish
composer install --no-interaction --prefer-dist --no-progress
```

### PHPUnit

```bash
vendor/bin/phpunit
```

### PHPStan

```bash
vendor/bin/phpstan analyse
```

## Validación en CI

Workflow: `.github/workflows/ci.yml`

- Triggers: `push`, `pull_request`, `workflow_dispatch`.
- Entorno: Ubuntu + PHP 8.3.
- Extensiones: `mbstring`, `intl`, `pdo`, `pdo_mysql`.
- Paso principal: `bash scripts/validate.sh`.

## Qué hacer si Composer falla por red/proxy

Síntoma típico:

- `curl error 56 while downloading https://repo.packagist.org/packages.json`
- `CONNECT tunnel failed, response 403`

Esto indica bloqueo de conectividad (proxy/firewall/red), no necesariamente error de código del proyecto.

Acciones recomendadas:

1. Verificar salida a `repo.packagist.org` en el entorno CI/local.
2. Configurar proxy corporativo correctamente para Composer si aplica.
3. Usar mirror interno o caché de paquetes en CI.
4. Generar `composer.lock` en un entorno con conectividad estable:

```bash
composer update
git add composer.lock
git commit -m "build: add composer lockfile"
```

## Qué significa que PHPUnit/PHPStan no corran por falta de `vendor`

Si `composer install` no se ejecuta correctamente, no se crea `vendor/bin/phpunit` ni `vendor/bin/phpstan`.

En ese caso:

- El script reporta estas validaciones como omitidas (skip).
- Debe documentarse explícitamente en el PR como limitación del entorno.
- No debe afirmarse que pruebas/unit/static analysis pasaron si no se ejecutaron.

## Validación funcional recomendada

Revisar manualmente:

- Instalación limpia.
- Configuración de entorno (`.env`).
- Conexión a base de datos.
- Login/logout.
- Rutas protegidas.
- Formularios principales.
- CRUD principal.
- Validaciones y mensajes de error.
- Logs y permisos.

## Definición de terminado

Una tarea se considera terminada cuando:

- El código/documentación fue actualizado.
- La validación aplicable fue ejecutada.
- Los errores fueron corregidos o documentados.
- El PR explica cambios, pruebas, riesgos y pendientes.
- No hay secretos expuestos.
