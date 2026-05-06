<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Controller;
use Browser\Core\Csrf;
use Browser\Core\Request;
use Browser\Core\Response;
use Browser\Core\Session;
use Browser\Services\SearchService;

final class SearchController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('search/index', [
            'title' => 'Buscador',
            'query' => '',
            'results' => [],
        ]);
    }

    public function search(Request $request): void
    {
        if (!Csrf::validate((string)$request->post('_csrf_token'))) {
            Session::flash('error', 'Token CSRF inválido.');
            Response::redirect('/search');
        }

        $query = trim((string)$request->post('q'));

        $this->view('search/index', [
            'title' => 'Buscador',
            'query' => $query,
            'results' => (new SearchService())->search($query),
        ]);
    }
}
