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
 *
 * A tela salva as caixas marcadas de uma vez (`sincronizar()`): o vínculo e a
 * auditoria de cada mudança são gravados do mesmo jeito que em `conceder()` e
 * `revogar()`, mas todos numa única transação, sem transações internas.
 */
class PermissaoDiretaService
{
    private const PERMISSAO_ADMINISTRAR = 'acessos.administrar';

    /**
     * Permissões que ganham destaque na tela: quem as tem pode alterar o acesso de outras contas.
     */
    private const PERMISSOES_CRITICAS = [self::PERMISSAO_ADMINISTRAR];

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
     *     categorias: list<array{titulo: string, permissoes: list<array<string, mixed>>}>,
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
                'critica'       => in_array($identificador, self::PERMISSOES_CRITICAS, true),
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
            'categorias'     => $this->agruparPorCategoria($permissoes),
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
     * Deixa as permissões diretas da conta exatamente como as marcadas: concede as que faltam e revoga as que
     * sobraram. É tudo ou nada: o vínculo e a auditoria de cada mudança são gravados por gravar(), a mesma
     * gravação de conceder() e revogar(), dentro de uma só transação, e qualquer falha desfaz todas.
     *
     * @param list<string> $marcadas Identificadores das caixas marcadas. O vínculo direto que a conta já tem e
     *                               não vier aqui é revogado, inclusive o que está fora do catálogo.
     *
     * @return array{
     *     sucesso: bool,
     *     tipo: string,
     *     codigo: string,
     *     mensagem: string,
     *     concedidas?: list<string>,
     *     revogadas?: list<string>,
     *     mantidas_por_heranca?: list<string>
     * }
     */
    public function sincronizar(int $contaId, array $marcadas, int $operadorId): array
    {
        $recusa = $this->recusarOperador($operadorId);

        if ($recusa !== null) {
            return $recusa;
        }

        $conta = $this->contas->findById($contaId);

        if ($conta === null) {
            return $this->falha('regra_negocio', 'CONTA_NAO_ENCONTRADA', 'Conta não encontrada.');
        }

        $desejadas = array_values(array_unique(array_map(
            static fn (string $permissao): string => strtolower(trim($permissao)),
            $marcadas,
        )));
        $atuais     = $conta->getPermissions() ?? [];
        $concedidas = array_values(array_diff($desejadas, $atuais));
        $revogadas  = array_values(array_diff($atuais, $desejadas));

        // Só o que vai ser concedido precisa estar no catálogo: o vínculo que a conta já tem pode ficar como está.
        if (array_diff($concedidas, array_keys($this->catalogo())) !== []) {
            return $this->falha(
                'validacao',
                'PERMISSAO_NAO_CADASTRADA',
                'Há permissão informada que não está cadastrada.',
            );
        }

        if ($concedidas === [] && $revogadas === []) {
            return [
                'sucesso'              => true,
                'tipo'                 => 'sucesso',
                'codigo'               => 'PERMISSOES_DIRETAS_SEM_ALTERACAO',
                'mensagem'             => 'Nenhuma alteração: as permissões diretas da conta já estavam assim.',
                'concedidas'           => [],
                'revogadas'            => [],
                'mantidas_por_heranca' => [],
            ];
        }

        $falha = $this->gravarMudancas($conta, $concedidas, $revogadas, $operadorId);

        return $falha ?? $this->descreverSincronizacao($contaId, $concedidas, $revogadas);
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
            $this->gravar($conta, $permissao, $acao, $operadorId);

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
     * Grava uma permissão direta e a auditoria dela, sem abrir transação: quem chama é o dono da transação
     * (persistir(), para uma permissão, ou gravarMudancas(), para várias). Lança exceção se algo não for gravado.
     */
    private function gravar(User $conta, string $permissao, string $acao, int $operadorId): void
    {
        if ($acao === AuditoriaPermissaoModel::ACAO_CONCEDIDA) {
            $conta->addPermission($permissao);
        } else {
            $conta->removePermission($permissao);
        }

        if (! $this->auditoria->registrar((int) $conta->id, $permissao, $acao, $operadorId)) {
            throw new RuntimeException('A auditoria não foi gravada.');
        }
    }

    /**
     * Grava todas as mudanças numa única transação, da qual este método é o dono: nada aqui abre transação
     * interna. Se o chamador já tinha aberto uma transação, esta participa dela, e o COMMIT ou o ROLLBACK de
     * verdade fica com ele.
     *
     * @param list<string> $concedidas
     * @param list<string> $revogadas
     *
     * @return array{
     *     sucesso: bool,
     *     tipo: string,
     *     codigo: string,
     *     mensagem: string
     * }|null A falha, ou nulo se tudo foi gravado.
     */
    private function gravarMudancas(User $conta, array $concedidas, array $revogadas, int $operadorId): ?array
    {
        $this->banco->transBegin();
        // Sem transação externa, esta é a transação mais externa e cabe a este método desfazer tudo se algo falhar.
        $dona = $this->banco->transDepth === 1;

        try {
            foreach ($concedidas as $permissao) {
                $this->gravar($conta, $permissao, AuditoriaPermissaoModel::ACAO_CONCEDIDA, $operadorId);
            }

            foreach ($revogadas as $permissao) {
                $this->gravar($conta, $permissao, AuditoriaPermissaoModel::ACAO_REVOGADA, $operadorId);
            }

            $this->banco->transCommit();

            return null;
        } catch (Throwable $erro) {
            $this->banco->transRollback();

            if ($dona) {
                // Mesma razão do persistir(): no modo estrito, a marca de falha só pode ser limpa pela transação dona.
                $this->banco->resetTransStatus();
            }

            log_message('error', 'Falha ao salvar permissões diretas: conta {conta}, operador {operador}: {erro}', [
                'conta'    => (int) $conta->id,
                'operador' => $operadorId,
                'erro'     => $erro->getMessage(),
            ]);

            return $this->falha(
                'inesperado',
                'PERMISSAO_DIRETA_ERRO_PERSISTENCIA',
                'Não foi possível concluir a operação. Tente novamente.',
            );
        }
    }

    /**
     * Relê a conta no banco e informa o que a gravação mudou de fato no acesso.
     *
     * @param list<string> $concedidas
     * @param list<string> $revogadas
     *
     * @return array{
     *     sucesso: bool,
     *     tipo: string,
     *     codigo: string,
     *     mensagem: string,
     *     concedidas: list<string>,
     *     revogadas: list<string>,
     *     mantidas_por_heranca: list<string>
     * }
     */
    private function descreverSincronizacao(int $contaId, array $concedidas, array $revogadas): array
    {
        $atual  = $this->contas->findById($contaId);
        $grupos = $atual !== null ? ($atual->getGroups() ?? []) : [];
        $matriz = $this->matriz();

        $mantidas = [];
        $detalhes = [];

        foreach ($revogadas as $permissao) {
            if ($atual === null || ! $atual->can($permissao)) {
                continue;
            }

            $origem     = $this->gruposQueConcedem($permissao, $grupos, $matriz);
            $mantidas[] = $permissao;
            $detalhes[] = $origem === [] ? $permissao : $permissao . ' (' . implode(', ', $origem) . ')';
        }

        return [
            'sucesso'              => true,
            'tipo'                 => 'sucesso',
            'codigo'               => 'PERMISSOES_DIRETAS_SALVAS',
            'mensagem'             => $this->mensagemDaSincronizacao(count($concedidas), count($revogadas), $detalhes),
            'concedidas'           => $concedidas,
            'revogadas'            => $revogadas,
            'mantidas_por_heranca' => $mantidas,
        ];
    }

    /**
     * @param list<string> $mantidas Permissões revogadas que a conta ainda recebe, com a origem do acesso.
     */
    private function mensagemDaSincronizacao(int $concedidas, int $revogadas, array $mantidas): string
    {
        $partes = [];

        if ($concedidas > 0) {
            $partes[] = $concedidas === 1 ? '1 concedida' : "{$concedidas} concedidas";
        }

        if ($revogadas > 0) {
            $partes[] = $revogadas === 1 ? '1 revogada' : "{$revogadas} revogadas";
        }

        $mensagem = 'Permissões diretas salvas: ' . implode(' e ', $partes) . '.';

        if ($mantidas !== []) {
            $mensagem .= ' A conta continua com acesso a: ' . implode(', ', $mantidas) . '.'
                . ' Esta tela não altera grupos nem a matriz de permissões.';
        }

        return $mensagem;
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
     * Junta as permissões por contexto, o trecho antes do ponto (`contas.consultar` fica em "Contas"). As
     * categorias saem em ordem alfabética do contexto, e as permissões de cada uma, na ordem em que chegam.
     *
     * @param list<array<string, mixed>> $permissoes
     *
     * @return list<array{titulo: string, permissoes: list<array<string, mixed>>}>
     */
    private function agruparPorCategoria(array $permissoes): array
    {
        $porContexto = [];

        foreach ($permissoes as $permissao) {
            $contexto = explode('.', (string) $permissao['identificador'], 2)[0];

            $porContexto[$contexto][] = $permissao;
        }

        ksort($porContexto);

        $categorias = [];

        foreach ($porContexto as $contexto => $doContexto) {
            $categorias[] = [
                'titulo'     => ucfirst(str_replace('_', ' ', (string) $contexto)),
                'permissoes' => $doContexto,
            ];
        }

        return $categorias;
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
