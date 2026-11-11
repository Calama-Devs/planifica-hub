<?php

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use Config\Menu;
use Throwable;

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
        $ultimaCategoria = null;

        foreach ($this->menuConfig->itens as $item) {
            if (! $this->usuarioPossuiAcesso($usuario, $item['permissao'])) {
                continue;
            }

            $categoriaItem = isset($item['categoria']) ? trim($item['categoria']) : null;
            $exibirCategoria = null;

            if ($categoriaItem !== null && $categoriaItem !== '' && $categoriaItem !== $ultimaCategoria) {
                $exibirCategoria = $categoriaItem;
                $ultimaCategoria = $categoriaItem;
            }

            $visiveis[] = [
                'categoria' => $exibirCategoria,
                'titulo'    => $item['titulo'],
                'url'       => $this->resolverUrl($item['rota']),
                'icone'     => $item['icone'],
                'ativo'     => url_is($item['padraoAtivo']),
            ];
        }

        return $visiveis;
    }

    private function resolverUrl(string $rota): string
    {
        try {
            return url_to($rota);
        } catch (Throwable) {
            return site_url($rota);
        }
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
