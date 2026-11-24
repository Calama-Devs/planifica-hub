<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PermissaoService;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class PermissaoController extends BaseController
{
    private PermissaoService $permissaoService;

    public function __construct()
    {
        $this->permissaoService = service('permissaoService');
    }

    /**
     * Exibe a página de Grupos e permissões.
     */
    public function index(): string
    {
        return view('permissao/index', [
            'urlListar'         => url_to('permissoes.grupos.listar'),
            'destinoCriarGrupo' => site_url('admin/permissoes/grupos/novo'),
        ]);
    }

    public function listarGrupos(): ResponseInterface
    {
        $searchParam = $this->request->getGet('search');
        $pesquisa    = '';

        if (is_array($searchParam) && array_key_exists('value', $searchParam)) {
            if (! is_string($searchParam['value'])) {
                return $this->erroValidacaoListagem();
            }
            $pesquisa = $searchParam['value'];
        } elseif ($searchParam !== null) {
            return $this->erroValidacaoListagem();
        }

        $entrada = [
            'draw'     => $this->request->getGet('draw'),
            'start'    => $this->request->getGet('start'),
            'length'   => $this->request->getGet('length'),
            'pesquisa' => $pesquisa,
        ];

        foreach (['draw', 'start', 'length'] as $campo) {
            if ($entrada[$campo] !== null && ! is_string($entrada[$campo])) {
                return $this->erroValidacaoListagem();
            }
        }

        $regras = [
            'draw'     => 'permit_empty|is_natural|less_than_equal_to[999999999]',
            'start'    => 'permit_empty|is_natural|less_than_equal_to[999999999]',
            'length'   => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]',
            'pesquisa' => 'permit_empty|string|max_length[100]',
        ];

        if (! $this->validateData($entrada, $regras)) {
            return $this->erroValidacaoListagem();
        }

        try {
            $validados = $this->validator->getValidated();
            $draw      = (int) ($validados['draw'] ?? 0);
            $inicio    = (int) ($validados['start'] ?? 0);
            $limite    = (int) ($validados['length'] ?? 10);
            $termo     = trim((string) ($validados['pesquisa'] ?? ''));

            $listagem = $this->permissaoService->listarGrupos($inicio, $limite, $termo);

            return $this->response->setJSON([
                'draw'            => $draw,
                'recordsTotal'    => $listagem['total'],
                'recordsFiltered' => $listagem['filtrados'],
                'data'            => $listagem['grupos'],
            ]);
        } catch (Throwable $erro) {
            log_message('error', 'Falha ao listar grupos na página de permissões ({tipo}).', [
                'tipo' => $erro::class,
            ]);

            return $this->response->setStatusCode(500)->setJSON([
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'LISTAGEM_GRUPOS_INDISPONIVEL',
                'mensagem' => 'Não foi possível carregar os grupos. Tente novamente.',
            ]);
        }
    }

    private function erroValidacaoListagem(): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'sucesso'  => false,
            'tipo'     => 'validacao',
            'codigo'   => 'LISTAGEM_GRUPOS_INVALIDA',
            'mensagem' => 'Parâmetros de listagem inválidos.',
        ]);
    }
}
