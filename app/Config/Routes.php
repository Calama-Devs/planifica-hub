<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->post('perfil/alterar-senha', 'RedefinirSenhaController::atualizarPeloPerfil', [
    'as'     => 'atualizar-senha-perfil',
    'filter' => 'session',
]);

$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Home::index', ['as' => 'dashboard']);
    $routes->get('sobre', 'Home::sobre', ['as' => 'sobre']);

    // Alteração de senha pelo perfil autenticado.
    $routes->post('sobre', 'Home::testarValidacao', ['as' => 'sobre.testar']);
    $routes->get('perfil', 'PerfilController::index', ['as' => 'perfil']);
    $routes->post('perfil', 'PerfilController::atualizar', ['as' => 'perfil.atualizar']);

    $routes->group('admin/permissoes', ['filter' => 'permission:acessos.administrar'], static function (RouteCollection $routes): void {
        $routes->get('/', 'PermissaoController::index', ['as' => 'permissoes.index']);
    });

    // Gerenciamento de usuários: o filtro session do grupo soma com permission da rota.
    $routes->group('admin/acessos', ['filter' => 'permission:acessos.administrar'], static function (RouteCollection $routes): void {
        $routes->get('/', 'AcessoController::index', ['as' => 'acessos.index']);
        $routes->get('listar', 'AcessoController::listar', ['as' => 'acessos.listar']);
    });
});

service('auth')->routes($routes, ['except' => ['logout', 'magic-link']]);
$routes->get('login/magic-link', '\\CodeIgniter\\Shield\\Controllers\\MagicLinkController::loginView', ['as' => 'magic-link']);
$routes->post('login/magic-link', '\\CodeIgniter\\Shield\\Controllers\\MagicLinkController::loginAction');
$routes->get('login/verify-magic-link', '\\App\\Controllers\\MagicLinkController::verify', ['as' => 'verify-magic-link']);
$routes->post('logout', '\CodeIgniter\Shield\Controllers\LoginController::logoutAction', ['as' => 'logout']);

// Recuperação de senha por link temporário.
$routes->get('recuperar-senha', 'RedefinirSenhaController::solicitar', ['as' => 'solicitar-redefinicao']);
$routes->post('recuperar-senha', 'RedefinirSenhaController::enviar', ['as' => 'enviar-redefinicao', 'filter' => 'auth-rates']);
$routes->get('recuperar-senha/enviado', 'RedefinirSenhaController::confirmacao', ['as' => 'confirmacao-redefinicao']);
$routes->get('recuperar-senha/verificar', 'RedefinirSenhaController::verificar', ['as' => 'verificar-redefinicao']);
$routes->get('reset-password', 'RedefinirSenhaController::redefinir');
$routes->post('reset-password', 'RedefinirSenhaController::atualizar');
$routes->post('reset-password/cancelar', 'RedefinirSenhaController::cancelar');

// Finalização de cadastro por convite.
$routes->get('finalizar-cadastro', 'FinalizarCadastroController::index', ['as' => 'finalizar_cadastro']);
$routes->post('finalizar-cadastro', 'FinalizarCadastroController::concluir', ['as' => 'finalizar_cadastro.concluir', 'filter' => 'auth-rates']);
$routes->get('convite/finalizar', 'FinalizarCadastroController::index', ['as' => 'convite.finalizar']);
