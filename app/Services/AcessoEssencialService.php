<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EstadoContaModel;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;
use Throwable;

class AcessoEssencialService
{
    private const PERMISSOES_ESSENCIAIS = ['acessos.administrar', 'contas.reativar'];

    public function __construct(private EstadoContaModel $contas)
    {
    }

    /**
     * O bloqueio é a primeira consulta da transação, antes de carregar as entidades do Shield.
     * @template T of array
     * @param callable(): T $operacao
     * @return T
     */
    public function executarComBloqueio(callable $operacao): array
    {
        $banco = $this->contas->obterConexao();
        $profundidade = $banco->transDepth;
        if (! $banco->transBegin()) {
            throw new RuntimeException('Não foi possível iniciar a transação.');
        }

        try {
            $this->contas->bloquearAlteracoesDeEstado();
            $resultado = $operacao();
            if (! $banco->transStatus() || ! $banco->transCommit()) {
                throw new RuntimeException('Não foi possível confirmar a transação.');
            }

            return $resultado;
        } catch (Throwable $erro) {
            if ($banco->transDepth > $profundidade) {
                $banco->transRollback();
            }
            if ($profundidade === 0) {
                $banco->resetTransStatus();
            }
            throw $erro;
        }
    }

    /**
     * null representa desativação; uma lista representa as permissões diretas após a edição.
     * @param list<string>|null $diretasDepois
     */
    public function retirariaUltimoAcesso(User $conta, ?array $diretasDepois = null): bool
    {
        if (! $this->temLoginOperacional($conta)) {
            return false;
        }

        $depois = null;
        if ($diretasDepois !== null) {
            $depois = clone $conta;
            $depois->setPermissionsCache($diretasDepois);
        }

        $perdas = array_filter(self::PERMISSOES_ESSENCIAIS, static fn (string $permissao): bool =>
            $conta->can($permissao) && ! $depois?->can($permissao));
        if ($perdas === []) {
            return false;
        }

        foreach ($this->contas->listarContasOperacionais() as $outra) {
            if ((int) $outra->id !== (int) $conta->id && $this->temLoginOperacional($outra)) {
                $perdas = array_filter($perdas, static fn (string $permissao): bool => ! $outra->can($permissao));
            }
            if ($perdas === []) {
                return false;
            }
        }

        return true;
    }

    private function temLoginOperacional(User $conta): bool
    {
        $identidade = $conta->getEmailIdentity();

        return (bool) $conta->active && ! $conta->isBanned()
            && $identidade !== null && is_string($identidade->secret2) && $identidade->secret2 !== '';
    }
}
