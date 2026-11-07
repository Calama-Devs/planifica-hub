<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditoriaPermissaoModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Pager\Pager;
use CodeIgniter\Shield\Authorization\PermissionMatcher;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use RuntimeException;
use Throwable;

/**
 * Concessão e revogação de permissões diretas das contas (manual 6.1 e 11.3).
 *
 * Mexe somente nos vínculos diretos (`auth_permissions_users`): os grupos da
 * conta e a matriz global de permissões nunca são alterados aqui. Toda
 * alteração é auditada na mesma transação. O Shield guarda as permissões em
 * cache na própria entidade, por isso o efeito real é sempre calculado sobre a
 * conta recarregada do banco.
 */
class PermissaoDiretaService
{
    private const PERMISSAO_ADMINISTRAR = 'acessos.administrar';

    private UserModel $contas;
    private AuditoriaPermissaoModel $auditoria;
    private BaseConnection $banco;

    public function __construct(
        ?UserModel $contas = null,
        ?AuditoriaPermissaoModel $auditoria = null,
        ?BaseConnection $banco = null,
    ) {
        $this->contas    = $contas ?? model(UserModel::class);
        $this->auditoria = $auditoria ?? model(AuditoriaPermissaoModel::class);
        $this->banco     = $banco ?? db_connect();
    }

    /**
     * Lista as contas, com grupos e total de permissões diretas, para escolher qual administrar.
     *
     * @return array{contas: list<array<string, mixed>>, paginador: Pager}
     */
    public function listarContas(int $porPagina = 15): array
    {
        // Instância própria: os withX() do Shield ficam ligados no modelo até o fim da requisição.
        $modelo   = model(UserModel::class, false);
        $usuarios = $modelo->withIdentities()
            ->withGroups()
            ->withPermissions()
            ->orderBy('username', 'ASC')
            ->paginate($porPagina);

        $contas = [];

        foreach ($usuarios as $usuario) {
            if ($usuario instanceof User) {
                $contas[] = $this->resumirConta($usuario);
            }
        }

        return ['contas' => $contas, 'paginador' => $modelo->pager];
    }

    /**
     * Para uma conta, mostra cada permissão do catálogo com a origem do acesso:
     * herdada (de quais grupos), direta e o acesso efetivo.
     *
     * @return array{
     *     conta: array<string, mixed>,
     *     permissoes: list<array<string, mixed>>,
     *     foraDoCatalogo: list<string>
     * }|null Nulo quando a conta não existe.
     */
    public function consultar(int $contaId): ?array
    {
        $conta = $this->contas->findById($contaId);

        if ($conta === null) {
            return null;
        }

        $catalogo = $this->catalogo();
        $matriz   = $this->matriz();
        $grupos   = $conta->getGroups() ?? [];
        $diretas  = $conta->getPermissions() ?? [];

        $permissoes = [];

        foreach ($catalogo as $identificador => $descricao) {
            $permissoes[] = [
                'identificador' => $identificador,
                'descricao'     => $descricao,
                'direta'        => in_array($identificador, $diretas, true),
                'herdada_de'    => $this->gruposQueConcedem($identificador, $grupos, $matriz),
                'efetiva'       => $conta->can($identificador),
            ];
        }

        return [
            'conta'          => [
                'id'     => (int) $conta->id,
                'rotulo' => $this->rotular($conta),
                'email'  => (string) $conta->email,
                'grupos' => $this->titulosDosGrupos($grupos),
            ],
            'permissoes'     => $permissoes,
            // Vínculos diretos que o catálogo não lista mais: continuam valendo, então precisam aparecer.
            'foraDoCatalogo' => array_values(array_diff($diretas, array_keys($catalogo))),
        ];
    }

    /**
     * Concede uma permissão do catálogo diretamente à conta.
     *
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string, acesso_efetivo?: bool}
     */
    public function conceder(int $contaId, string $permissao, int $operadorId): array
    {
        $permissao = strtolower(trim($permissao));

        $recusa = $this->recusarOperador($operadorId);

        if ($recusa !== null) {
            return $recusa;
        }

        if (! array_key_exists($permissao, $this->catalogo())) {
            return $this->falha('validacao', 'PERMISSAO_NAO_CADASTRADA', 'A permissão informada não está cadastrada.');
        }

        $conta = $this->contas->findById($contaId);

        if ($conta === null) {
            return $this->falha('regra_negocio', 'CONTA_NAO_ENCONTRADA', 'Conta não encontrada.');
        }

        if ($this->temVinculoDireto($conta, $permissao)) {
            return $this->falha(
                'regra_negocio',
                'PERMISSAO_DIRETA_JA_CONCEDIDA',
                'A conta já possui esta permissão direta.',
            );
        }

        return $this->persistir($conta, $permissao, $operadorId, AuditoriaPermissaoModel::ACAO_CONCEDIDA);
    }

    /**
     * Revoga um vínculo direto da conta. Só o vínculo direto sai: o que a conta recebe dos grupos continua valendo.
     *
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string, acesso_efetivo?: bool}
     */
    public function revogar(int $contaId, string $permissao, int $operadorId): array
    {
        $permissao = strtolower(trim($permissao));

        $recusa = $this->recusarOperador($operadorId);

        if ($recusa !== null) {
            return $recusa;
        }

        $conta = $this->contas->findById($contaId);

        if ($conta === null) {
            return $this->falha('regra_negocio', 'CONTA_NAO_ENCONTRADA', 'Conta não encontrada.');
        }

        // A lista permitida é o que a conta tem de fato: assim também dá para limpar vínculos fora do catálogo.
        // Só vale o vínculo direto: quem recebe a permissão apenas de um grupo não tem o que revogar aqui.
        if (! $this->temVinculoDireto($conta, $permissao)) {
            return $this->falha(
                'regra_negocio',
                'PERMISSAO_DIRETA_NAO_CONCEDIDA',
                'A conta não possui esta permissão direta.',
            );
        }

        return $this->persistir($conta, $permissao, $operadorId, AuditoriaPermissaoModel::ACAO_REVOGADA);
    }

    /**
     * Grava o vínculo direto e a auditoria na mesma transação e devolve o efeito real,
     * lido da conta recarregada.
     *
     * Se o chamador já abriu uma transação, a operação participa dela: no CodeIgniter só a transação
     * mais externa faz COMMIT ou ROLLBACK de verdade, e as de dentro apenas ajustam a profundidade.
     * Quem abriu a transação continua decidindo o desfecho, olhando o `sucesso` do retorno.
     *
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string, acesso_efetivo?: bool}
     */
    private function persistir(User $conta, string $permissao, int $operadorId, string $acao): array
    {
        $contaId = (int) $conta->id;

        $this->banco->transBegin();
        // Sem transação externa, esta é a transação mais externa e cabe a este método tratar a falha por inteiro.
        $dona = $this->banco->transDepth === 1;

        try {
            if ($acao === AuditoriaPermissaoModel::ACAO_CONCEDIDA) {
                $conta->addPermission($permissao);
            } else {
                $conta->removePermission($permissao);
            }

            if (! $this->auditoria->registrar($contaId, $permissao, $acao, $operadorId)) {
                throw new RuntimeException('A auditoria não foi gravada.');
            }

            // Par do transBegin(): em transação externa só baixa a profundidade,
            // e o COMMIT de verdade continua com quem abriu a transação.
            $this->banco->transCommit();
        } catch (Throwable $erro) {
            $this->banco->transRollback();

            if ($dona) {
                // No modo estrito do CodeIgniter, uma query que falhou deixa a conexão marcada como "com falha"
                // mesmo após o rollback, e as transações seguintes também falhariam. A marca é limpa só aqui;
                // dentro de uma transação externa ela fica, para o dono dela desfazer tudo.
                $this->banco->resetTransStatus();
            }

            log_message(
                'error',
                'Falha ao alterar permissão direta ({acao}): conta {conta}, operador {operador}: {erro}',
                [
                    'acao'     => $acao,
                    'conta'    => $contaId,
                    'operador' => $operadorId,
                    'erro'     => $erro->getMessage(),
                ],
            );

            return $this->falha(
                'inesperado',
                'PERMISSAO_DIRETA_ERRO_PERSISTENCIA',
                'Não foi possível concluir a operação. Tente novamente.',
            );
        }

        return $this->descreverEfeito($contaId, $permissao, $acao);
    }

    /**
     * Relê a conta no banco e informa o que a alteração mudou de fato no acesso.
     *
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string, acesso_efetivo: bool}
     */
    private function descreverEfeito(int $contaId, string $permissao, string $acao): array
    {
        $atual   = $this->contas->findById($contaId);
        $efetivo = $atual !== null && $atual->can($permissao);
        $grupos  = $atual !== null
            ? $this->gruposQueConcedem($permissao, $atual->getGroups() ?? [], $this->matriz())
            : [];
        $origem = implode(', ', $grupos);

        if ($acao === AuditoriaPermissaoModel::ACAO_CONCEDIDA) {
            $mensagem = $grupos === []
                ? 'Permissão concedida diretamente à conta.'
                : "Permissão concedida diretamente. A conta já a recebia de: {$origem}; o acesso efetivo não mudou.";
            $codigo = 'PERMISSAO_DIRETA_CONCEDIDA';
        } else {
            $codigo   = 'PERMISSAO_DIRETA_REVOGADA';
            $mensagem = $this->mensagemDaRevogacao($efetivo, $grupos, $origem);
        }

        return [
            'sucesso'        => true,
            'tipo'           => 'sucesso',
            'codigo'         => $codigo,
            'mensagem'       => $mensagem,
            'acesso_efetivo' => $efetivo,
        ];
    }

    /**
     * @param list<string> $grupos Títulos dos grupos que ainda concedem a permissão.
     */
    private function mensagemDaRevogacao(bool $efetivo, array $grupos, string $origem): string
    {
        if (! $efetivo) {
            return 'Permissão direta revogada. A conta não tem mais acesso a esta permissão.';
        }

        if ($grupos === []) {
            return 'Permissão direta revogada, mas a conta ainda tem acesso por outro vínculo direto.';
        }

        return "Permissão direta revogada, mas a conta continua com acesso herdado de: {$origem}. "
            . 'Esta tela não altera grupos nem a matriz de permissões.';
    }

    /**
     * Repete a checagem do filtro de rota: o operador precisa poder administrar acessos (defesa em profundidade).
     *
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}|null
     */
    private function recusarOperador(int $operadorId): ?array
    {
        $operador = $this->contas->findById($operadorId);

        if ($operador !== null && $operador->can(self::PERMISSAO_ADMINISTRAR)) {
            return null;
        }

        return $this->falha('permissao', 'ACESSOS_SEM_PERMISSAO', 'Sem permissão para administrar acessos.');
    }

    /**
     * Informa se a permissão está gravada diretamente na conta (`auth_permissions_users`).
     * Os grupos não entram: a herança só aparece em `User::can()`, nunca aqui.
     */
    private function temVinculoDireto(User $conta, string $permissao): bool
    {
        return in_array($permissao, $conta->getPermissions() ?? [], true);
    }

    /**
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}
     */
    private function falha(string $tipo, string $codigo, string $mensagem): array
    {
        return ['sucesso' => false, 'tipo' => $tipo, 'codigo' => $codigo, 'mensagem' => $mensagem];
    }

    /**
     * Catálogo ativo: o mesmo que o Shield consulta para aceitar `addPermission()`.
     *
     * @return array<string, string>
     */
    private function catalogo(): array
    {
        $catalogo = setting('AuthGroups.permissions');
        $catalogo = is_array($catalogo) ? $catalogo : [];

        ksort($catalogo);

        return $catalogo;
    }

    /**
     * Matriz ativa: a mesma que `User::can()` consulta.
     *
     * @return array<string, list<string>>
     */
    private function matriz(): array
    {
        $matriz = setting('AuthGroups.matrix');

        return is_array($matriz) ? $matriz : [];
    }

    /**
     * Títulos dos grupos da conta que concedem a permissão, com a mesma regra de curingas do `can()`.
     *
     * @param list<string>              $grupos
     * @param array<string, list<string>> $matriz
     *
     * @return list<string>
     */
    private function gruposQueConcedem(string $permissao, array $grupos, array $matriz): array
    {
        $concedem = array_filter(
            $grupos,
            static fn (string $grupo): bool => PermissionMatcher::matches($permissao, $matriz[$grupo] ?? []),
        );

        return $this->titulosDosGrupos(array_values($concedem));
    }

    /**
     * @param list<string> $grupos
     *
     * @return list<string>
     */
    private function titulosDosGrupos(array $grupos): array
    {
        $definidos = setting('AuthGroups.groups');
        $definidos = is_array($definidos) ? $definidos : [];

        return array_map(
            static fn (string $grupo): string => (string) ($definidos[$grupo]['title'] ?? $grupo),
            $grupos,
        );
    }

    /**
     * Linha da listagem de contas. Grupos e permissões já vêm carregados em lote pelo `listarContas()`.
     *
     * @return array<string, mixed>
     */
    private function resumirConta(User $conta): array
    {
        return [
            'id'            => (int) $conta->id,
            'rotulo'        => $this->rotular($conta),
            'email'         => (string) $conta->email,
            'grupos'        => $this->titulosDosGrupos($conta->getGroups() ?? []),
            'total_diretas' => count($conta->getPermissions() ?? []),
        ];
    }

    private function rotular(User $conta): string
    {
        $nome = (string) $conta->username;

        if ($nome !== '') {
            return $nome;
        }

        $email = (string) $conta->email;

        return $email !== '' ? $email : 'Conta ' . $conta->id;
    }
}
