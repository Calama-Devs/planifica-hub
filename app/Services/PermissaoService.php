<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventoAuditoriaModel;
use App\Models\GrupoModel;
use App\Models\GrupoUsuarioModel;
use App\Models\PermissaoModel;
use Config\AuthGroups;
use CodeIgniter\Shield\Authorization\AuthorizationException;
use CodeIgniter\Shield\Entities\User;
use Throwable;

class PermissaoService
{
    /**
     * Grupos nativos protegidos contra bloqueio/exclusão, independentemente de alterações no grupo padrão.
     */
    private const GRUPOS_PROTEGIDOS = ['superadmin', 'user'];

    private GrupoModel $grupoModel;
    private PermissaoModel $permissaoModel;
    private GrupoUsuarioModel $grupoUsuarioModel;
    private AuditoriaService $auditoria;

    public function __construct(
        ?PermissaoModel $permissaoModel = null,
        ?GrupoUsuarioModel $grupoUsuarioModel = null,
        ?GrupoModel $grupoModel = null,
        ?AuditoriaService $auditoria = null,
    ) {
        $this->permissaoModel    = $permissaoModel ?? model(PermissaoModel::class);
        $this->grupoUsuarioModel = $grupoUsuarioModel ?? model(GrupoUsuarioModel::class);
        $this->grupoModel        = $grupoModel ?? model(GrupoModel::class);
        $this->auditoria         = $auditoria ?? new AuditoriaService(
            new EventoAuditoriaModel($this->permissaoModel->db)
        );
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
        $this->exigirPermissaoConsulta('listar_grupos');

        try {
            $inicio   = max(0, $inicio);
            $limite   = max(1, min($limite, 100));
            $pesquisa = trim($pesquisa);
            $todos    = $this->obterGrupos();

            usort(
                $todos,
                static fn (array $a, array $b): int =>
                    strcasecmp($a['nome'], $b['nome'])
                    ?: strcmp($a['identificador'], $b['identificador'])
            );

            $filtrados = $pesquisa === ''
                ? $todos
                : array_values(array_filter(
                    $todos,
                    static fn (array $grupo): bool =>
                        mb_stripos($grupo['nome'], $pesquisa) !== false
                        || mb_stripos($grupo['descricao'], $pesquisa) !== false
                        || mb_stripos($grupo['identificador'], $pesquisa) !== false
                ));

            $resultado = [
                'grupos'    => array_slice($filtrados, $inicio, $limite),
                'total'     => count($todos),
                'filtrados' => count($filtrados),
            ];

            $this->auditoria->registrar([
                'modulo'        => 'permissoes',
                'acao'          => 'listar_grupos',
                'entidade_tipo' => 'grupo',
                'descricao'     => 'Listagem de grupos consultada.',
                'resultado'     => 'concluida',
            ]);

            return $resultado;
        } catch (Throwable $erro) {
            $this->registrarErroConsulta('listar_grupos', null, $erro);

            throw $erro;
        }
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
     * @return list<array{
     *     identificador: string,
     *     nome: string,
     *     descricao: string,
     *     usuarios_vinculados: int,
     *     situacao: string,
     *     ativo: bool,
     *     protegido: bool
     * }>
     */
    private function obterGrupos(): array
    {
        $authGroups = config(AuthGroups::class);

        $definidos = setting('AuthGroups.groups');

        $definidos = is_array($definidos) ? $definidos : [];

        $gruposProtegidos = array_values(array_unique([
            ...self::GRUPOS_PROTEGIDOS,
            (string) $authGroups->defaultGroup,
        ]));

        $contagens = $this->grupoUsuarioModel
            ->contarUsuariosPorGrupo();

        $metadados = $this->grupoModel
            ->obterMapaGrupos();

        $todos = [];

        foreach ($definidos as $identificador => $dados) {
            if (! is_array($dados)) {
                throw new \UnexpectedValueException('Configuração de grupo inválida.');
            }

            $slug = (string) $identificador;
            $ativo = (bool) ($metadados[$slug]['ativo'] ?? true);

            $todos[] = [
                'identificador'       => $slug,
                'nome'                => (string) ($dados['title'] ?? $slug),
                'descricao'           => (string) ($dados['description'] ?? ''),
                'usuarios_vinculados' => $contagens[$slug] ?? 0,
                'situacao'            => $ativo ? 'ativo' : 'bloqueado',
                'ativo'               => $ativo,
                'protegido'           => in_array($slug, $gruposProtegidos, true),
            ];
        }

        return $todos;
    }

    /**
     * @return array{
     *     identificador: string,
     *     nome: string,
     *     descricao: string,
     *     usuarios_vinculados: int,
     *     situacao: string,
     *     ativo: bool,
     *     protegido: bool
     * }|null
     */
    public function buscarGrupoPorIdentificador(
        string $identificador
    ): ?array {
        $this->exigirPermissaoConsulta('consultar_grupo');

        if (
            trim($identificador) === ''
            || mb_strlen($identificador) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $identificador) === 1
        ) {
            $this->registrarRecusaConsultaDeGrupos(
                'consultar_grupo',
                null,
                'Consulta recusada: identificador de grupo inválido.'
            );

            throw new \InvalidArgumentException('Identificador de grupo inválido.');
        }

        try {
            foreach ($this->obterGrupos() as $grupo) {
                if ($grupo['identificador'] !== $identificador) {
                    continue;
                }

                $this->auditoria->registrar([
                    'modulo'        => 'permissoes',
                    'acao'          => 'consultar_grupo',
                    'entidade_tipo' => 'grupo',
                    'entidade_id'   => $this->identificadorParaAuditoria($identificador),
                    'descricao'     => 'Grupo consultado.',
                    'resultado'     => 'concluida',
                ]);

                return $grupo;
            }

            $this->registrarRecusaConsultaDeGrupos(
                'consultar_grupo',
                $this->identificadorParaAuditoria($identificador),
                'Consulta recusada: grupo não encontrado.'
            );

            return null;
        } catch (Throwable $erro) {
            $this->registrarErroConsulta('consultar_grupo', $identificador, $erro);

            throw $erro;
        }
    }

    /**
     * Registra uma recusa de consulta sem alterar a resposta funcional da requisição.
     */
    public function registrarRecusaConsultaDeGrupos(
        string $acao,
        ?string $identificador,
        string $descricao
    ): void {
        if (! in_array($acao, ['listar_grupos', 'consultar_grupo'], true)) {
            throw new \InvalidArgumentException('Ação de consulta inválida.');
        }

        try {
            $this->auditoria->registrar([
                'modulo'        => 'permissoes',
                'acao'          => $acao,
                'entidade_tipo' => 'grupo',
                'entidade_id'   => $this->identificadorParaAuditoria($identificador),
                'descricao'     => $descricao,
                'resultado'     => 'recusada',
            ]);
        } catch (Throwable $erro) {
            log_message(
                'warning',
                'Não foi possível auditar a recusa de consulta de grupos ({tipo}).',
                ['tipo' => $erro::class]
            );
        }
    }

    private function exigirPermissaoConsulta(string $acao): void
    {
        $operador = auth()->user();

        if ($operador instanceof User && $operador->can('acessos.administrar')) {
            return;
        }

        $descricao = $operador instanceof User
            ? 'Consulta recusada: operador sem permissão.'
            : 'Consulta recusada: autenticação necessária.';

        $this->registrarRecusaConsultaDeGrupos($acao, null, $descricao);

        throw new AuthorizationException('Consulta de grupos não autorizada.');
    }

    private function registrarErroConsulta(
        string $acao,
        ?string $identificador,
        Throwable $erro
    ): void {
        log_message(
            'error',
            'Falha na consulta de grupos ({acao}, {tipo}).',
            ['acao' => $acao, 'tipo' => $erro::class]
        );

        try {
            $this->auditoria->registrar([
                'modulo'        => 'permissoes',
                'acao'          => $acao,
                'entidade_tipo' => 'grupo',
                'entidade_id'   => $this->identificadorParaAuditoria($identificador),
                'descricao'     => 'Falha técnica ao consultar grupos.',
                'resultado'     => 'erro',
            ]);
        } catch (Throwable $erroAuditoria) {
            log_message(
                'error',
                'Falha ao registrar auditoria da consulta de grupos ({tipo}).',
                ['tipo' => $erroAuditoria::class]
            );
        }
    }

    private function identificadorParaAuditoria(?string $identificador): ?string
    {
        return $identificador !== null && mb_strlen($identificador) <= 100
            ? $identificador
            : null;
    }
}
