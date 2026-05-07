# Validación del proyecto

## Validación rápida

Ejecutar:

```bash
bash scripts/validate.sh
```

## Validación manual

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

## Validación funcional

Revisar manualmente:

- Instalación limpia.
- Configuración de entorno.
- Conexión a base de datos.
- Login.
- Logout.
- Rutas protegidas.
- Formularios principales.
- CRUD principal.
- Validaciones.
- Mensajes de error.
- Logs.
- Permisos.

## Validación de seguridad

Confirmar:

- No hay credenciales en el repositorio.
- No hay `.env` real subido.
- No hay claves AWS.
- No hay archivos `.pem` o `.key`.
- Las consultas usan PDO preparado.
- Las entradas se validan.
- Las salidas HTML se escapan.
- Las rutas privadas requieren sesión.
- Los errores internos no se muestran al usuario final.

## Definición de terminado

Una tarea se considera terminada cuando:

- El código fue implementado.
- La validación aplicable fue ejecutada.
- Los errores fueron corregidos o documentados.
- El PR explica cambios, pruebas, riesgos y pendientes.
- No hay secretos expuestos.
