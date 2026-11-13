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
     * Concede uma permissão do catálogo diretamente à conta.
     */
    public function conceder(string $contaId): RedirectResponse
    {
        $id        = $this->contaDaUrl($contaId);
        $permissao = $this->permissaoInformada();

        if ($permissao === null) {
            return $this->recusarEntrada($id);
        }

        $resultado = $this->permissaoDiretaService->conceder($id, $permissao, (int) auth()->id());

        return $this->responder($id, $resultado, false);
    }

    /**
     * Revoga a permissão direta da conta, sem tocar no que ela herda dos grupos.
     */
    public function revogar(string $contaId): RedirectResponse
    {
        $id        = $this->contaDaUrl($contaId);
        $permissao = $this->permissaoInformada();

        if ($permissao === null) {
            return $this->recusarEntrada($id);
        }

        $resultado = $this->permissaoDiretaService->revogar($id, $permissao, (int) auth()->id());

        return $this->responder($id, $resultado, true);
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
     * Valida o campo enviado pelo formulário e devolve a permissão, ou nulo se for inválido.
     */
    private function permissaoInformada(): ?string
    {
        $regras = [
            'permissao' => [
                'rules'  => 'required|max_length[100]',
                'errors' => [
                    'required'   => 'Informe a permissão que deseja alterar.',
                    'max_length' => 'A permissão informada é inválida.',
                ],
            ],
        ];

        if (! $this->validate($regras)) {
            return null;
        }

        return (string) $this->validator->getValidated()['permissao'];
    }

    private function recusarEntrada(int $contaId): RedirectResponse
    {
        return redirect()->to(url_to('acessos.permissoes', $contaId))
            ->with('error', (string) $this->validator->getError('permissao'));
    }

    /**
     * Traduz o retorno do Service em mensagem para a tela. Revogou o vínculo direto mas o acesso
     * continua por herança: o resultado vai como aviso, para ninguém achar que o acesso acabou.
     *
     * @param array{sucesso: bool, tipo: string, codigo: string, mensagem: string, acesso_efetivo?: bool} $resultado
     */
    private function responder(int $contaId, array $resultado, bool $revogacao): RedirectResponse
    {
        $destino = redirect()->to(url_to('acessos.permissoes', $contaId));

        if (! $resultado['sucesso']) {
            return $destino->with('error', $resultado['mensagem']);
        }

        $manteveAcesso = $revogacao && ($resultado['acesso_efetivo'] ?? false);

        return $destino->with($manteveAcesso ? 'warning' : 'success', $resultado['mensagem']);
    }
}
