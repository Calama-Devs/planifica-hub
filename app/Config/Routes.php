<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index', ['filter' => 'session']);

service('auth')->routes($routes, ['except' => ['logout']]);
$routes->post('logout', '\CodeIgniter\Shield\Controllers\LoginController::logoutAction', ['as' => 'logout']);

$routes->get('forgot-password', 'RedefinirSenhaController::solicitar');
$routes->get('reset-password', 'RedefinirSenhaController::redefinir', ['filter' => 'session']);
$routes->post('reset-password', 'RedefinirSenhaController::atualizar', ['filter' => 'session']);
