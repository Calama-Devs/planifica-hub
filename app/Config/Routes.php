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

    $routes->group('admin/permissoes', ['filter' => 'permission:acessos.administrar'], static function (RouteCollection $routes): void {
        $routes->get('/', 'PermissaoController::index', ['as' => 'permissoes.index']);
    });

    // Administração de acessos: o filtro do grupo (session) soma com o permission da rota.
    $routes->group('admin/acessos', ['filter' => 'permission:acessos.administrar'], static function (RouteCollection $routes): void {
        $routes->get('/', 'AcessoController::index', ['as' => 'acessos.index']);
        $routes->get('(:num)/permissoes', 'AcessoController::permissoes/$1', ['as' => 'acessos.permissoes']);
        $routes->post('(:num)/permissoes', 'AcessoController::salvar/$1', ['as' => 'acessos.permissoes.salvar']);
    });

    // A área de acessos consome estes endpoints JSON; Controller e Service repetem a autorização.
    $routes->get('admin/contas/(:num)', 'ContaController::consultar/$1', [
        'as' => 'contas.consultar',
        'filter' => 'permissao-json:contas.editar',
    ]);
    $routes->post('admin/contas/(:num)', 'ContaController::atualizar/$1', [
        'as' => 'contas.atualizar',
        'filter' => 'permissao-json:contas.editar',
    ]);
});

// Confirmação pública por link enviada ao novo endereço; o GET só mostra o formulário.
$routes->get('confirmar-troca-email', 'ConfirmacaoEmailContaController::formulario', [
    'as' => 'contas.email.confirmar.form',
]);
$routes->post('confirmar-troca-email', 'ConfirmacaoEmailContaController::confirmar', [
    'as' => 'contas.email.confirmar',
]);

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
