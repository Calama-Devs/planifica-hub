<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EstadoContaModel;
use App\Models\IdentidadeRecuperacaoModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\RememberModel;
use CodeIgniter\Shield\Models\UserIdentityModel;
use RuntimeException;
use Throwable;

/**
 * Usa users.active como estado administrativo e revoga credenciais persistentes ao suspender.
 *
 * @phpstan-type Resultado array{sucesso: bool, tipo: string, codigo: string, mensagem: string}
 */
class EstadoContaService
{
    private EstadoContaModel $contas;
    private AcessoEssencialService $acessoEssencial;
    private RememberModel $lembrancas;
    private UserIdentityModel $identidades;
    private ConfirmacaoAdministrativaService $confirmacao;

    public function __construct(
        ?EstadoContaModel $contas = null,
        ?RememberModel $lembrancas = null,
        ?UserIdentityModel $identidades = null,
        ?ConfirmacaoAdministrativaService $confirmacao = null,
    ) {
        $this->contas = $contas ?? new EstadoContaModel();
        $this->acessoEssencial = new AcessoEssencialService($this->contas);
        $this->lembrancas = $lembrancas ?? new RememberModel();
        $this->identidades = $identidades ?? new UserIdentityModel();
        $this->confirmacao = $confirmacao ?? service('confirmacaoAdministrativaService');
    }

    /** @return Resultado */
    public function desativar(int $contaId, #[\SensitiveParameter] string $senhaAdmin): array
    {
        return $this->alterarEstado($contaId, $senhaAdmin, false);
    }

    /** @return Resultado */
    public function reativar(int $contaId, #[\SensitiveParameter] string $senhaAdmin): array
    {
        return $this->alterarEstado($contaId, $senhaAdmin, true);
    }

    /** @return Resultado */
    private function alterarEstado(int $contaId, #[\SensitiveParameter] string $senhaAdmin, bool $ativar): array
    {
        $operador = $this->confirmacao->confirmar(
            $ativar ? 'reativar_conta' : 'desativar_conta',
            $contaId,
            $senhaAdmin,
        );

        if (! $operador instanceof User) {
            return $operador;
        }

        $operadorId = (int) $operador->id;

        try {
            return $this->acessoEssencial->executarComBloqueio(
                fn (): array => $this->processarAlteracao($contaId, $operadorId, $ativar)
            );
        } catch (Throwable $erro) {
            log_message('error', 'Falha ao alterar estado: conta {conta}, operador {operador}, exceção {classe}.', [
                'conta' => $contaId,
                'operador' => $operadorId,
                'classe' => $erro::class,
            ]);

            return $this->falha('inesperado', 'CONTA_ERRO_PERSISTENCIA', 'Não foi possível alterar a conta.');
        }
    }

    /** @return Resultado */
    private function processarAlteracao(int $contaId, int $operadorId, bool $ativar): array
    {
        $recusa = $this->validarOperador($operadorId, $ativar);

        if ($recusa !== null) {
            return $recusa;
        }

        $conta = $this->contas->findById($contaId);

        if ($conta === null) {
            return $this->falha('regra_negocio', 'CONTA_NAO_ENCONTRADA', 'Conta não encontrada.');
        }

        if (! $ativar && $contaId === $operadorId) {
            return $this->falha(
                'regra_negocio',
                'CONTA_AUTODESATIVACAO',
                'Você não pode desativar a própria conta.',
            );
        }

        if ((bool) $conta->active === $ativar) {
            return $this->falha(
                'regra_negocio',
                $ativar ? 'CONTA_JA_ATIVA' : 'CONTA_JA_INATIVA',
                $ativar ? 'A conta já está ativa.' : 'A conta já está inativa.',
            );
        }

        if (! $ativar && $this->acessoEssencial->retirariaUltimoAcesso($conta)) {
            return $this->falha(
                'regra_negocio',
                'CONTA_ULTIMO_ADMINISTRADOR',
                'A última conta com acesso administrativo essencial não pode ser desativada.',
            );
        }

        return $this->gravarAlteracao($conta, $ativar);
    }

    /**
     * A senha já foi confirmada antes do bloqueio. Aqui o operador é conferido de novo sob o bloqueio, com o estado
     * e as permissões lidos depois de qualquer alteração concorrente.
     *
     * @return Resultado|null
     */
    private function validarOperador(int $operadorId, bool $ativar): ?array
    {
        $operador = $this->contas->findById($operadorId);
        $permissao = $ativar ? 'contas.reativar' : 'contas.desativar';

        if (
            $operador === null || ! $operador->active || $operador->isBanned()
            || ! $operador->can($permissao)
        ) {
            return $this->falha(
                'permissao',
                'CONTA_SEM_PERMISSAO',
                'Sem permissão para alterar o estado desta conta.',
            );
        }

        return null;
    }

    /** @return Resultado */
    private function gravarAlteracao(User $conta, bool $ativar): array
    {
        $alteracoes = ['active' => (int) $ativar];

        if (! $ativar) {
            $alteracoes['versao_sessao'] = (int) $conta->versao_sessao + 1;
        }

        if (! $this->contas->update($conta->id, $alteracoes)) {
            throw new RuntimeException('A alteração de estado não foi gravada.');
        }

        if (! $ativar) {
            $this->lembrancas->purgeRememberTokens($conta);
            $this->identidades->deleteIdentitiesByType($conta, 'magic-link');
            $this->identidades->deleteIdentitiesByType($conta, IdentidadeRecuperacaoModel::TIPO_TOKEN);
        }

        return [
            'sucesso' => true,
            'tipo' => 'sucesso',
            'codigo' => $ativar ? 'CONTA_REATIVADA' : 'CONTA_DESATIVADA',
            'mensagem' => $ativar ? 'Conta reativada com sucesso.' : 'Conta desativada com sucesso.',
        ];
    }

    /** @return Resultado */
    private function falha(string $tipo, string $codigo, string $mensagem): array
    {
        return ['sucesso' => false, 'tipo' => $tipo, 'codigo' => $codigo, 'mensagem' => $mensagem];
    }
}
