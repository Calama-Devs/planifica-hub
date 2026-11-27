<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Protege e audita as consultas JSON dos grupos de permissões. */
class PermissaoGruposJsonFilter implements FilterInterface
{
    /** @param list<string>|null $arguments */
    public function before(RequestInterface $request, $arguments = null): ResponseInterface|null
    {
        $operador = auth()->user();

        if ($operador instanceof User && $operador->can('acessos.administrar')) {
            return null;
        }

        $autenticado = $operador instanceof User;
        $servico     = service('permissaoService');

        $servico->registrarRecusaConsultaDeGrupos(
            $arguments[0] ?? 'listar_grupos',
            null,
            $autenticado
                ? 'Consulta recusada: operador sem permissão.'
                : 'Consulta recusada: autenticação necessária.'
        );

        return service('response')
            ->setStatusCode($autenticado ? 403 : 401)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON([
                'sucesso'  => false,
                'tipo'     => 'permissao',
                'codigo'   => $autenticado ? 'GRUPOS_SEM_PERMISSAO' : 'AUTENTICACAO_NECESSARIA',
                'mensagem' => $autenticado
                    ? 'Sem permissão para consultar grupos.'
                    : 'Entre novamente para continuar.',
            ]);
    }

    /** @param list<string>|null $arguments */
    public function after(
        RequestInterface $request,
        ResponseInterface $response,
        $arguments = null
    ): ?ResponseInterface {
        return null;
    }
}
