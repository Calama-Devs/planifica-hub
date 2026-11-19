<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PermissaoDiretaService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Administração dos acessos das contas. Por enquanto, as permissões diretas.
 *
 * A barreira principal é o filtro `permission:acessos.administrar` da rota; o
 * Service repete a checagem em cada alteração (manual 6.1 e 11.3).
 */
class AcessoController extends BaseController
{
    private PermissaoDiretaService $permissaoDiretaService;

    public function __construct()
    {
        $this->permissaoDiretaService = service('permissaoDiretaService');
    }

    /**
     * Lista as contas para escolher qual administrar.
     */
    public function index(): string
    {
        $situacao = $this->request->getGet('situacao');
        if (! in_array($situacao, ['ativos', 'inativos'], true)) {
            $situacao = 'ativos';
        }

        $dados                      = $this->permissaoDiretaService->listarContas(0, situacao: $situacao);
        $dados['situacao']          = $situacao;
        $dados['gruposDisponiveis'] = $this->permissaoDiretaService->gruposDisponiveis();

        foreach ($dados['contas'] as &$conta) {
            $conta['rotulo']         = esc($conta['rotulo']);
            $conta['username']       = esc($conta['username']);
            $conta['email']          = esc($conta['email']);
            $conta['url_permissoes'] = url_to('acessos.permissoes', $conta['id']);
        }
        unset($conta);

        return view('acesso/index', $dados);
    }

    /**
     * Consulta contas de usuarios
     */
    public function consultarConta(): ResponseInterface
    {
        $entrada = $this->request->getGet(['pesquisa', 'page', 'situacao']);
        $regras  = [
            'pesquisa' => 'permit_empty|string|max_length[100]',
            'page'     => 'permit_empty|is_natural_no_zero|less_than_equal_to[999999999]',
            'situacao' => 'permit_empty|string|in_list[ativos,inativos,todos]',
        ];

        if (
            (isset($entrada['pesquisa']) && ! is_string($entrada['pesquisa']))
            || (isset($entrada['page']) && ! is_string($entrada['page']))
            || (isset($entrada['situacao']) && ! is_string($entrada['situacao']))
            || ! $this->validateData($entrada, $regras)
        ) {
            return $this->response->setStatusCode(422)->setJSON([
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'PESQUISA_CONTAS_INVALIDA',
                'mensagem' => 'Informe um nome ou e-mail com até 100 caracteres e uma página válida.',
            ]);
        }

        try {
            $validados = $this->validator->getValidated();
            $situacao  = isset($validados['situacao']) && $validados['situacao'] !== ''
                ? (string) $validados['situacao']
                : null;
            $listagem  = $this->permissaoDiretaService->listarContas(
                15,
                trim((string) ($validados['pesquisa'] ?? '')),
                (int) ($validados['page'] ?? 1),
                $situacao,
            );

            return $this->responderPesquisa($listagem);
        } catch (Throwable $erro) {
            log_message('error', 'Falha ao pesquisar contas na administração de acessos ({tipo}).', [
                'tipo' => $erro::class,
            ]);

            return $this->response->setStatusCode(500)->setJSON([
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'PESQUISA_CONTAS_INDISPONIVEL',
                'mensagem' => 'Não foi possível pesquisar as contas. Tente novamente.',
            ]);
        }
    }

    /**
     * @param array{contas: list<array<string, mixed>>, paginador: \CodeIgniter\Pager\Pager} $listagem
     */
    private function responderPesquisa(array $listagem): ResponseInterface
    {
        $contas = [];

        foreach ($listagem['contas'] as $conta) {
            $contas[] = [
                'id'             => $conta['id'],
                'rotulo'         => $conta['rotulo'],
                'email'          => $conta['email'],
                'grupos'         => $conta['grupos'],
                'total_diretas'  => $conta['total_diretas'],
                'url_permissoes' => url_to('acessos.permissoes', $conta['id']),
            ];
        }

        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON([
            'sucesso'   => true,
            'tipo'      => 'sucesso',
            'codigo'    => 'CONTAS_PESQUISADAS',
            'mensagem'  => $contas === [] ? 'Nenhuma conta encontrada.' : 'Pesquisa concluída.',
            'contas'    => $contas,
            'paginacao' => [
                'pagina_atual'  => $listagem['paginador']->getCurrentPage(),
                'total_paginas' => $listagem['paginador']->getPageCount(),
                'total_contas'  => $listagem['paginador']->getTotal(),
            ],
        ]);
    }

    /**
     * Mostra as permissões disponíveis, herdadas, diretas e efetivas de uma conta.
     */
    public function permissoes(string $contaId): string
    {
        $visao = $this->permissaoDiretaService->consultar($this->contaDaUrl($contaId));

        if ($visao === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('acesso/permissoes', $visao);
    }

    /**
     * Salva as caixas marcadas: concede as permissões diretas que faltam e revoga as que foram desmarcadas,
     * sem tocar no que a conta herda dos grupos. O operador vem sempre da sessão, nunca do formulário.
     */
    public function salvar(string $contaId): RedirectResponse
    {
        $id         = $this->contaDaUrl($contaId);
        $permissoes = $this->permissoesMarcadas();

        if ($permissoes === null) {
            return redirect()->to(url_to('acessos.permissoes', $id))
                ->with('error', 'A seleção de permissões é inválida.');
        }

        $resultado = $this->permissaoDiretaService->sincronizar($id, $permissoes, (int) auth()->id());

        return $this->responder($id, $resultado);
    }

    /**
     * O `(:num)` da rota aceita qualquer quantidade de dígitos. Fora da faixa de um id (1 a 999.999.999)
     * a conta não existe: devolve 404 em vez de estourar o tipo inteiro (manual 11.4).
     */
    private function contaDaUrl(string $contaId): int
    {
        if (preg_match('/^[1-9][0-9]{0,8}$/', $contaId) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        return (int) $contaId;
    }

    /**
     * Lê as caixas marcadas do formulário. Sem nenhuma caixa marcada o navegador não envia o campo, e isso vale
     * como lista vazia (todas desmarcadas). O formato da lista é conferido aqui; cada identificador ainda é
     * conferido contra o catálogo no Service.
     *
     * @return list<string>|null Nulo quando o formato é inválido.
     */
    private function permissoesMarcadas(): ?array
    {
        $enviadas = $this->request->getPost('permissoes') ?? [];

        if (! is_array($enviadas)) {
            return null;
        }

        $marcadas = [];

        foreach ($enviadas as $permissao) {
            if (! is_string($permissao) || $permissao === '' || mb_strlen($permissao) > 100) {
                return null;
            }

            $marcadas[] = $permissao;
        }

        return $marcadas;
    }

    /**
     * Traduz o retorno do Service em mensagem para a tela. Desmarcou o vínculo direto mas o acesso
     * continua por herança: o resultado vai como aviso, para ninguém achar que o acesso acabou.
     *
     * @param array{
     *     sucesso: bool,
     *     tipo: string,
     *     codigo: string,
     *     mensagem: string,
     *     mantidas_por_heranca?: list<string>
     * } $resultado
     */
    private function responder(int $contaId, array $resultado): RedirectResponse
    {
        $destino = redirect()->to(url_to('acessos.permissoes', $contaId));

        if (! $resultado['sucesso']) {
            return $destino->with('error', $resultado['mensagem']);
        }

        $manteveAcesso = ($resultado['mantidas_por_heranca'] ?? []) !== [];

        return $destino->with($manteveAcesso ? 'warning' : 'success', $resultado['mensagem']);
    }
}
