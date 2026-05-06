<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Controller;
use Browser\Core\Request;
use Browser\Services\MailService;

final class MailController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireAuth();

        $this->view('mail/index', [
            'title' => 'Correo',
            'messages' => (new MailService())->inboxPlaceholder(),
        ]);
    }
}
