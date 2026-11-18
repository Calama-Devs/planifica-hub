<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EstadoContaModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Authentication\Passwords;
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
    private BaseConnection $banco;
    private RememberModel $lembrancas;
    private UserIdentityModel $identidades;

    public function __construct(
        ?EstadoContaModel $contas = null,
        ?RememberModel $lembrancas = null,
        ?UserIdentityModel $identidades = null,
    ) {
        $this->contas = $contas ?? new EstadoContaModel();
        $this->banco = $this->contas->obterConexao();
        $this->lembrancas = $lembrancas ?? new RememberModel();
        $this->identidades = $identidades ?? new UserIdentityModel();
    }

    /** @return Resultado */
    public function desativar(int $contaId, int $operadorId, string $senhaAtual): array
    {
        return $this->alterarEstado($contaId, $operadorId, $senhaAtual, false);
    }

    /** @return Resultado */
    public function reativar(int $contaId, int $operadorId, string $senhaAtual): array
    {
        return $this->alterarEstado($contaId, $operadorId, $senhaAtual, true);
    }

    /** @return Resultado */
    private function alterarEstado(int $contaId, int $operadorId, string $senhaAtual, bool $ativar): array
    {
        $profundidadeAnterior = $this->banco->transDepth;

        try {
            if (! $this->banco->transBegin()) {
                throw new RuntimeException('Não foi possível iniciar a transação.');
            }

            $this->contas->bloquearAlteracoesDeEstado();
            $resultado = $this->processarAlteracao($contaId, $operadorId, $senhaAtual, $ativar);

            if (! $this->banco->transStatus() || ! $this->banco->transCommit()) {
                throw new RuntimeException('Não foi possível concluir a transação.');
            }

            return $resultado;
        } catch (Throwable $erro) {
            if ($this->banco->transDepth > $profundidadeAnterior) {
                $this->banco->transRollback();
            }

            if ($profundidadeAnterior === 0) {
                $this->banco->resetTransStatus();
            }

            log_message('error', 'Falha ao alterar estado: conta {conta}, operador {operador}, exceção {classe}.', [
                'conta' => $contaId,
                'operador' => $operadorId,
                'classe' => $erro::class,
            ]);

            return $this->falha('inesperado', 'CONTA_ERRO_PERSISTENCIA', 'Não foi possível alterar a conta.');
        }
    }

    /** @return Resultado */
    private function processarAlteracao(int $contaId, int $operadorId, string $senhaAtual, bool $ativar): array
    {
        $recusa = $this->validarOperador($operadorId, $senhaAtual, $ativar);

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

        if (! $ativar && $this->retirariaUltimoAcessoAdministrativo($conta)) {
            return $this->falha(
                'regra_negocio',
                'CONTA_ULTIMO_ADMINISTRADOR',
                'A última conta com acesso administrativo essencial não pode ser desativada.',
            );
        }

        return $this->gravarAlteracao($conta, $ativar);
    }

    /** @return Resultado|null */
    private function validarOperador(int $operadorId, string $senhaAtual, bool $ativar): ?array
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

        $identidade = $operador->getEmailIdentity();
        /** @var Passwords $senhas */
        $senhas = service('passwords');

        if (
            $senhaAtual === '' || $identidade === null || ! is_string($identidade->secret2)
            || ! $senhas->verify($senhaAtual, $identidade->secret2)
        ) {
            return $this->falha('permissao', 'CONTA_SENHA_INVALIDA', 'A senha atual informada está incorreta.');
        }

        return null;
    }

    private function retirariaUltimoAcessoAdministrativo(User $conta): bool
    {
        if (! $this->possuiLoginOperacional($conta)) {
            return false;
        }

        $operacionais = $this->contas->listarContasOperacionais();

        foreach (['acessos.administrar', 'contas.reativar'] as $permissao) {
            if (! $conta->can($permissao)) {
                continue;
            }

            $alternativas = array_filter($operacionais, fn (User $candidata): bool =>
                (int) $candidata->id !== (int) $conta->id
                && $this->possuiLoginOperacional($candidata)
                && $candidata->can($permissao));

            if ($alternativas === []) {
                return true;
            }
        }

        return false;
    }

    private function possuiLoginOperacional(User $conta): bool
    {
        $identidade = $conta->getEmailIdentity();

        return (bool) $conta->active && ! $conta->isBanned()
            && $identidade !== null && is_string($identidade->secret2) && $identidade->secret2 !== '';
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
