<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PermissaoModel;
use CodeIgniter\Database\BaseConnection;
use Config\AuthGroups;

class PermissaoService
{
    private PermissaoModel $permissaoModel;
    private BaseConnection $banco;

    public function __construct(
        ?PermissaoModel $permissaoModel = null,
        ?BaseConnection $banco = null,
    ) {
        $this->permissaoModel = $permissaoModel ?? model(PermissaoModel::class);
        $this->banco          = $banco ?? db_connect();
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
    ): array {
        $inicio   = max(0, $inicio);
        $limite   = max(1, min($limite, 100));
        $pesquisa = trim($pesquisa);

        $authGroups       = config(AuthGroups::class);
        $definidos        = setting('AuthGroups.groups');
        $definidos        = is_array($definidos) ? $definidos : [];
        $gruposProtegidos = array_values(array_unique(['superadmin', (string) $authGroups->defaultGroup]));
        $contagens        = $this->contarUsuariosPorGrupo();

        $todos = [];

        foreach ($definidos as $identificador => $dados) {
            $slug = (string) $identificador;

            $todos[] = [
                'identificador'       => $slug,
                'nome'                => (string) ($dados['title'] ?? $slug),
                'descricao'           => (string) ($dados['description'] ?? ''),
                'usuarios_vinculados' => $contagens[$slug] ?? 0,
                'situacao'            => 'ativo',
                'ativo'               => true,
                'protegido'           => in_array($slug, $gruposProtegidos, true),
            ];
        }

        usort(
            $todos,
            static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome'])
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

    /**
     * Conta quantos usuários não removidos estão vinculados a cada grupo na tabela do Shield.
     *
     * @return array<string, int>
     */
    private function contarUsuariosPorGrupo(): array
    {
        if (! $this->banco->tableExists('auth_groups_users') || ! $this->banco->tableExists('users')) {
            return [];
        }

        $linhas = $this->banco->table('auth_groups_users agu')
            ->select('agu.group')
            ->select('COUNT(DISTINCT agu.user_id) AS total', false)
            ->join('users u', 'u.id = agu.user_id', 'inner')
            ->where('u.deleted_at', null)
            ->groupBy('agu.group')
            ->get()
            ->getResultArray();

        $contagens = [];

        foreach ($linhas as $linha) {
            $grupo             = (string) ($linha['group'] ?? '');
            $contagens[$grupo] = (int) ($linha['total'] ?? 0);
        }

        return $contagens;
    }
}
