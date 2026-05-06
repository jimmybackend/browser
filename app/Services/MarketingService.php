<?php

declare(strict_types=1);

namespace Browser\Services;

final class MarketingService
{
    public function dashboardCards(): array
    {
        return [
            'Clientes' => 'Base para administrar clientes de marketing.',
            'Campañas' => 'Base para registrar campañas, canales, fechas y presupuesto.',
            'Leads' => 'Base para seguimiento de prospectos.',
            'Eventos' => 'Base para historial comercial y automatizaciones.',
        ];
    }
}
