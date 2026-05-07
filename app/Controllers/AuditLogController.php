<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Auth;
use Browser\Core\Controller;
use Browser\Core\Request;
use Browser\Core\Response;
use Browser\Models\AuditLog;
use Browser\Models\UserRole;

final class AuditLogController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireAuth();

        $userId = Auth::id();
        if ($userId === null || !UserRole::userHasRole($userId, 'admin')) {
            Response::forbidden();
            return;
        }

        $filters = [
            'action' => trim((string) $request->input('action', '')),
            'user_id' => trim((string) $request->input('user_id', '')),
            'date_from' => trim((string) $request->input('date_from', '')),
            'date_to' => trim((string) $request->input('date_to', '')),
        ];

        $this->view('audit/index', [
            'title' => 'Audit Logs',
            'logs' => AuditLog::listRecent(100, $filters),
            'filters' => $filters,
            'total' => AuditLog::countByFilters($filters),
        ]);
    }
}
