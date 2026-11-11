<?php

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use Config\Menu;

class MenuService
{
    private Menu $menuConfig;

    public function __construct(?Menu $menuConfig = null)
    {
        $this->menuConfig = $menuConfig ?? config(Menu::class);
    }

    public function itensVisiveis(?User $usuario = null): array
    {
        if ($usuario === null) {
            if (! auth()->loggedIn()) {
                return [];
            }

            $usuario = auth()->user();
        }

        if ($usuario === null) {
            return [];
        }

        return $this->filtrarItensVisiveis($usuario);
    }

    public function filtrarItensVisiveis(User $usuario): array
    {
        $visiveis = [];

        foreach ($this->menuConfig->itens as $item) {
            if (! $this->usuarioPossuiAcesso($usuario, $item['permissao'])) {
                continue;
            }

            $url = route_to($item['rota']) !== false
                ? url_to($item['rota'])
                : site_url($item['rota']);

            $visiveis[] = [
                'categoria' => $item['categoria'] ?? null,
                'titulo'    => $item['titulo'],
                'url'       => $url,
                'icone'     => $item['icone'],
                'ativo'     => url_is($item['padraoAtivo']),
            ];
        }

        return $visiveis;
    }

    private function usuarioPossuiAcesso(User $usuario, string|array|null $permissaoExigida): bool
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
