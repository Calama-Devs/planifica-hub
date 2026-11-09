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
    ];

    public function itensVisiveis(): array
    {
        if (! auth()->loggedIn()) {
            return [];
        }

        $usuario = auth()->user();
        if ($usuario === null) {
            return [];
        }

        $visiveis = [];

        foreach ($this->itens as $item) {
            if (! $this->usuarioPossuiAcesso($usuario, $item['permissao'])) {
                continue;
            }

            $url = route_to($item['rota']) !== false
                ? url_to($item['rota'])
                : site_url($item['rota']);

            $visiveis[] = [
                'titulo' => $item['titulo'],
                'url'    => $url,
                'icone'  => $item['icone'],
                'ativo'  => url_is($item['padraoAtivo']),
            ];
        }

        return $visiveis;
    }

    public function usuarioPossuiAcesso($usuario, string|array|null $permissaoExigida): bool
    {
        if ($permissaoExigida === null || $permissaoExigida === '') {
            return true;
        }

        foreach ((array) $permissaoExigida as $permissao) {
            if ($usuario->can($permissao)) {
                return true;
            }
        }

        return false;
    }
}
