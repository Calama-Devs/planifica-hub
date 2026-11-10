<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Menu extends BaseConfig
{
    /**
        * Catálago centralizado dos itens do menu principal
        *
        * Campos de cada item:
        * titulo: Texto exibidi no menu
        * rota: Nome da rota 'as' declarada em app/Config/Routes.php ou caminho relativo
        * padraoAtivo: Padrão avaliado por url_is() para destacar a página ativa
        * icone: Classes CSS Ícone
        * permisao: null para qualquer usuário logado, ou string/lits de permissões do Shield
        */
    public array $itens = [

        [
            'titulo'        => 'Dashboard',
            'rota'          => 'dashboard',
            'padraoAtivo'   => '/',
            'icone'         => 'mdi mdi-home text-primary',
            'permissao'     => null,
        ],

         [
            'titulo'        => 'Sobre',
            'rota'          => 'sobre',
            'padraoAtivo'   => 'sobre*',
            'icone'         => 'mdi mdi-information-outline text-info',
            'permissao'     => null,
        ],

        [
            'categoria'     => 'Administração',
            'titulo'        => 'Permissões',
            'rota'          => 'admin/permissoes',
            'padraoAtivo'   => 'admin/permissoes*',
            'icone'         => 'mdi mdi-shield-key-outline text-warning',
            'permissao'     => 'acessos.administrar',
        ]
    ];
}
