<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Auth;
use Browser\Core\Controller;
use Browser\Core\Request;
use Browser\Models\UserRole;
use Browser\Services\SearchService;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $query = trim((string) $request->input('q', ''));
        if ($query !== '') {
            $searchData = (new SearchService())->search($query, $request);
            $this->view('search/index', [
                'title' => 'Buscador',
                'query' => $query,
                'results' => $searchData['results'],
                'directNavigation' => $searchData['directNavigation'],
            ]);

            return;
        }

        $user = Auth::user();

        $this->view('home', [
            'title' => 'Browser - Buscador independiente',
            'query' => '',
            'results' => [],
            'suggestedPages' => (new SearchService())->suggestedPages(),
            'isAdmin' => $user !== null && UserRole::userHasRole((int) $user['id'], 'admin'),
            'user' => $user,
        ]);
    }

    public function about(Request $request): void
    {
        $this->view('about', [
            'title' => 'Acerca de Browser',
        ]);
    }
}
