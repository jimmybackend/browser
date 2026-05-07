# Validación del proyecto

## Validación local (rápida)

Ejecutar:

```bash
bash scripts/validate.sh
```

El script valida, en este orden:

1. Sintaxis PHP (`php -l` en archivos del repo, excluyendo `vendor`/`node_modules`).
2. Composer (`composer validate` + `composer install`).
3. PHPUnit (si existe `vendor/bin/phpunit`) con fallback seguro:
   - `vendor/bin/phpunit --configuration phpunit.xml.dist` si existe `phpunit.xml.dist`.
   - `vendor/bin/phpunit --configuration phpunit.xml` si existe `phpunit.xml`.
   - `vendor/bin/phpunit tests` si no hay config pero sí existe `tests/`.
   - Nunca ejecuta `vendor/bin/phpunit` sin configuración/parámetro explícito.
4. PHPStan (`vendor/bin/phpstan analyse`) solo si está instalado.
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

### PHPUnit (con configuración explícita)

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist
```

### PHPStan

```bash
vendor/bin/phpstan analyse
```

## Cobertura baseline de PHPUnit (sin DB)

La suite actual incluye pruebas mínimas de estructura en `tests/BootstrapTest.php` para validar que existan:

- `app/`
- `public/index.php`
- `bin/browser`
- `database/migrations/`
- `.env.example`
- `AGENTS.md`
- `phpunit.xml.dist`
- `scripts/validate.sh`

También valida que `composer.json` mantenga:

- `autoload.psr-4.Browser\\` apuntando a `app/`
- script `test` con `vendor/bin/phpunit --configuration phpunit.xml.dist`

Estas pruebas no requieren MySQL ni conexión externa.

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

Esto significa un problema de conectividad (proxy/firewall/red) del entorno y no necesariamente un error de código del repositorio.

Acciones recomendadas:

1. Verificar salida a `repo.packagist.org` en el entorno CI/local.
2. Configurar proxy corporativo correctamente para Composer si aplica.
3. Usar mirror interno o caché de paquetes en CI.
4. Generar `composer.lock` en un entorno con conectividad estable.

## Qué significa que PHPStan se omita

Si `vendor/bin/phpstan` no existe, el script reporta PHPStan como omitido (skip).

Esto no implica que el análisis estático haya pasado; solo indica que la herramienta no está instalada/configurada en el entorno actual.

## Qué significa que PHPUnit/PHPStan no corran por falta de `vendor`

Si `composer install` no se ejecuta correctamente, no se crea `vendor/bin/phpunit` ni `vendor/bin/phpstan`.

En ese caso:

- El script reporta estas validaciones como omitidas (skip).
- Debe documentarse explícitamente en el PR como limitación del entorno.
- No debe afirmarse que pruebas/unit/static analysis pasaron si no se ejecutaron.
