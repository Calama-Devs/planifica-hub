<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GrupoUsuarioModel;
use App\Models\PermissaoModel;
use Config\AuthGroups;
use Config\Database;
use Throwable;

class PermissaoService
{
    /**
     * Grupos nativos protegidos contra bloqueio/exclusão, independentemente de alterações no grupo padrão.
     */
    private const GRUPOS_PROTEGIDOS = ['superadmin', 'user'];

    private PermissaoModel $permissaoModel;
    private GrupoUsuarioModel $grupoUsuarioModel;
    private ?AuditoriaService $auditoriaService;

    public function __construct(
        ?PermissaoModel $permissaoModel = null,
        ?GrupoUsuarioModel $grupoUsuarioModel = null,
        ?AuditoriaService $auditoriaService = null,
    ) {
        $this->permissaoModel    = $permissaoModel ?? model(PermissaoModel::class);
        $this->grupoUsuarioModel = $grupoUsuarioModel ?? model(GrupoUsuarioModel::class);
        $this->auditoriaService  = $auditoriaService;
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
        string $situacao = 'ativos',
    ): array {
        $inicio   = max(0, $inicio);
        $limite   = max(1, min($limite, 100));
        $pesquisa = trim($pesquisa);
        $situacao = $situacao === 'inativos' ? 'inativos' : 'ativos';

        $authGroups       = config(AuthGroups::class);
        $definidos        = setting('AuthGroups.groups');
        $definidos        = is_array($definidos) ? $definidos : [];
        $inativos         = $this->obterGruposInativos();
        $gruposProtegidos = array_values(array_unique([
            ...self::GRUPOS_PROTEGIDOS,
            (string) $authGroups->defaultGroup,
        ]));
        $contagens        = $this->grupoUsuarioModel->contarUsuariosPorGrupo();

        $todos = [];

        foreach ($definidos as $identificador => $dados) {
            $slug  = (string) $identificador;
            $ativo = ! in_array($slug, $inativos, true);

            if (($situacao === 'ativos' && ! $ativo) || ($situacao === 'inativos' && $ativo)) {
                continue;
            }

            $todos[] = [
                'identificador'       => $slug,
                'nome'                => (string) ($dados['title'] ?? $slug),
                'descricao'           => (string) ($dados['description'] ?? ''),
                'usuarios_vinculados' => $contagens[$slug] ?? 0,
                'situacao'            => $ativo ? 'ativo' : 'inativo',
                'ativo'               => $ativo,
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
     * Reativa um grupo previamente inativado, registrando o evento na auditoria centralizada.
     *
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}
     */
    public function reativarGrupo(string $identificador, int $atorId): array
    {
        $slug = trim($identificador);
        if ($slug === '') {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'GRUPO_IDENTIFICADOR_INVALIDO',
                'mensagem' => 'Identificador de grupo inválido.',
            ];
        }

        $ator = $atorId > 0 ? auth()->getProvider()->findById($atorId) : null;
        if ($ator === null || ! $ator->can('acessos.administrar')) {
            return [
                'sucesso'  => false,
                'tipo'     => 'permissao',
                'codigo'   => 'GRUPO_REATIVACAO_NAO_AUTORIZADA',
                'mensagem' => 'Você não tem permissão para reativar grupos.',
            ];
        }

        $definidos = setting('AuthGroups.groups');
        $definidos = is_array($definidos) ? $definidos : [];

        if (! array_key_exists($slug, $definidos)) {
            return [
                'sucesso'  => false,
                'tipo'     => 'nao_encontrado',
                'codigo'   => 'GRUPO_NAO_ENCONTRADO',
                'mensagem' => 'Grupo não encontrado.',
            ];
        }

        $inativos = $this->obterGruposInativos();
        if (! in_array($slug, $inativos, true)) {
            return [
                'sucesso'  => false,
                'tipo'     => 'regra_negocio',
                'codigo'   => 'GRUPO_JA_ATIVO',
                'mensagem' => 'Este grupo já está ativo.',
            ];
        }

        $novosInativos = array_values(array_filter(
            $inativos,
            static fn (string $item): bool => $item !== $slug
        ));

        $banco     = Database::connect();
        $auditoria = $this->auditoriaService ?? service('auditoriaService');

        $banco->transStart();

        try {
            service('settings')->set('AuthGroups.gruposInativos', $novosInativos);

            $auditoria->registrar([
                'modulo'      => 'permissoes',
                'acao'        => 'reativar_grupo',
                'entidade'    => 'grupo',
                'entidade_id' => $slug,
                'resultado'   => 'sucesso',
                'mensagem'    => 'Grupo reativado com sucesso.',
                'detalhes'    => json_encode([
                    'identificador' => $slug,
                    'nome'          => (string) ($definidos[$slug]['title'] ?? $slug),
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $banco->transComplete();

            if (! $banco->transStatus()) {
                return [
                    'sucesso'  => false,
                    'tipo'     => 'inesperado',
                    'codigo'   => 'GRUPO_REATIVACAO_INDISPONIVEL',
                    'mensagem' => 'Não foi possível reativar o grupo. Tente novamente.',
                ];
            }

            return [
                'sucesso'  => true,
                'tipo'     => 'sucesso',
                'codigo'   => 'GRUPO_REATIVADO',
                'mensagem' => 'Grupo reativado com sucesso.',
            ];
        } catch (Throwable $erro) {
            $banco->transRollback();
            log_message('error', 'Falha ao reativar grupo ({tipo}).', [
                'tipo' => $erro::class,
            ]);

            return [
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'GRUPO_REATIVACAO_INDISPONIVEL',
                'mensagem' => 'Não foi possível reativar o grupo. Tente novamente.',
            ];
        }
    }

    /**
     * Retorna a lista de identificadores de grupos inativos.
     *
     * @return list<string>
     */
    private function obterGruposInativos(): array
    {
        $inativos = setting('AuthGroups.gruposInativos');
        if (! is_array($inativos)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $inativos),
            static fn (string $item): bool => $item !== ''
        ));
    }
}
