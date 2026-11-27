<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PermissaoService;
use CodeIgniter\Shield\Entities\User;
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
        $this->response->setHeader('Cache-Control', 'no-store');

        $operador = $this->obterOperadorAutorizado('listar_grupos');
        if ($operador instanceof ResponseInterface) {
            return $operador;
        }

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

            $listagem = $this->permissaoService->listarGrupos($inicio, $limite, $termo);
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

    private function extrairTermoBusca(): ?string
    {
        $parametroBusca = $this->request->getGet('search');

        if (is_array($parametroBusca)) {
            if (! array_key_exists('value', $parametroBusca) || ! is_string($parametroBusca['value'])) {
                return null;
            }

            if (
                isset($parametroBusca['regex'])
                && ! in_array(
                    $parametroBusca['regex'],
                    ['false', '0', false, 0],
                    true
                )
            ) {
                return null;
            }

            return $parametroBusca['value'];
        }

        if (is_string($parametroBusca)) {
            return $parametroBusca;
        }

        if ($parametroBusca !== null) {
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
        $this->permissaoService->registrarRecusaConsultaDeGrupos(
            'listar_grupos',
            null,
            'Consulta recusada: parâmetros de listagem inválidos.'
        );

        return $this->response->setStatusCode(422)->setJSON([
            'sucesso'  => false,
            'tipo'     => 'validacao',
            'codigo'   => 'LISTAGEM_GRUPOS_INVALIDA',
            'mensagem' => 'Parâmetros de listagem inválidos.',
        ]);
    }

    public function consultarGrupo(
        string $identificador
    ): ResponseInterface {
        $this->response->setHeader('Cache-Control', 'no-store');

        $operador = $this->obterOperadorAutorizado('consultar_grupo');
        if ($operador instanceof ResponseInterface) {
            return $operador;
        }

        if (
            trim($identificador) === ''
            || mb_strlen($identificador) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $identificador) === 1
        ) {
            $this->permissaoService->registrarRecusaConsultaDeGrupos(
                'consultar_grupo',
                null,
                'Consulta recusada: identificador de grupo inválido.'
            );

            return $this->response->setStatusCode(422)->setJSON([
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'CONSULTA_GRUPO_INVALIDA',
                'mensagem' => 'Identificador de grupo inválido.',
            ]);
        }

        try {
            $grupo = $this->permissaoService->buscarGrupoPorIdentificador($identificador);

            if ($grupo === null) {
                return $this->response->setStatusCode(404)->setJSON([
                    'sucesso'  => false,
                    'tipo'     => 'regra_negocio',
                    'codigo'   => 'GRUPO_NAO_ENCONTRADO',
                    'mensagem' => 'Grupo não encontrado.',
                ]);
            }

            return $this->response->setJSON([
                'sucesso'  => true,
                'tipo'     => 'sucesso',
                'codigo'   => 'CONSULTA_GRUPO_OK',
                'mensagem' => 'Grupo consultado com sucesso.',
                'data'     => $grupo,
            ]);
        } catch (Throwable $erro) {
            log_message(
                'error',
                'Falha ao consultar grupo ({tipo}).',
                ['tipo' => $erro::class]
            );

            return $this->response->setStatusCode(500)->setJSON([
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'CONSULTA_GRUPO_INDISPONIVEL',
                'mensagem' => 'Não foi possível consultar o grupo.',
            ]);
        }
    }

    private function obterOperadorAutorizado(string $acao): User|ResponseInterface
    {
        $operador = auth()->user();

        if (! $operador instanceof User) {
            $this->permissaoService->registrarRecusaConsultaDeGrupos(
                $acao,
                null,
                'Consulta recusada: autenticação necessária.'
            );

            return $this->response->setStatusCode(401)->setJSON([
                'sucesso'  => false,
                'tipo'     => 'permissao',
                'codigo'   => 'AUTENTICACAO_NECESSARIA',
                'mensagem' => 'Entre novamente para continuar.',
            ]);
        }

        if (! $operador->can('acessos.administrar')) {
            $this->permissaoService->registrarRecusaConsultaDeGrupos(
                $acao,
                null,
                'Consulta recusada: operador sem permissão.'
            );

            return $this->response->setStatusCode(403)->setJSON([
                'sucesso'  => false,
                'tipo'     => 'permissao',
                'codigo'   => 'GRUPOS_SEM_PERMISSAO',
                'mensagem' => 'Sem permissão para consultar grupos.',
            ]);
        }

        return $operador;
    }
}
