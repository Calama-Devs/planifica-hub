<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PermissaoService;

class PermissaoController extends BaseController
{
    private PermissaoService $permissaoService;

    public function __construct()
    {
        $this->permissaoService = service('permissaoService');
    }

    /**
     * Exibe a listagem de permissões do sistema.
     */
    public function index(): string
    {
        $permissoes = $this->permissaoService->listarPermissoes();

        return view('permissao/index', [
            'permissoes' => $permissoes,
        ]);
    }
}
