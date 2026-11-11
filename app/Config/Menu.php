<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Menu extends BaseConfig
{
    /**
        * Catálogo centralizado dos itens do menu principal
        *
        * Campos de cada item:
        *categoria: (opcional) Título da seção exibido antes do item
        * titulo: Texto exibido no menu
        * rota: Nome da rota 'as' declarada em app/Config/Routes.php ou caminho relativo
        * padraoAtivo: Padrão avaliado por url_is() para destacar a página ativa
        * icone: Classes CSS Ícone
        * permissao: null para qualquer usuário logado, ou string/lista de permissões do Shield
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
            'rota'          => 'permissoes.index',
            'padraoAtivo'   => 'admin/permissoes*',
            'icone'         => 'mdi mdi-shield-key-outline text-warning',
            'permissao'     => 'acessos.administrar',
        ],
    ];
}
