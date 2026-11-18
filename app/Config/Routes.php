<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Home::index', ['as' => 'dashboard']);
    $routes->get('sobre', 'Home::sobre', ['as' => 'sobre']);
    $routes->post('sobre', 'Home::testarValidacao', ['as' => 'sobre.testar']);
    $routes->get('perfil', 'PerfilController::index', ['as' => 'perfil']);
    $routes->post('perfil', 'PerfilController::atualizar', ['as' => 'perfil.atualizar']);

    $routes->group('admin/permissoes', ['filter' => 'permission:acessos.administrar'], static function (RouteCollection $routes): void {
        $routes->get('/', 'PermissaoController::index', ['as' => 'permissoes.index']);
    });

    // Administração de acessos: o filtro do grupo (session) soma com o permission da rota.
    $routes->group('admin/acessos', ['filter' => 'permission:acessos.administrar'], static function (RouteCollection $routes): void {
        $routes->get('/', 'AcessoController::index', ['as' => 'acessos.index']);
        $routes->get('(:num)/permissoes', 'AcessoController::permissoes/$1', ['as' => 'acessos.permissoes']);
        $routes->post('(:num)/permissoes', 'AcessoController::salvar/$1', ['as' => 'acessos.permissoes.salvar']);
    });

    $routes->group(
        'admin/contas',
        [],
        static function (RouteCollection $routes): void {
            $routes->get('/', 'ContaController::index', ['as' => 'contas.index']);
            $routes->get('tabela', 'ContaController::listar', ['as' => 'contas.tabela']);
            $routes->get('(:num)', 'ContaController::consultar/$1', ['as' => 'contas.consultar']);
            $routes->post('(:num)', 'ContaController::atualizar/$1', ['as' => 'contas.atualizar']);
        },
    );
});

service('auth')->routes($routes, ['except' => ['logout']]);
$routes->post('logout', '\CodeIgniter\Shield\Controllers\LoginController::logoutAction', ['as' => 'logout']);
