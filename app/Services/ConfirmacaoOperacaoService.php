<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\I18n\Time;

/**
 * Mantém a janela de confirmação recente de uma operação e de uma conta específicas.
 *
 * A conferência da senha e o limite de tentativas são do serviço compartilhado de confirmação administrativa;
 * esta classe só decide por quanto tempo uma confirmação já aceita dispensa uma nova senha.
 *
 * @phpstan-import-type Recusa from ConfirmacaoAdministrativaService
 */
class ConfirmacaoOperacaoService
{
    private const DURACAO_SEGUNDOS = 300;
    private const CHAVE_JANELAS = 'confirmacoes_recentes';

    private ConfirmacaoAdministrativaService $confirmacao;

    public function __construct(?ConfirmacaoAdministrativaService $confirmacao = null)
    {
        $this->confirmacao = $confirmacao ?? service('confirmacaoAdministrativaService');
    }

    /**
     * Confirma a senha do operador e abre a janela de confirmação recente para a operação e a conta.
     * A operação é uma ação cadastrada em `Config\ConfirmacaoAdministrativa`.
     *
     * @return Recusa|null A recusa estruturada, ou null quando a senha foi aceita.
     */
    public function registrar(
        User $operador,
        #[\SensitiveParameter] string $senha,
        string $operacao,
        int $contaId,
    ): ?array {
        $confirmado = $this->confirmacao->confirmar($operacao, $contaId, $senha);

        if (! $confirmado instanceof User) {
            return $confirmado;
        }

        // As demais verificações do chamador usam o operador informado: ele precisa ser o da sessão.
        if ((int) $confirmado->id !== (int) $operador->id) {
            return [
                'sucesso' => false,
                'tipo' => 'permissao',
                'codigo' => 'AUTENTICACAO_NECESSARIA',
                'mensagem' => 'Entre novamente para continuar.',
            ];
        }

        $janelas = session()->get(self::CHAVE_JANELAS) ?? [];
        $janelas[$this->chave($operador, $operacao, $contaId)] = Time::now()
            ->addSeconds(self::DURACAO_SEGUNDOS)
            ->getTimestamp();
        session()->set(self::CHAVE_JANELAS, $janelas);

        return null;
    }

    public function segundosRestantes(User $operador, string $operacao, int $contaId): ?int
    {
        $janelas = session()->get(self::CHAVE_JANELAS) ?? [];
        $expiraEm = $janelas[$this->chave($operador, $operacao, $contaId)] ?? null;

        if (! is_int($expiraEm)) {
            return null;
        }

        $restantes = $expiraEm - Time::now()->getTimestamp();

        return $restantes > 0 ? $restantes : null;
    }

    private function chave(User $operador, string $operacao, int $contaId): string
    {
        return (int) $operador->id . ':' . $operacao . ':' . $contaId;
    }
}
