<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AcessoService;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Gerenciamento de Usuários (listagem server-side e preparação das ações administrativas).
 *
 * A rota exige `session` e uma das permissões de convite ou administração de acessos.
 */
class AcessoController extends BaseController
{
    private AcessoService $acessoService;

    public function __construct()
    {
        $this->acessoService = service('acessoService');
    }

    /**
     * Exibe a tela de Gerenciamento de Usuários.
     */
    public function index(): string
    {
        $situacao = $this->request->getGet('situacao');
        if (! in_array($situacao, ['ativos', 'inativos'], true)) {
            $situacao = 'ativos';
        }

        $operador                 = auth()->user();
        $podeAdministrarAcessos = $operador !== null && $operador->can('acessos.administrar');

        return view('acesso/index', [
            'situacao'                => $situacao,
            'gruposDisponiveis'       => $podeAdministrarAcessos ? $this->acessoService->gruposDisponiveis() : [],
            'podeCriarConta'          => $operador !== null && $operador->can('contas.criar'),
            'podeAdministrarAcessos' => $podeAdministrarAcessos,
            'formularioEmModal'       => true,
        ]);
    }

    /**
     * Endpoint JSON server-side para listagem, busca e paginação do DataTables.
     */
    public function listar(): ResponseInterface
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
            'situacao' => $this->request->getGet('situacao'),
            'pesquisa' => $pesquisa,
        ];

        foreach (['draw', 'start', 'length', 'situacao'] as $campo) {
            if ($entrada[$campo] !== null && ! is_string($entrada[$campo])) {
                return $this->erroValidacaoListagem();
            }
        }

        $regras = [
            'draw'     => 'permit_empty|is_natural|less_than_equal_to[999999999]',
            'start'    => 'permit_empty|is_natural|less_than_equal_to[999999999]',
            'length'   => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]',
            'situacao' => 'permit_empty|string|in_list[ativos,inativos]',
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
            $situacao  = isset($validados['situacao']) && $validados['situacao'] !== ''
                ? (string) $validados['situacao']
                : 'ativos';
            $termo     = trim((string) ($validados['pesquisa'] ?? ''));

            $listagem = $this->acessoService->listarContas($inicio, $limite, $termo, $situacao);

            return $this->response->setJSON([
                'draw'            => $draw,
                'recordsTotal'    => $listagem['total'],
                'recordsFiltered' => $listagem['filtrados'],
                'data'            => $listagem['contas'],
            ]);
        } catch (Throwable $erro) {
            log_message('error', 'Falha ao listar contas no gerenciamento de usuários ({tipo}).', [
                'tipo' => $erro::class,
            ]);

            return $this->response->setStatusCode(500)->setJSON([
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'LISTAGEM_CONTAS_INDISPONIVEL',
                'mensagem' => 'Não foi possível carregar os usuários. Tente novamente.',
            ]);
        }
    }

    private function erroValidacaoListagem(): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'sucesso'  => false,
            'tipo'     => 'validacao',
            'codigo'   => 'LISTAGEM_CONTAS_INVALIDA',
            'mensagem' => 'Parâmetros de listagem inválidos.',
        ]);
    }
}
