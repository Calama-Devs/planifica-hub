<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Home::index', ['as' => 'dashboard']);
    $routes->get('sobre', 'Home::sobre', ['as' => 'sobre']);

    // Alteração de senha pelo perfil autenticado.
    $routes->post('perfil/alterar-senha', 'RedefinirSenhaController::atualizarPeloPerfil', ['as' => 'atualizar-senha-perfil']);

    $routes->post('sobre', 'Home::testarValidacao', ['as' => 'sobre.testar']);
    $routes->get('perfil', 'PerfilController::index', ['as' => 'perfil']);
    $routes->post('perfil', 'PerfilController::atualizar', ['as' => 'perfil.atualizar']);

    // A autorização específica e a senha do operador são conferidas pelo Service.
    $routes->post('admin/contas/(:segment)/desativar', 'EstadoContaController::desativar/$1', ['as' => 'contas.desativar']);
    $routes->post('admin/contas/(:segment)/reativar', 'EstadoContaController::reativar/$1', ['as' => 'contas.reativar']);

    $routes->group('admin/permissoes', ['filter' => 'permission:acessos.administrar'], static function (RouteCollection $routes): void {
        $routes->get('/', 'PermissaoController::index', ['as' => 'permissoes.index']);
    });
});

service('auth')->routes($routes, ['except' => ['logout']]);
$routes->post('logout', '\CodeIgniter\Shield\Controllers\LoginController::logoutAction', ['as' => 'logout']);

// Recuperação de senha por link temporário.
$routes->get('recuperar-senha', 'RedefinirSenhaController::solicitar', ['as' => 'solicitar-redefinicao']);
$routes->post('recuperar-senha', 'RedefinirSenhaController::enviar', ['as' => 'enviar-redefinicao', 'filter' => 'auth-rates']);
$routes->get('recuperar-senha/enviado', 'RedefinirSenhaController::confirmacao', ['as' => 'confirmacao-redefinicao']);
$routes->get('recuperar-senha/verificar', 'RedefinirSenhaController::verificar', ['as' => 'verificar-redefinicao']);
$routes->get('reset-password', 'RedefinirSenhaController::redefinir');
$routes->post('reset-password', 'RedefinirSenhaController::atualizar');
$routes->post('reset-password/cancelar', 'RedefinirSenhaController::cancelar');
