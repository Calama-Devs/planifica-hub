<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PermissaoService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Tela de Grupos e permissões (listagem server-side e formulário de criação de grupo).
 *
 * A rota exige `session` e `permission:acessos.administrar`.
 */
class PermissaoController extends BaseController
{
    private PermissaoService $permissaoService;

    public function __construct()
    {
        $this->permissaoService = service('permissaoService');
    }

    public function index(): string
    {
        return view('permissao/index', [
            'urlListar'         => url_to('permissoes.grupos.listar'),
            'destinoCriarGrupo' => url_to('permissoes.grupos.novo'),
            'versaoCss'         => $this->versaoAsset('assets/css/permissao/index.css'),
            'versaoJs'          => $this->versaoAsset('assets/js/permissao/index.js'),
        ]);
    }

    public function criarGrupo(): string
    {
        $selecionadas = old('permissoes');
        if (! is_array($selecionadas)) {
            $selecionadas = [];
        }

        $categorias = array_merge(
            $this->permissaoService->listarPermissoesPorCategoria(auth()->user()),
            $this->categoriasComplementaresVisuais(),
        );

        return view('permissao/criar_grupo', [
            'categoriasPermissoes'   => $categorias,
            'permissoesSelecionadas' => array_values(array_filter($selecionadas, 'is_string')),
            'errosCampos'            => session('errors') ?? [],
            'urlSalvar'              => url_to('permissoes.grupos.salvar'),
            'urlVoltar'              => url_to('permissoes.index'),
            'versaoCss'              => $this->versaoAsset('assets/css/permissao/formulario_grupo.css'),
            'versaoJs'               => $this->versaoAsset('assets/js/permissao/formulario_grupo.js'),
        ]);
    }

    public function salvarGrupo(): RedirectResponse
    {
        $regras = [
            'nome'      => 'required|string|min_length[3]|max_length[80]',
            'descricao' => 'permit_empty|string|max_length[255]',
        ];

        $mensagens = [
            'nome' => [
                'required'   => 'Informe o nome do grupo.',
                'min_length' => 'O nome do grupo deve ter pelo menos 3 caracteres.',
                'max_length' => 'O nome do grupo pode ter no máximo 80 caracteres.',
            ],
            'descricao' => [
                'max_length' => 'A descrição pode ter no máximo 255 caracteres.',
            ],
        ];

        $valido = $this->validate($regras, $mensagens);
        $erros  = $valido ? [] : $this->validator->getErrors();

        $erroPermissoes = $this->validarPermissoesSelecionadas($this->request->getPost('permissoes'));
        if ($erroPermissoes !== null) {
            $erros['permissoes'] = $erroPermissoes;
        }

        if ($erros !== []) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $erros)
                ->with('error', 'Verifique os campos destacados antes de continuar.');
        }

        // Persistência definitiva no banco será integrada quando a entrega de back-end (PLAN-60) entrar em homolog.
        return redirect()->back()
            ->withInput()
            ->with(
                'warning',
                'Os dados foram validados, mas o salvamento de grupos ainda aguarda integração com o servidor.'
            );
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

    /**
     * Categorias visuais complementares (sem vínculo com o catálogo do Shield)
     * para compor o protótipo enquanto os demais módulos são integrados.
     *
     * @return list<array{
     *     chave: string,
     *     titulo: string,
     *     icone: string,
     *     somenteVisual: bool,
     *     permissoes: list<array{identificador: string, descricao: string, habilitada: bool}>
     * }>
     */
    private function categoriasComplementaresVisuais(): array
    {
        $item = static fn (string $id, string $desc): array => [
            'identificador' => $id,
            'descricao'     => $desc,
            'habilitada'    => true,
        ];

        return [
            [
                'chave'         => 'modulos_sistemas',
                'titulo'        => 'Módulos e sistemas',
                'icone'         => 'mdi-apps',
                'somenteVisual' => true,
                'permissoes'    => [
                    $item('modulos.rif', 'RIF'),
                    $item('modulos.roboto', 'ROBOTO'),
                    $item('modulos.alunos_pwa', 'ALUNOS-PWA'),
                    $item('modulos.planifica_hub', 'PLANIFICA-HUB'),
                ],
            ],
            [
                'chave'         => 'participantes',
                'titulo'        => 'Participantes',
                'icone'         => 'mdi-account-multiple-outline',
                'somenteVisual' => true,
                'permissoes'    => [
                    $item('participantes.visualizar', 'Visualizar participantes'),
                    $item('participantes.adicionar', 'Adicionar participantes'),
                    $item('participantes.editar', 'Editar participantes'),
                    $item('participantes.remover', 'Remover participantes'),
                ],
            ],
            [
                'chave'         => 'configuracoes',
                'titulo'        => 'Configurações',
                'icone'         => 'mdi-cog-outline',
                'somenteVisual' => true,
                'permissoes'    => [
                    $item('configuracoes.visualizar', 'Visualizar configurações'),
                    $item('configuracoes.gerais', 'Alterar configurações gerais'),
                    $item('configuracoes.integracoes', 'Gerenciar integrações'),
                ],
            ],
            [
                'chave'         => 'code_review',
                'titulo'        => 'Code Review',
                'icone'         => 'mdi-code-tags',
                'somenteVisual' => true,
                'permissoes'    => [
                    $item('codereview.visualizar', 'Visualizar revisões'),
                    $item('codereview.comentar', 'Comentar revisões'),
                    $item('codereview.aprovar', 'Aprovar revisões'),
                ],
            ],
        ];
    }

    private function validarPermissoesSelecionadas(mixed $enviadas): ?string
    {
        if ($enviadas === null) {
            return null;
        }

        if (! is_array($enviadas)) {
            return 'A seleção de permissões informada é inválida.';
        }

        $habilitadas = [];
        foreach ($this->permissaoService->listarPermissoesPorCategoria(auth()->user()) as $categoria) {
            foreach ($categoria['permissoes'] as $permissao) {
                if ($permissao['habilitada']) {
                    $habilitadas[$permissao['identificador']] = true;
                }
            }
        }

        foreach ($enviadas as $item) {
            if (! is_string($item) || ! isset($habilitadas[$item])) {
                return 'Uma ou mais permissões selecionadas não estão disponíveis para o seu perfil.';
            }
        }

        return null;
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
