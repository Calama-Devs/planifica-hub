<?php

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Validation\ValidationRules;
use CodeIgniter\Validation\ValidationInterface;

class PerfilService
{
    /**
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string, erros?: array<string, string>}
     */
    public function atualizarNome(User $usuario, string $nome): array
    {
        if ($nome === $usuario->username) {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'PERFIL_NOME_SEM_ALTERACAO',
                'mensagem' => 'Informe um nome diferente do atual.',
                'erros'    => ['nome' => 'Informe um nome diferente do atual.'],
            ];
        }

        $validacao = $this->obterValidacaoNome($usuario);

        if (! $validacao->run(['nome' => $nome])) {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'PERFIL_NOME_INVALIDO',
                'mensagem' => 'Verifique o nome informado.',
                'erros'    => $validacao->getErrors(),
            ];
        }

        $dadosValidados = $validacao->getValidated();

        if (! $this->salvarNome($usuario, $dadosValidados['nome'])) {
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

    private function obterValidacaoNome(User $usuario): ValidationInterface
    {
        $validacao = service('validation')->reset();
        $validacao->setRules($this->obterRegrasNomeParaEdicao((string) $usuario->id), [
            'nome' => [
                'required'    => 'Informe seu nome.',
                'min_length'  => 'O nome deve ter pelo menos {param} caracteres.',
                'max_length'  => 'O nome deve ter no máximo {param} caracteres.',
                'regex_match' => 'O nome contém caracteres não permitidos.',
                'is_unique'   => 'Este nome já está em uso.',
            ],
        ]);

        return $validacao;
    }

    private function salvarNome(User $usuario, string $nome): bool
    {
        $salvo = model(UserModel::class)->update($usuario->id, ['username' => $nome]);

        if (! $salvo) {
            log_message('error', 'Falha ao atualizar o nome exibido da conta autenticada.');
        }

        return $salvo;
    }

    public function obterComprimentoMaximoNome(): ?int
    {
        foreach ($this->obterRegrasConfiguradasNome() as $regra) {
            if (is_string($regra) && preg_match('/^max_length\[(\d+)\]$/', $regra, $correspondencia) === 1) {
                return (int) $correspondencia[1];
            }
        }

        return null;
    }

    private function obterRegrasNomeParaEdicao(string $usuarioId): array
    {
        $regrasNome = array_values(array_filter(
            $this->obterRegrasConfiguradasNome(),
            static fn ($regra): bool => is_string($regra)
                ? ! str_starts_with($regra, 'is_unique[')
                : is_callable($regra),
        ));

        $configuracaoAuth = config('Auth');
        $regrasNome[]     = sprintf(
            'is_unique[%s.username,id,%s]',
            $configuracaoAuth->tables['users'],
            $usuarioId,
        );

        return [
            'nome' => [
                'label' => 'nome',
                'rules' => array_merge(['required', 'string'], $regrasNome),
            ],
        ];
    }

    /**
     * @return list<mixed>
     */
    private function obterRegrasConfiguradasNome(): array
    {
        $regrasCadastro = (new ValidationRules())->getRegistrationRules();
        $definicaoNome  = $regrasCadastro['username'] ?? config('Auth')->usernameValidationRules;

        if (is_array($definicaoNome) && array_key_exists('rules', $definicaoNome)) {
            $definicaoNome = $definicaoNome['rules'];
        }

        if (is_string($definicaoNome)) {
            return explode('|', $definicaoNome);
        }

        if (! is_array($definicaoNome)) {
            return [];
        }

        return array_values($definicaoNome);
    }
}
