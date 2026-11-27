<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GrupoUsuarioModel;
use App\Models\PermissaoModel;
use Config\AuthGroups;

class PermissaoService
{
    /**
     * Grupos nativos protegidos contra bloqueio/exclusão, independentemente de alterações no grupo padrão.
     */
    private const GRUPOS_PROTEGIDOS = ['superadmin', 'user'];

    private PermissaoModel $permissaoModel;
    private GrupoUsuarioModel $grupoUsuarioModel;

    public function __construct(
        ?PermissaoModel $permissaoModel = null,
        ?GrupoUsuarioModel $grupoUsuarioModel = null,
    ) {
        $this->permissaoModel    = $permissaoModel ?? model(PermissaoModel::class);
        $this->grupoUsuarioModel = $grupoUsuarioModel ?? model(GrupoUsuarioModel::class);
    }

    /**
     * Lista os grupos configurados no sistema com contagem real de usuários vinculados,
     * busca por nome ou descrição e paginação no servidor (compatível com DataTables).
     *
     * @return array{
     *     grupos: list<array{
     *         identificador: string,
     *         nome: string,
     *         descricao: string,
     *         usuarios_vinculados: int,
     *         situacao: string,
     *         ativo: bool,
     *         protegido: bool
     *     }>,
     *     total: int,
     *     filtrados: int
     * }
     */
    public function listarGrupos(
        int $inicio = 0,
        int $limite = 10,
        string $pesquisa = '',
        string $ordenarPor = 'nome',
        string $direcao = 'asc',
    ): array {
        $camposPermitidos = [
        'identificador',
        'nome',
        'descricao',
        'usuarios_vinculados',
        'situacao',];

        if (! in_array($ordenarPor, $camposPermitidos, true)) {
            throw new \InvalidArgumentException(
                'Campo de ordenação inválido.'
            );
        }

        if (! in_array($direcao, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(
                'Direção de ordenação inválida.'
            );
        }
        $inicio   = max(0, $inicio);
        $limite   = max(1, min($limite, 100));
        $pesquisa = trim($pesquisa);
        $todos = $this->obterGrupos();

        usort(
            $todos,
            static function (
                array $a,
                array $b
            ) use (
                $ordenarPor,
                $direcao
            ): int {
                if ($ordenarPor === 'usuarios_vinculados') {
                    $comparacao = $a[$ordenarPor] <=> $b[$ordenarPor];
                } else {
                    $comparacao = strcasecmp(
                        (string) $a[$ordenarPor],
                        (string) $b[$ordenarPor]
                    );
                }

                if ($comparacao === 0) {
                    return strcmp(
                        $a['identificador'],
                        $b['identificador']
                    );
                }

                return $direcao === 'desc'
                ? -$comparacao
                : $comparacao;
            }
        );

        $filtrados = $pesquisa === ''
            ? $todos
            : array_values(array_filter(
                $todos,
                static fn (array $grupo): bool => mb_stripos($grupo['nome'], $pesquisa) !== false
                    || mb_stripos($grupo['descricao'], $pesquisa) !== false
                    || mb_stripos($grupo['identificador'], $pesquisa) !== false
            ));

        $pagina = array_slice($filtrados, $inicio, $limite);

        return [
            'grupos'    => array_values($pagina),
            'total'     => count($todos),
            'filtrados' => count($filtrados),
        ];
    }

    /**
     * Retorna todas as permissões ativas ordenadas por identificador,
     * sincronizando automaticamente o catálogo de código com a base.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPermissoes(): array
    {
        $this->sincronizarCatalogo();

        return $this->permissaoModel->listarTodasAtivas();
    }

    /**
     * Busca uma permissão ativa pelo seu ID.
     *
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->permissaoModel->find($id);
    }

    /**
     * Sincroniza as permissões de código (AuthGroups.php) com o banco de dados
     * e garante a mescla transparente em memória sem sobrescrever settings.
     *
     * @return array<string, string> Mapa consolidado [identificador => descricao]
     */
    public function sincronizarCatalogo(): array
    {
        $permissoesCodigo = config(AuthGroups::class)->permissions;

        foreach ($permissoesCodigo as $identificador => $descricao) {
            $existente = $this->permissaoModel->buscarPorIdentificador($identificador);

            if ($existente === null) {
                $this->permissaoModel->insert([
                    'identificador' => $identificador,
                    'descricao'     => $descricao,
                    'origem'        => 'sistema',
                ]);
            }
        }

        $settings = setting();
        if (is_object($settings)) {
            if (method_exists($settings, 'forget')) {
                $settings->forget('AuthGroups.permissions');
            } elseif (method_exists($settings, 'delete')) {
                $settings->delete('AuthGroups.permissions');
            }
        }

        $authGroups = config(AuthGroups::class);
        if ($authGroups instanceof AuthGroups) {
            $authGroups->carregarPermissoes();
        }

        return $this->permissaoModel->obterMapaCatalogo();
    }

    private function obterGrupos(): array
    {
        $authGroups = config(AuthGroups::class);

        $definidos = setting('AuthGroups.groups');

        $definidos = is_array($definidos)
        ? $definidos
        : [];

        $gruposProtegidos = array_values(array_unique([
        ...self::GRUPOS_PROTEGIDOS,
        (string) $authGroups->defaultGroup,
        ]));

        $contagens = $this->grupoUsuarioModel
        ->contarUsuariosPorGrupo();

        $todos = [];

        foreach ($definidos as $identificador => $dados) {
            $slug = (string) $identificador;

            $todos[] = [
            'identificador' => $slug,
            'nome' => (string) ($dados['title'] ?? $slug),
            'descricao' => (string) ($dados['description'] ?? ''),
            'usuarios_vinculados' => $contagens[$slug] ?? 0,
            'situacao' => 'ativo',
            'ativo' => true,
            'protegido' => in_array(
                $slug,
                $gruposProtegidos,
                true
            ),
            ];
        }

        return $todos;
    }

    public function buscarGrupoPorIdentificador(
        string $identificador
    ): ?array {
        foreach ($this->obterGrupos() as $grupo) {
            if ($grupo['identificador'] === $identificador) {
                return $grupo;
            }
        }

        return null;
    }
}
