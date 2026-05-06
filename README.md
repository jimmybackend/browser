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
