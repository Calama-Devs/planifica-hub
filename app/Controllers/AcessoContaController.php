<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PermissaoService;
use CodeIgniter\HTTP\RedirectResponse;

class AcessoContaController extends BaseController
{
    private AcessoContaController $acessoContaService;

    private function __construct()
    {
      
    }
    
    /**
     * Exibe permissoes de contas de usuarios do sistema().
     */
    public function index(): string
    {
      $contas = $this->acessoContaService->contas();
      
      return view('', [
        'contas' => $contas,
      ]);
    }
}