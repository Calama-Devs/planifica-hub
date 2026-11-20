<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\I18n\Time;

/** Confirma uma senha recente para uma operação e conta específicas. */
class ConfirmacaoOperacaoService
{
    private const DURACAO_SEGUNDOS = 300;
    private const CHAVE_SESSAO = 'confirmacao_operacao';
    private const CHAVE_JANELAS = 'confirmacoes_recentes';

    public function registrar(User $operador, string $senha, string $operacao, int $contaId): bool
    {
        if ($senha === '' || $operacao === '' || $contaId < 1) {
            return false;
        }

        $contaOperador = model(config('Auth')->userProvider)->findById((int) $operador->id);
        $hash = $contaOperador?->getPasswordHash();

        if (! is_string($hash) || ! service('passwords')->verify($senha, $hash)) {
            return false;
        }

        $janelas = session()->get(self::CHAVE_JANELAS) ?? [];
        $janelas[$this->chave($operador, $operacao, $contaId)] = Time::now()
            ->addSeconds(self::DURACAO_SEGUNDOS)
            ->getTimestamp();
        session()->set(self::CHAVE_JANELAS, $janelas);

        return true;
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

    public function confirmar(User $operador, string $senha, string $operacao, int $contaId): bool
    {
        if ($senha === '' || $operacao === '' || $contaId < 1) {
            return false;
        }

        $contaOperador = model(config('Auth')->userProvider)->findById((int) $operador->id);
        $hash = $contaOperador?->getPasswordHash();

        if (! is_string($hash) || ! service('passwords')->verify($senha, $hash)) {
            return false;
        }

        session()->set(self::CHAVE_SESSAO, [
            'operador_id' => (int) $operador->id,
            'operacao'    => $operacao,
            'conta_id'    => $contaId,
            'expira_em'   => time() + self::DURACAO_SEGUNDOS,
        ]);

        return true;
    }

    public function consumir(User $operador, string $operacao, int $contaId): bool
    {
        $confirmacao = session()->get(self::CHAVE_SESSAO);
        session()->remove(self::CHAVE_SESSAO);

        return is_array($confirmacao)
            && ($confirmacao['operador_id'] ?? null) === (int) $operador->id
            && ($confirmacao['operacao'] ?? null) === $operacao
            && ($confirmacao['conta_id'] ?? null) === $contaId
            && is_int($confirmacao['expira_em'] ?? null)
            && $confirmacao['expira_em'] >= time();
    }
}
