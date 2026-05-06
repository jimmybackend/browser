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
use Browser\Models\MarketingCampaign;
use Browser\Models\MarketingClient;
use Browser\Models\UserRole;
use Browser\Services\AuditLogger;
use Browser\Services\TernarySignalService;

final class MarketingCampaignController extends Controller
{
    private const FULL_ACCESS_ROLES = ['admin', 'marketing_manager'];
    private const READ_ONLY_ROLES = ['sales_agent'];
    private const CHANNELS = ['seo', 'sem', 'social', 'email', 'content', 'display', 'other'];

    public function index(Request $request): void { $this->authorizeRead(); $this->view('marketing/campaigns/index', ['title' => 'Campañas de Marketing', 'campaigns' => MarketingCampaign::list(), 'canWrite' => $this->canWrite()]); }
    public function create(Request $request): void { $this->authorizeWrite('/marketing/campaigns'); $this->view('marketing/campaigns/create', ['title' => 'Nueva campaña', 'clients' => MarketingClient::list(1000)]); }

    public function store(Request $request): void
    {
        $this->authorizeWrite('/marketing/campaigns');
        $this->validateCsrfOrRedirect($request, '/marketing/campaigns/create');
        [$data, $errors] = $this->validatePayload($request);
        if ($errors !== []) { Session::flash('error', implode(' ', $errors)); Response::redirect('/marketing/campaigns/create'); }
        try {
            $id = MarketingCampaign::create($data);
            $this->logAudit('marketing_campaign_created', (string) $id, ['status' => $data['status']]);
            $this->recordHealthSignal($id, $data['status']);
            Session::flash('success', 'Campaña creada correctamente.');
            Response::redirect('/marketing/campaigns/show?id=' . $id);
        } catch (\Throwable $exception) {
            Session::flash('error', 'No se pudo crear la campaña.'); Response::redirect('/marketing/campaigns/create');
        }
    }

    public function show(Request $request): void
    {
        $this->authorizeRead();
        $campaign = MarketingCampaign::find((int) $request->query('id', 0));
        if (!$campaign) { Session::flash('error', 'Campaña no encontrada.'); Response::redirect('/marketing/campaigns'); }
        $this->view('marketing/campaigns/show', ['title' => 'Detalle de campaña', 'campaign' => $campaign, 'healthSignal' => $this->statusToSignal($campaign['status']), 'canWrite' => $this->canWrite()]);
    }

    public function edit(Request $request): void
    {
        $this->authorizeWrite('/marketing/campaigns');
        $campaign = MarketingCampaign::find((int) $request->query('id', 0));
        if (!$campaign) { Session::flash('error', 'Campaña no encontrada.'); Response::redirect('/marketing/campaigns'); }
        $this->view('marketing/campaigns/edit', ['title' => 'Editar campaña', 'campaign' => $campaign, 'clients' => MarketingClient::list(1000), 'channels' => self::CHANNELS]);
    }

    public function update(Request $request): void
    {
        $this->authorizeWrite('/marketing/campaigns');
        $this->validateCsrfOrRedirect($request, '/marketing/campaigns');
        $id = (int) $request->post('id', 0);
        if (!MarketingCampaign::find($id)) { Session::flash('error', 'Campaña no encontrada.'); Response::redirect('/marketing/campaigns'); }
        [$data, $errors] = $this->validatePayload($request);
        if ($errors !== []) { Session::flash('error', implode(' ', $errors)); Response::redirect('/marketing/campaigns/edit?id=' . $id); }
        try { MarketingCampaign::update($id, $data); $this->logAudit('marketing_campaign_updated', (string) $id, ['status' => $data['status']]); $this->recordHealthSignal($id, $data['status']); Session::flash('success', 'Campaña actualizada correctamente.'); } catch (\Throwable $exception) { Session::flash('error', 'No se pudo actualizar la campaña.'); }
        Response::redirect('/marketing/campaigns/show?id=' . $id);
    }

    public function status(Request $request): void
    {
        $this->authorizeWrite('/marketing/campaigns');
        $this->validateCsrfOrRedirect($request, '/marketing/campaigns');
        $id = (int) $request->post('id', 0); $status = trim((string) $request->post('status'));
        if (!MarketingCampaign::find($id) || !in_array($status, MarketingCampaign::VALID_STATUSES, true)) { Session::flash('error', 'Solicitud inválida para cambio de estado.'); Response::redirect('/marketing/campaigns'); }
        try { MarketingCampaign::updateStatus($id, $status); $this->logAudit('marketing_campaign_status_changed', (string) $id, ['status' => $status]); $this->recordHealthSignal($id, $status); Session::flash('success', 'Estado actualizado correctamente.'); } catch (\Throwable $exception) { Session::flash('error', 'No se pudo cambiar el estado.'); }
        Response::redirect('/marketing/campaigns/show?id=' . $id);
    }

    public function delete(Request $request): void
    {
        $this->authorizeWrite('/marketing/campaigns');
        $this->validateCsrfOrRedirect($request, '/marketing/campaigns');
        $id = (int) $request->post('id', 0);
        if (!MarketingCampaign::find($id)) { Session::flash('error', 'Campaña no encontrada.'); Response::redirect('/marketing/campaigns'); }
        try { MarketingCampaign::delete($id); $this->logAudit('marketing_campaign_deleted', (string) $id); Session::flash('success', 'Campaña eliminada correctamente.'); } catch (\Throwable $exception) { Session::flash('error', 'No se pudo eliminar la campaña.'); }
        Response::redirect('/marketing/campaigns');
    }

    private function validatePayload(Request $request): array
    {
        $data = ['client_id' => (int) $request->post('client_id', 0), 'name' => trim((string) $request->post('name')), 'description' => trim((string) $request->post('description')), 'channel' => trim((string) $request->post('channel', 'other')), 'budget' => trim((string) $request->post('budget')), 'start_date' => trim((string) $request->post('start_date')), 'end_date' => trim((string) $request->post('end_date')), 'status' => trim((string) $request->post('status', 'draft'))];
        $errors = [];
        if ($data['client_id'] <= 0 || MarketingClient::find($data['client_id']) === null) { $errors[] = 'Cliente inválido para la campaña.'; }
        if ($data['name'] === '' || mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 190) { $errors[] = 'Nombre inválido (2-190 caracteres).'; }
        if (!in_array($data['channel'], self::CHANNELS, true)) { $errors[] = 'Canal inválido.'; }
        if ($data['budget'] !== '' && !is_numeric($data['budget'])) { $errors[] = 'Presupuesto inválido.'; }
        $data['budget'] = $data['budget'] === '' ? null : (string) round((float) $data['budget'], 2);
        if ($data['start_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['start_date'])) { $errors[] = 'Fecha de inicio inválida.'; }
        if ($data['end_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['end_date'])) { $errors[] = 'Fecha de fin inválida.'; }
        if ($data['start_date'] !== '' && $data['end_date'] !== '' && $data['start_date'] > $data['end_date']) { $errors[] = 'La fecha de inicio no puede ser mayor a la fecha fin.'; }
        if (!in_array($data['status'], MarketingCampaign::VALID_STATUSES, true)) { $errors[] = 'Estado inválido.'; }

        return [$data, $errors];
    }

    private function canWrite(): bool { $this->requireAuth(); $userId = Auth::id(); if ($userId === null) { return false; } foreach (self::FULL_ACCESS_ROLES as $role) { if (UserRole::userHasRole($userId, $role)) { return true; } } return false; }
    private function authorizeRead(): void { $this->requireAuth(); $userId = Auth::id(); if ($userId === null) { Response::redirect('/login'); } foreach (array_merge(self::FULL_ACCESS_ROLES, self::READ_ONLY_ROLES) as $role) { if (UserRole::userHasRole($userId, $role)) { return; } } http_response_code(403); echo '403 Forbidden'; exit; }
    private function authorizeWrite(string $redirect): void { $this->authorizeRead(); if (!$this->canWrite()) { Session::flash('error', 'No tienes permisos para modificar campañas.'); Response::redirect($redirect); } }
    private function validateCsrfOrRedirect(Request $request, string $redirect): void { if (!Csrf::validate((string) $request->post('_csrf_token'))) { Session::flash('error', 'Token CSRF inválido.'); Response::redirect($redirect); } }
    private function recordHealthSignal(int $id, string $status): void { (new TernarySignalService())->record('campaign_health_signal', $this->statusToSignal($status), 'marketing_campaign', (string) $id, Auth::id(), 'system', null, 'campaign_status_sync', null, ['status' => $status]); }
    private function statusToSignal(string $status): int { return match ($status) { 'active' => TernarySignal::POSITIVE, 'draft' => TernarySignal::NEUTRAL, default => TernarySignal::NEGATIVE, }; }
    private function logAudit(string $action, string $entityId, array $metadata = []): void { (new AuditLogger())->log(Auth::id(), $action, 'marketing_campaign', $entityId, $metadata); }
}
