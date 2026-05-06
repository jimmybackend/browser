<?php

declare(strict_types=1);

namespace Browser\Services;

final class MailService
{
    public function inboxPlaceholder(): array
    {
        // Fase MVP: este servicio prepara el módulo de correo.
        // En fases futuras conectará con SMTP/IMAP o infraestructura propia.
        return [
            [
                'from' => 'sistema@browser.local',
                'subject' => 'Bienvenido a Browser',
                'preview' => 'El módulo de correo está preparado como placeholder seguro.',
            ],
        ];
    }
}
