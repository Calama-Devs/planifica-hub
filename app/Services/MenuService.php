<?php

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use Config\Menu;
use CodeIgniter\Router\Exceptions\RouterException;

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

        foreach ($this->menuConfig->itens as $indice => $item) {
            if (! $this->itemValido($item, $indice)) {
                continue;
            }

            if (! $this->usuarioPossuiAcesso($usuario, $item['permissao'] ?? null)) {
                continue;
            }

            $url = $this->resolverUrl($item['rota']);

            if ($url === null) {
                continue;
            }

            $categoriaItem = isset($item['categoria']) && is_string($item['categoria']) ? trim($item['categoria']) : null;
            $exibirCategoria = null;

            if ($categoriaItem !== null && $categoriaItem !== '' && $categoriaItem !== $ultimaCategoria) {
                $exibirCategoria = $categoriaItem;
                $ultimaCategoria = $categoriaItem;
            }

            $visiveis[] = [
                'categoria' => $exibirCategoria,
                'titulo'    => $item['titulo'],
                'url'       => $url,
                'icone'     => $item['icone'],
                'ativo'     => url_is($item['padraoAtivo']),
            ];
        }

        return $visiveis;
    }

    private function itemValido(array $item, int $indice): bool
    {
        $camposObrigatorios = ['titulo', 'rota', 'padraoAtivo', 'icone'];

        foreach ($camposObrigatorios as $campo) {
            if (! isset($item[$campo]) || ! is_string($item[$campo]) || trim($item[$campo]) === '') {
                log_message(
                    'warning',
                    'MenuService: item de menu no indice {indice} ignorado por ausência ou invalidez do campo obrigatório "{campo}".',
                    [
                        'indice' => $indice,
                        'campo'  => $campo,
                    ]
                );

                return false;
            }
        }

        return true;
    }

    private function resolverUrl(string $rota): ?string
    {
        try {
            return url_to($rota);
        } catch (RouterException $e) {
            log_message(
                'warning',
                'MenuService: falha ao resolver rota nomeada "{rota}". Detalhe: {erro}',
                [
                    'rota' => $rota,
                    'erro' => $e->getMessage(),
                ]
            );

            return null;
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
