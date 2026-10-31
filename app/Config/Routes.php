<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => ['session', 'redefinicaoSenha']], static function (RouteCollection $routes): void {
    $routes->get('/', 'Home::index', ['as' => 'dashboard']);
    $routes->get('sobre', 'Home::sobre', ['as' => 'sobre']);
    $routes->get('perfil/alterar-senha', 'RedefinirSenhaController::alterarPeloPerfil', ['as' => 'alterar-senha-perfil']);
    $routes->post('perfil/alterar-senha', 'RedefinirSenhaController::atualizarPeloPerfil', ['as' => 'atualizar-senha-perfil']);
});

service('auth')->routes($routes, ['except' => ['logout']]);
$routes->post('logout', '\CodeIgniter\Shield\Controllers\LoginController::logoutAction', ['as' => 'logout']);

$routes->get('forgot-password', 'RedefinirSenhaController::solicitar', ['filter' => 'redefinicaoSenha']);
$routes->get('reset-password', 'RedefinirSenhaController::redefinir', ['filter' => ['session', 'redefinicaoSenha']]);
$routes->post('reset-password', 'RedefinirSenhaController::atualizar', ['filter' => ['session', 'redefinicaoSenha']]);
