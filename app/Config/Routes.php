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

    $routes->post('admin/contas/(:segment)/desativar', 'EstadoContaController::desativar/$1', [
        'as' => 'contas.desativar',
        'filter' => 'permission:contas.desativar',
    ]);
    $routes->post('admin/contas/(:segment)/reativar', 'EstadoContaController::reativar/$1', [
        'as' => 'contas.reativar',
        'filter' => 'permission:contas.reativar',
    ]);

    $routes->group('admin/permissoes', ['filter' => 'permission:acessos.administrar'], static function (RouteCollection $routes): void {
        $routes->get('/', 'PermissaoController::index', ['as' => 'permissoes.index']);
        $routes->get('listar', 'PermissaoController::listarGrupos', ['as' => 'permissoes.grupos.listar']);
    });

    // A tela aceita uma das permissões; a listagem continua restrita à administração de acessos.
    $routes->group('admin/acessos', static function (RouteCollection $routes): void {
        $routes->get('/', 'AcessoController::index', [
            'as'     => 'acessos.index',
            'filter' => 'permission:contas.criar,acessos.administrar',
        ]);
        $routes->get('listar', 'AcessoController::listar', [
            'as'     => 'acessos.listar',
            'filter' => 'permission:acessos.administrar',
        ]);
    });

    $routes->post('admin/convites', 'ConviteController::emitir', ['as' => 'convites.emitir', 'filter' => 'permission:contas.criar']);
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

// Finalização de cadastro por convite.
$routes->get('finalizar-cadastro', 'ConviteController::index', ['as' => 'finalizar_cadastro']);
$routes->post('finalizar-cadastro', 'ConviteController::concluir', ['as' => 'finalizar_cadastro.concluir', 'filter' => 'auth-rates']);
$routes->get('convite/finalizar', 'ConviteController::index', ['as' => 'convite.finalizar']);
