<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Home::index', ['as' => 'dashboard']);
    $routes->get('sobre', 'Home::sobre', ['as' => 'sobre']);
    $routes->post('sobre', 'Home::testarValidacao', ['as' => 'sobre.testar']);

    $routes->group('admin/permissoes', ['filter' => 'permission:acessos.administrar'], static function (RouteCollection $routes): void {
        $routes->get('/', 'PermissaoController::index', ['as' => 'permissoes.index']);
        $routes->get('nova', 'PermissaoController::nova', ['as' => 'permissoes.nova']);
        $routes->post('salvar', 'PermissaoController::salvar', ['as' => 'permissoes.salvar']);
        $routes->get('editar/(:num)', 'PermissaoController::editar/$1', ['as' => 'permissoes.editar']);
        $routes->post('atualizar/(:num)', 'PermissaoController::atualizar/$1', ['as' => 'permissoes.atualizar']);
    });

    // Administração de acessos: o filtro do grupo (session) soma com o permission da rota.
    $routes->group('admin/acessos', ['filter' => 'permission:acessos.administrar'], static function (RouteCollection $routes): void {
        $routes->get('/', 'AcessoController::index', ['as' => 'acessos.index']);
        $routes->get('(:num)/permissoes', 'AcessoController::permissoes/$1', ['as' => 'acessos.permissoes']);
        $routes->post('(:num)/permissoes/conceder', 'AcessoController::conceder/$1', ['as' => 'acessos.permissoes.conceder']);
        $routes->post('(:num)/permissoes/revogar', 'AcessoController::revogar/$1', ['as' => 'acessos.permissoes.revogar']);
    });
});

service('auth')->routes($routes, ['except' => ['logout']]);
$routes->post('logout', '\CodeIgniter\Shield\Controllers\LoginController::logoutAction', ['as' => 'logout']);
