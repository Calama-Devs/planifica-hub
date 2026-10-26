<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index', ['filter' => ['session', 'redefinicaoSenha']]);

service('auth')->routes($routes, ['except' => ['logout']]);
$routes->post('logout', '\CodeIgniter\Shield\Controllers\LoginController::logoutAction', ['as' => 'logout']);

$routes->get('forgot-password', 'RedefinirSenhaController::solicitar', ['filter' => 'redefinicaoSenha']);
$routes->get('reset-password', 'RedefinirSenhaController::redefinir', ['filter' => ['session', 'redefinicaoSenha']]);
$routes->post('reset-password', 'RedefinirSenhaController::atualizar', ['filter' => ['session', 'redefinicaoSenha']]);
