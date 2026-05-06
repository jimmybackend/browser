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

## Empresa objetivo

Browser está pensado para una empresa de marketing que desea tener una plataforma propia para búsqueda, comunicación, clientes, campañas y automatización.

La idea es construir primero una base firme y segura, y después agregar funciones más avanzadas.

## Stack técnico

- PHP 8.3 o superior
- MySQL 8 o MariaDB
- PDO
- Composer
- HTML
- CSS
- JavaScript
- Docker
- Apache o Nginx
- AWS EC2 en producción
- AWS S3 para archivos en fases futuras

## Licencia

Este proyecto usará la licencia Apache 2.0.

La licencia Apache 2.0 permite usar, modificar y distribuir el código, incluyendo uso comercial, siempre respetando los términos de la licencia.

## Estructura inicial del proyecto

```txt
browser/
├── app/
│   ├── Controllers/
│   ├── Core/
│   ├── Middleware/
│   ├── Models/
│   ├── Services/
│   └── Views/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── public/
│   └── assets/
├── storage/
│   ├── logs/
│   ├── mail/
│   └── uploads/
├── tests/
├── docker/
├── .env.example
├── .gitignore
├── composer.json
├── docker-compose.yml
├── LICENSE
└── README.md

## Protocolo ternario interno para IA

Browser incorpora un protocolo ternario para representar decisiones internas con valores numéricos estables en lugar de texto libre.

### ¿Qué es?
Es una convención de señales con tres estados fijos:

- `+1` = aceptar / positivo / relevante
- `0` = pendiente / neutral / desconocido
- `-1` = rechazar / negativo / riesgoso

### ¿Por qué usar +1, 0 y -1?
- Reduce ambigüedad semántica entre módulos y entre humano/IA.
- Simplifica validaciones, filtros y reglas de negocio.
- Evita depender de comparaciones de texto como "aprobado", "rechazado" o "pendiente".

### ¿Cómo ayuda al buscador manejado por IA?
Permite que el buscador evalúe resultados usando señales internas consistentes (por ejemplo relevancia, confianza y seguridad) y no etiquetas textuales variables.

### ¿Cómo evita comparar textos humanos?
La lógica interna compara enteros (`-1`, `0`, `1`). Las etiquetas humanas son solo una capa de visualización para UI o reportes.

### Uso previsto por área
- **Búsqueda**: relevancia, coincidencia de intención, confianza de fuente.
- **Seguridad y privacidad**: seguridad de contenido, riesgo de spam, señales de riesgo.
- **Correo**: riesgo de entrega de email.
- **Marketing**: calidad de lead y priorización comercial.

Regla base: los textos humanos son etiquetas visibles; la decisión interna siempre se ejecuta con valores numéricos ternarios.

## Instalación en servidor sin Docker

Para una instalación productiva en Ubuntu con Nginx, PHP-FPM y MySQL remoto, sigue la guía:

- [`docs/UBUNTU_NGINX_PHP_INSTALL.md`](docs/UBUNTU_NGINX_PHP_INSTALL.md)

Comandos CLI principales:

```bash
php bin/browser doctor
php bin/browser migrate
php bin/browser seed
php bin/browser admin:create
```
