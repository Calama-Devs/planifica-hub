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
        $situacao = $this->request->getGet('situacao') === 'inativos' ? 'inativos' : 'ativos';

        return view('permissao/index', [
            'situacao'          => $situacao,
            'urlListar'         => url_to('permissoes.grupos.listar'),
            'urlReativar'       => url_to('permissoes.grupos.reativar', '999999999'),
            'destinoCriarGrupo' => site_url('admin/permissoes/grupos/novo'),
            'versaoCss'         => $this->versaoAsset('assets/css/permissao/index.css'),
            'versaoJs'          => $this->versaoAsset('assets/js/permissao/index.js'),
        ]);
    }

    /**
     * Endpoint JSON server-side para a listagem de grupos.
     *
     * Retorna o envelope padronizado (`sucesso`, `tipo`, `codigo`, `mensagem`) junto às chaves
     * exigidas pelo contrato do DataTables (`draw`, `recordsTotal`, `recordsFiltered`, `data`).
     * Erros retornam 422 (`LISTAGEM_GRUPOS_INVALIDA`) ou 500 (`LISTAGEM_GRUPOS_INDISPONIVEL`).
     */
    public function listarGrupos(): ResponseInterface
    {
        $pesquisa = $this->extrairTermoBusca();
        if ($pesquisa === null) {
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
            $situacao  = $this->request->getGet('situacao') === 'inativos' ? 'inativos' : 'ativos';

            $listagem = $this->permissaoService->listarGrupos($inicio, $limite, $termo, $situacao);
            $grupos   = array_map(
                static function (array $grupo): array {
                    $slug              = rawurlencode($grupo['identificador']);
                    $grupo['destinos'] = [
                        'editar'      => site_url('admin/permissoes/grupos/' . $slug . '/editar'),
                        'integrantes' => site_url('admin/permissoes/grupos/' . $slug . '/integrantes'),
                    ];

                    return $grupo;
                },
                $listagem['grupos'],
            );

            return $this->response->setJSON([
                'sucesso'         => true,
                'tipo'            => 'sucesso',
                'codigo'          => 'LISTAGEM_GRUPOS_OK',
                'mensagem'        => 'Grupos listados com sucesso.',
                'draw'            => $draw,
                'recordsTotal'    => $listagem['total'],
                'recordsFiltered' => $listagem['filtrados'],
                'data'            => $grupos,
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

    /**
     * Reativa um grupo previamente inativado.
     */
    public function reativarGrupo(string $identificador): ResponseInterface
    {
        $resultado = $this->permissaoService->reativarGrupo(
            rawurldecode($identificador),
            (int) auth()->id(),
        );

        $resultado['csrf'] = [
            'nome' => csrf_token(),
            'hash' => csrf_hash(),
        ];

        $status = match ($resultado['tipo']) {
            'sucesso'        => 200,
            'permissao'      => 403,
            'nao_encontrado' => 404,
            'validacao', 'regra_negocio' => 422,
            default          => 500,
        };

        return $this->response->setStatusCode($status)->setJSON($resultado);
    }

    private function extrairTermoBusca(): ?string
    {
        $searchParam = $this->request->getGet('search');

        if (is_array($searchParam)) {
            if (! array_key_exists('value', $searchParam)) {
                return '';
            }

            return is_string($searchParam['value']) ? $searchParam['value'] : null;
        }

        if (is_string($searchParam)) {
            return $searchParam;
        }

        if ($searchParam !== null) {
            return null;
        }

        $buscaDireta = $this->request->getGet('search[value]');
        if ($buscaDireta === null) {
            return '';
        }

        return is_string($buscaDireta) ? $buscaDireta : null;
    }

    private function versaoAsset(string $caminhoRelativo): string
    {
        $caminho = FCPATH . $caminhoRelativo;

        if (! is_file($caminho)) {
            return '1';
        }

        $mtime = filemtime($caminho);

        return $mtime !== false ? (string) $mtime : '1';
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
