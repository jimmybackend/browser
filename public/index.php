<?php

declare(strict_types=1);

use Browser\Controllers\AdminController;
use Browser\Controllers\AuthController;
use Browser\Controllers\DashboardController;
use Browser\Controllers\HomeController;
use Browser\Controllers\MailController;
use Browser\Controllers\ProfileController;
use Browser\Controllers\MarketingController;
use Browser\Controllers\SearchController;
use Browser\Core\Env;
use Browser\Core\Router;
use Browser\Core\Session;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

Env::load(BASE_PATH);
Session::start();

$router = new Router();

$router->get('/', [HomeController::class, 'index']);

$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/profile', [ProfileController::class, 'index']);
$router->post('/profile', [ProfileController::class, 'update']);

$router->get('/mail', [MailController::class, 'index']);
$router->get('/search', [SearchController::class, 'index']);
$router->post('/search', [SearchController::class, 'search']);

$router->get('/marketing', [MarketingController::class, 'index']);
$router->get('/admin', [AdminController::class, 'index']);
$router->get('/admin/users', [AdminController::class, 'users']);
$router->get('/admin/users/show', [AdminController::class, 'showUser']);
$router->get('/admin/users/roles', [AdminController::class, 'editUserRoles']);
$router->post('/admin/users/roles', [AdminController::class, 'updateUserRoles']);

$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
