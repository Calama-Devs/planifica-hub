<?php

namespace App\Services;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Throwable;

class RedefinirSenhaService
{
    private UserIdentityModel $modeloIdentidade;

    public function __construct(?UserIdentityModel $modeloIdentidade = null)
    {
        $this->modeloIdentidade = $modeloIdentidade ?? model(UserIdentityModel::class);
    }

    /**
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}
     */
    public function redefinir(User $usuario, string $senha, string $confirmacao): array
    {
        $validacao = $this->validarSenha($usuario, $senha, $confirmacao);

        if (!$validacao['sucesso']) {
            return $validacao;
        }

        try {
            $identidade = $this->modeloIdentidade->getIdentityByType(
                $usuario,
                Session::ID_TYPE_EMAIL_PASSWORD
            );

            if ($identidade === null) {
                log_message(
                    'error',
                    '[RedefinirSenhaService::redefinir] Usuario {id} nao possui identidade email_password.',
                    ['id' => $usuario->id]
                );

                return $this->erro(
                    'regra_negocio',
                    'IDENTIDADE_NAO_ENCONTRADA',
                    'Nao foi possivel redefinir a senha desta conta.'
                );
            }

            $senhas    = service('passwords');
            $hashSenha = $senhas->hash($senha);

            if (! is_string($hashSenha)) {
                log_message(
                    'error',
                    '[RedefinirSenhaService::redefinir] Nao foi possivel gerar hash para o usuario {id}.',
                    ['id' => $usuario->id]
                );

                return $this->erro('inesperado', 'HASH_NAO_GERADO', 'Erro ao redefinir senha.');
            }

            $atualizado = $this->modeloIdentidade->update($identidade->id, [
                'secret2'     => $hashSenha,
                'force_reset' => 0,
            ]);

            if ($atualizado === false) {
                log_message(
                    'error',
                    '[RedefinirSenhaService::redefinir] Falha ao atualizar a senha do usuario {id}.',
                    ['id' => $usuario->id]
                );

                return $this->erro('inesperado', 'SENHA_NAO_ATUALIZADA', 'Erro ao redefinir senha.');
            }

            log_message('info', '[RedefinirSenhaService::redefinir] Senha redefinida para o usuario {id}.', [
                'id' => $usuario->id,
            ]);

            return [
                'sucesso'  => true,
                'tipo'     => 'sucesso',
                'codigo'   => 'SENHA_REDEFINIDA',
                'mensagem' => 'Senha redefinida com sucesso.',
            ];
        } catch (Throwable $erro) {
            log_message(
                'error',
                '[RedefinirSenhaService::redefinir] Erro inesperado para o usuario {id}: {erro}',
                ['id' => $usuario->id, 'erro' => $erro->getMessage()]
            );

            return $this->erro(
                'inesperado',
                'ERRO_AO_REDEFINIR_SENHA',
                'Ocorreu um erro ao redefinir a senha, tente novamente.'
            );
        }
    }

    /**
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}
     */
    private function validarSenha(User $usuario, string $senha, string $confirmacao): array
    {
        if ($senha === '' || $confirmacao === '') {
            return $this->erro('validacao', 'SENHA_OBRIGATORIA', 'Informe e confirme a nova senha.');
        }

        if ($senha !== $confirmacao) {
            return $this->erro('validacao', 'CONFIRMACAO_DIVERGENTE', 'As senhas informadas devem ser iguais.');
        }

        /** @var Passwords $senhas */
        $senhas          = service('passwords');
        $resultadoShield = $senhas->check($senha, $usuario);

        if (!$resultadoShield->isOK()) {
            return $this->erro(
                'validacao',
                'SENHA_RECUSADA_PELO_SHIELD',
                $this->traduzirMotivoShield($resultadoShield->reason())
            );
        }

        if (!preg_match('/[A-Z]/', $senha)) {
            return $this->erro(
                'validacao',
                'SENHA_SEM_MAIUSCULA',
                'A senha deve conter pelo menos uma letra maiuscula.'
            );
        }

        if (!preg_match('/[a-z]/', $senha)) {
            return $this->erro(
                'validacao',
                'SENHA_SEM_MINUSCULA',
                'A senha deve conter pelo menos uma letra minuscula.'
            );
        }

        if (!preg_match('/[0-9]/', $senha)) {
            return $this->erro('validacao', 'SENHA_SEM_NUMERO', 'A senha deve conter pelo menos um numero.');
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $senha)) {
            return $this->erro(
                'validacao',
                'SENHA_SEM_CARACTERE_ESPECIAL',
                'A senha deve conter pelo menos um caractere especial.'
            );
        }

        if (preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $senha)) {
            return $this->erro('validacao', 'SENHA_COM_EMOJI', 'A senha nao pode conter emojis.');
        }

        return [
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'SENHA_VALIDA',
            'mensagem' => 'Senha valida.',
        ];
    }

    private function traduzirMotivoShield(?string $motivo): string
    {
        $tamanhoMinimo = config('Auth')->minimumPasswordLength;
        $mensagens = [
            lang('Auth.errorPasswordEmpty') => 'Informe a nova senha.',
            lang('Auth.errorPasswordLength', [$tamanhoMinimo]) =>
                'A senha deve ter pelo menos ' . $tamanhoMinimo . ' caracteres.',
            lang('Auth.errorPasswordCommon') => 'A senha informada e muito comum. Escolha outra senha.',
            lang('Auth.errorPasswordPersonal') =>
                'A senha nao pode conter informacoes do nome de usuario ou do e-mail.',
            lang('Auth.errorPasswordTooSimilar') => 'A senha e muito semelhante ao nome de usuario.',
        ];

        return $mensagens[$motivo ?? ''] ?? 'A senha nao atende as regras de seguranca do sistema.';
    }

    /**
     * @return array{sucesso: false, tipo: string, codigo: string, mensagem: string}
     */
    private function erro(string $tipo, string $codigo, string $mensagem): array
    {
        return [
            'sucesso'  => false,
            'tipo'     => $tipo,
            'codigo'   => $codigo,
            'mensagem' => $mensagem,
        ];
    }
}
