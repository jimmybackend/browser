<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Controller;
use Browser\Core\Request;
use Browser\Services\MarketingService;

final class MarketingController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireAuth();

        $this->view('marketing/index', [
            'title' => 'Marketing',
            'cards' => (new MarketingService())->dashboardCards(),
        ]);
    }
}
