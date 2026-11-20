<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\EdicaoContaService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

class ContaController extends BaseController
{
    private EdicaoContaService $edicaoContaService;

    public function __construct()
    {
        $this->edicaoContaService = service('edicaoContaService');
    }

    public function consultar(string $contaId): ResponseInterface
    {
        $operador = $this->obterOperadorAutorizado();

        if ($operador instanceof ResponseInterface) {
            return $operador;
        }

        $conta = $this->edicaoContaService->consultar(
            $this->idValido($contaId),
            $operador,
        );

        if ($conta === null) {
            return $this->jsonResponse([
                'sucesso' => false,
                'tipo' => 'regra_negocio',
                'codigo' => 'CONTA_NAO_ENCONTRADA',
                'mensagem' => 'Conta não encontrada.',
            ], 404);
        }

        return $this->response->setHeader('Cache-Control', 'no-store')
            ->setJSON(['sucesso' => true, 'conta' => $conta]);
    }

    public function atualizar(string $contaId): ResponseInterface
    {
        $operador = $this->obterOperadorAutorizado();

        if ($operador instanceof ResponseInterface) {
            return $operador;
        }

        $dados = $this->request->getPost();
        $propriedadesPermitidas = ['nome', 'senha_admin', 'email', 'justificativa'];
        $extras = array_diff(array_keys($dados), $propriedadesPermitidas, [csrf_token()]);

        if (
            $extras !== []
            || ! is_string($dados['nome'] ?? null)
            || (array_key_exists('senha_admin', $dados) && ! is_string($dados['senha_admin']))
            || (array_key_exists('email', $dados) && $dados['email'] !== null && ! is_string($dados['email']))
            || (
                array_key_exists('justificativa', $dados)
                && $dados['justificativa'] !== null
                && ! is_string($dados['justificativa'])
            )
        ) {
            return $this->jsonResponse([
                'sucesso' => false,
                'tipo' => 'validacao',
                'codigo' => 'DADOS_CONTA_INVALIDOS',
                'mensagem' => 'Os dados enviados não são válidos.',
            ], 400);
        }

        $resultado = $this->edicaoContaService->atualizar(
            $this->idValido($contaId),
            $dados['nome'],
            $dados['senha_admin'] ?? '',
            $operador,
            $dados['email'] ?? null,
            $dados['justificativa'] ?? null,
            array_key_exists('email', $dados),
            array_key_exists('justificativa', $dados),
        );

        $status = $resultado['status'] ?? ($resultado['sucesso'] ? 200 : 422);

        if ($resultado['codigo'] === 'CONTA_NAO_ENCONTRADA') {
            $status = 404;
        } elseif ($resultado['codigo'] === 'CONTA_SEM_PERMISSAO') {
            $status = 403;
        } elseif ($resultado['tipo'] === 'inesperado') {
            $status = 500;
        }

        return $this->jsonResponse($resultado, $status);
    }

    private function jsonResponse(array $dados, int $status): ResponseInterface
    {
        $dados['csrf_hash'] = csrf_hash();

        return $this->response->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($dados);
    }

    private function obterOperadorAutorizado(): User|ResponseInterface
    {
        $operador = auth()->user();

        if (! $operador instanceof User) {
            return $this->jsonResponse([
                'sucesso' => false,
                'tipo' => 'permissao',
                'codigo' => 'AUTENTICACAO_NECESSARIA',
                'mensagem' => 'Entre novamente para continuar.',
            ], 401);
        }

        if (! $operador->can('contas.editar')) {
            return $this->jsonResponse([
                'sucesso' => false,
                'tipo' => 'permissao',
                'codigo' => 'CONTA_SEM_PERMISSAO',
                'mensagem' => 'Sem permissão para editar contas.',
            ], 403);
        }

        return $operador;
    }

    private function idValido(string $contaId): int
    {
        if (preg_match('/^[1-9][0-9]{0,8}$/', $contaId) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        return (int) $contaId;
    }
}
