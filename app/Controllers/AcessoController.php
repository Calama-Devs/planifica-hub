<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PermissaoDiretaService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

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
        return view('acesso/index', $this->permissaoDiretaService->listarContas());
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
