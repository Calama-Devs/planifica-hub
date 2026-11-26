<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Autoriza endpoints JSON e devolve erros no formato esperado pela interface. */
class PermissaoJsonFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ResponseInterface|null
    {
        $operador = auth()->user();

        foreach ($arguments ?? [] as $permissao) {
            if ($operador instanceof User && $operador->can($permissao)) {
                return null;
            }
        }

        $autenticado = $operador instanceof User;
        $resposta = service('response');

        return $resposta->setStatusCode($autenticado ? 403 : 401)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON([
                'sucesso' => false,
                'tipo' => 'permissao',
                'codigo' => $autenticado ? 'CONTA_SEM_PERMISSAO' : 'AUTENTICACAO_NECESSARIA',
                'mensagem' => $autenticado
                    ? 'Sem permissão para editar contas.'
                    : 'Entre novamente para continuar.',
                'csrf_hash' => csrf_hash(),
            ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
