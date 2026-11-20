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
}
