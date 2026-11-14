<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\AuditoriaUsuarioModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Registra tentativas sem permissão antes de o filtro permission do Shield bloquear a rota. */
class AuditoriaContasFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): RequestInterface|ResponseInterface|string|null
    {
        $permissao = is_array($arguments) ? (string) ($arguments[0] ?? '') : '';
        $usuario   = auth()->user();

        if ($permissao !== '' && (! $usuario || ! $usuario->can($permissao))) {
            $operadorId = auth()->id();

            if ($operadorId !== null) {
                $operacao = $permissao === 'contas.consultar' ? 'consulta' : 'cadastro';

                if (! model(AuditoriaUsuarioModel::class)->registrar(null, (int) $operadorId, $operacao, 'recusada')) {
                    log_message('error', 'Falha ao registrar recusa de acesso à administração de usuários.');
                }
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
