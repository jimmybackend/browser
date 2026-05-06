<?php

declare(strict_types=1);

namespace Browser\Services;

use Browser\Core\TernarySignal;

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

    public function leadQualitySignalExample(): array
    {
        $leadQualitySignal = TernarySignal::NEUTRAL;

        return [
            'signal_key' => 'marketing_lead_quality',
            'signal_value' => $leadQualitySignal,
            'signal_label' => TernarySignal::label($leadQualitySignal),
            'human_hint' => '0 = lead pendiente de calificación comercial.',
        ];
    }
}
