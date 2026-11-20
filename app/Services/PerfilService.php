<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UsuarioAutenticacaoModel;
use App\Validation\ValidadorNomeCompleto;
use CodeIgniter\Shield\Entities\User;

class PerfilService
{
    public function obterComprimentoMaximoNome(): int
    {
        return 255;
    }

    /**
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string, erros?: array<string, string>}
     */
    public function atualizarNome(User $usuario, string $nome): array
    {
        $nome = trim($nome);

        $nomeAtual = (string) ($usuario->nome_completo ?? $usuario->username);

        if ($nome === trim($nomeAtual)) {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'PERFIL_NOME_SEM_ALTERACAO',
                'mensagem' => 'Informe um nome diferente do atual.',
                'erros'    => ['nome' => 'Informe um nome diferente do atual.'],
            ];
        }

        if (! $this->nomeCompletoValido($nome)) {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'PERFIL_NOME_INVALIDO',
                'mensagem' => 'Verifique o nome informado.',
                'erros'    => ['nome' => 'Informe um nome completo válido, com até 255 caracteres.'],
            ];
        }

        if (! $this->salvarNome($usuario, $nome)) {
            return [
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'PERFIL_NOME_NAO_ATUALIZADO',
                'mensagem' => 'Não foi possível atualizar seu nome. Tente novamente.',
            ];
        }

        return [
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'PERFIL_NOME_ATUALIZADO',
            'mensagem' => 'Nome atualizado com sucesso.',
        ];
    }

    private function nomeCompletoValido(string $nome): bool
    {
        return (new ValidadorNomeCompleto())->validar($nome);
    }

    private function salvarNome(User $usuario, string $nome): bool
    {
        $salvo = model(UsuarioAutenticacaoModel::class)->update($usuario->id, ['nome_completo' => $nome]);

        if (! $salvo) {
            log_message('error', 'Falha ao atualizar o nome exibido da conta autenticada.');
        }

        return $salvo;
    }
}
