<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Auth;
use Browser\Core\Controller;
use Browser\Core\Csrf;
use Browser\Core\Request;
use Browser\Core\Response;
use Browser\Core\Session;
use Browser\Core\TernarySignal;
use Browser\Models\MarketingClient;
use Browser\Models\UserRole;
use Browser\Services\AuditLogger;
use Browser\Services\TernarySignalService;

final class MarketingClientController extends Controller
{
    private const ALLOWED_ROLES = ['admin', 'marketing_manager', 'sales_agent'];

    public function index(Request $request): void
    {
        $this->authorizeMarketingAccess();

        $clients = MarketingClient::list();
        $this->view('marketing/clients/index', ['title' => 'Clientes de Marketing', 'clients' => $clients]);
    }

    public function create(Request $request): void
    {
        $this->authorizeMarketingAccess();

        $this->view('marketing/clients/create', ['title' => 'Nuevo cliente']);
    }

    public function store(Request $request): void
    {
        $this->authorizeMarketingAccess();
        $this->validateCsrfOrRedirect($request, '/marketing/clients/create');

        [$data, $errors] = $this->validatePayload($request);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            Response::redirect('/marketing/clients/create');
        }

        try {
            $clientId = MarketingClient::create($data);
            $this->logAudit('marketing_client_created', (string) $clientId, ['status' => $data['status']]);
            $this->recordHealthSignal($clientId, $data['status']);
            Session::flash('success', 'Cliente creado correctamente.');
            Response::redirect('/marketing/clients/show?id=' . $clientId);
        } catch (\Throwable $exception) {
            Session::flash('error', 'No se pudo crear el cliente.');
            Response::redirect('/marketing/clients/create');
        }
    }

    public function show(Request $request): void
    {
        $this->authorizeMarketingAccess();
        $id = (int) $request->query('id', 0);
        $client = MarketingClient::find($id);

        if (!$client) {
            Session::flash('error', 'Cliente no encontrado.');
            Response::redirect('/marketing/clients');
        }

        $this->view('marketing/clients/show', ['title' => 'Detalle de cliente', 'client' => $client, 'healthSignal' => $this->statusToSignal($client['status'])]);
    }

    public function edit(Request $request): void
    {
        $this->authorizeMarketingAccess();
        $id = (int) $request->query('id', 0);
        $client = MarketingClient::find($id);

        if (!$client) {
            Session::flash('error', 'Cliente no encontrado.');
            Response::redirect('/marketing/clients');
        }

        $this->view('marketing/clients/edit', ['title' => 'Editar cliente', 'client' => $client]);
    }

    public function update(Request $request): void
    {
        $this->authorizeMarketingAccess();
        $this->validateCsrfOrRedirect($request, '/marketing/clients');

        $id = (int) $request->post('id', 0);
        if (!MarketingClient::find($id)) {
            Session::flash('error', 'Cliente no encontrado.');
            Response::redirect('/marketing/clients');
        }

        [$data, $errors] = $this->validatePayload($request);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            Response::redirect('/marketing/clients/edit?id=' . $id);
        }

        try {
            MarketingClient::update($id, $data);
            $this->logAudit('marketing_client_updated', (string) $id, ['status' => $data['status']]);
            $this->recordHealthSignal($id, $data['status']);
            Session::flash('success', 'Cliente actualizado correctamente.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'No se pudo actualizar el cliente.');
        }

        Response::redirect('/marketing/clients/show?id=' . $id);
    }

    public function status(Request $request): void
    {
        $this->authorizeMarketingAccess();
        $this->validateCsrfOrRedirect($request, '/marketing/clients');

        $id = (int) $request->post('id', 0);
        $status = trim((string) $request->post('status'));

        if (!MarketingClient::find($id) || !in_array($status, MarketingClient::VALID_STATUSES, true)) {
            Session::flash('error', 'Solicitud inválida para cambio de estado.');
            Response::redirect('/marketing/clients');
        }

        try {
            MarketingClient::updateStatus($id, $status);
            $this->logAudit('marketing_client_status_changed', (string) $id, ['status' => $status]);
            $this->recordHealthSignal($id, $status);
            Session::flash('success', 'Estado actualizado correctamente.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'No se pudo cambiar el estado.');
        }

        Response::redirect('/marketing/clients/show?id=' . $id);
    }

    public function delete(Request $request): void
    {
        $this->authorizeMarketingAccess();
        $this->validateCsrfOrRedirect($request, '/marketing/clients');

        $id = (int) $request->post('id', 0);

        if (!MarketingClient::find($id)) {
            Session::flash('error', 'Cliente no encontrado.');
            Response::redirect('/marketing/clients');
        }

        try {
            MarketingClient::delete($id);
            $this->logAudit('marketing_client_deleted', (string) $id);
            Session::flash('success', 'Cliente eliminado correctamente.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'No se pudo eliminar el cliente.');
        }

        Response::redirect('/marketing/clients');
    }

    private function authorizeMarketingAccess(): void
    {
        $this->requireAuth();
        $userId = Auth::id();

        if ($userId === null) {
            Response::redirect('/login');
        }

        foreach (self::ALLOWED_ROLES as $role) {
            if (UserRole::userHasRole($userId, $role)) {
                return;
            }
        }

        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }

    private function validateCsrfOrRedirect(Request $request, string $redirect): void
    {
        if (!Csrf::validate((string) $request->post('_csrf_token'))) {
            Session::flash('error', 'Token CSRF inválido.');
            Response::redirect($redirect);
        }
    }

    private function validatePayload(Request $request): array
    {
        $companyName = trim((string) $request->post('company_name'));
        $contactName = trim((string) $request->post('contact_name'));
        $contactEmail = trim((string) $request->post('contact_email'));
        $contactPhone = trim((string) $request->post('contact_phone'));
        $website = trim((string) $request->post('website'));
        $status = trim((string) $request->post('status'));
        $notes = trim((string) $request->post('notes'));

        $errors = [];
        if ($companyName === '' || mb_strlen($companyName) < 2 || mb_strlen($companyName) > 190) {
            $errors[] = 'Empresa inválida (2-190 caracteres).';
        }
        if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Correo de contacto inválido.';
        }
        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Sitio web inválido.';
        }
        if ($contactPhone !== '' && mb_strlen($contactPhone) > 60) {
            $errors[] = 'Teléfono demasiado largo.';
        }
        if (!in_array($status, MarketingClient::VALID_STATUSES, true)) {
            $errors[] = 'Estado inválido.';
        }
        if ($notes !== '' && mb_strlen($notes) > 5000) {
            $errors[] = 'Notas demasiado largas.';
        }

        return [[
            'company_name' => $companyName,
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'website' => $website,
            'status' => $status,
            'notes' => $notes,
        ], $errors];
    }

    private function recordHealthSignal(int $clientId, string $status): void
    {
        $signal = $this->statusToSignal($status);
        (new TernarySignalService())->record('client_health_signal', $signal, 'marketing_client', (string) $clientId, Auth::id(), 'system', null, 'client_status_sync', null, ['status' => $status]);
    }

    private function statusToSignal(string $status): int
    {
        return match ($status) {
            'active' => TernarySignal::POSITIVE,
            'prospect' => TernarySignal::NEUTRAL,
            default => TernarySignal::NEGATIVE,
        };
    }

    private function logAudit(string $action, string $entityId, array $metadata = []): void
    {
        (new AuditLogger())->log(Auth::id(), $action, 'marketing_client', $entityId, $metadata);
    }
}
