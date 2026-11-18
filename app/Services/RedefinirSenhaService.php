<?php

namespace App\Services;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\I18n\Time;
use Throwable;

class RedefinirSenhaService
{
    private const TIPO_TOKEN_REDEFINICAO = 'magic-link-reset';

    private UserIdentityModel $modeloIdentidade;

    public function __construct(?UserIdentityModel $modeloIdentidade = null)
    {
        $this->modeloIdentidade = $modeloIdentidade ?? model(UserIdentityModel::class);
    }

    /**
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}
     */
    public function alterarPeloPerfil(
        User $usuario,
        string $senhaAtual,
        string $senhaNova,
        string $confirmacao
    ): array {
        if ($senhaAtual === '') {
            return $this->erro('validacao', 'SENHA_ATUAL_OBRIGATORIA', 'Informe sua senha atual.');
        }

        try {
            $identidade = $this->modeloIdentidade->getIdentityByType(
                $usuario,
                Session::ID_TYPE_EMAIL_PASSWORD
            );

            if ($identidade === null || ! is_string($identidade->secret2)) {
                log_message(
                    'error',
                    '[RedefinirSenhaService::alterarPeloPerfil] Usuário {id} não possui identidade email_password.',
                    ['id' => $usuario->id]
                );

                return $this->erro(
                    'regra_negocio',
                    'IDENTIDADE_NAO_ENCONTRADA',
                    'Não foi possível alterar a senha desta conta.'
                );
            }

            /** @var Passwords $senhas */
            $senhas = service('passwords');

            if (!$senhas->verify($senhaAtual, $identidade->secret2)) {
                return $this->erro(
                    'validacao',
                    'SENHA_ATUAL_INCORRETA',
                    'A senha atual informada está incorreta.'
                );
            }
        } catch (Throwable $erro) {
            log_message(
                'error',
                '[RedefinirSenhaService::alterarPeloPerfil] Erro ao validar senha do usuário {id}: {erro}',
                ['id' => $usuario->id, 'erro' => $erro->getMessage()]
            );

            return $this->erro(
                'inesperado',
                'ERRO_AO_VALIDAR_SENHA_ATUAL',
                'Ocorreu um erro ao validar sua senha atual. Tente novamente.'
            );
        }

        return $this->redefinir($usuario, $senhaNova, $confirmacao);
    }

    public function solicitarRedefinicao(string $email): void
    {
        try {
            /** @var UserModel $modeloUsuario */
            $modeloUsuario = model(UserModel::class);
            $usuario       = $modeloUsuario->findByCredentials(['email' => $email]);

            if ($usuario === null || (int) $usuario->active !== 1) {
                return;
            }

            $this->modeloIdentidade->deleteIdentitiesByType($usuario, self::TIPO_TOKEN_REDEFINICAO);

            helper('text');
            $token = random_string('crypto', 20);
            $this->modeloIdentidade->insert([
                'user_id' => $usuario->id,
                'type'    => self::TIPO_TOKEN_REDEFINICAO,
                'secret'  => $token,
                'expires' => Time::now()->addSeconds(setting('Auth.magicLinkLifetime')),
            ]);

            $emailRedefinicao = \Config\Services::email()->setMailType('html')
                ->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '')
                ->setTo($usuario->email)
                ->setSubject('Redefinição de senha')
                ->setMessage(view('redefinirsenha/mensagem_email', [
                    'token'          => $token,
                    'user'           => $usuario,
                    'date'           => Time::now()->toDateTimeString(),
                    'urlVerificacao' => url_to('verificar-redefinicao'),
                ], ['debug' => false]));

            if ($emailRedefinicao->send(false) === false) {
                log_message('error', '[RedefinirSenhaService::solicitarRedefinicao] Falha ao enviar e-mail.');
                $this->modeloIdentidade->deleteIdentitiesByType($usuario, self::TIPO_TOKEN_REDEFINICAO);
            }

            $emailRedefinicao->clear();
        } catch (Throwable $erro) {
            log_message(
                'error',
                '[RedefinirSenhaService::solicitarRedefinicao] Erro inesperado: {erro}',
                ['erro' => $erro->getMessage()]
            );
        }
    }

    /**
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string, usuario?: User}
     */
    public function validarTokenRedefinicao(?string $token): array
    {
        try {
            $identidade = $this->modeloIdentidade->getIdentityBySecret(self::TIPO_TOKEN_REDEFINICAO, $token);

            if ($identidade === null) {
                return $this->erro('validacao', 'TOKEN_INVALIDO', 'Token de redefinição inválido ou expirado.');
            }

            $this->modeloIdentidade->delete($identidade->id);

            if (Time::now()->isAfter($identidade->expires)) {
                return $this->erro('validacao', 'TOKEN_EXPIRADO', 'Token de redefinição inválido ou expirado.');
            }

            /** @var UserModel $modeloUsuario */
            $modeloUsuario = model(UserModel::class);
            $usuario       = $modeloUsuario->findById($identidade->user_id);

            if ($usuario === null || (int) $usuario->active !== 1) {
                return $this->erro('permissao', 'USUARIO_INDISPONIVEL', 'Não foi possível concluir a redefinição.');
            }

            return [
                'sucesso'  => true,
                'tipo'     => 'sucesso',
                'codigo'   => 'TOKEN_VALIDO',
                'mensagem' => 'Token validado com sucesso.',
                'usuario'  => $usuario,
            ];
        } catch (Throwable $erro) {
            log_message(
                'error',
                '[RedefinirSenhaService::validarTokenRedefinicao] Erro inesperado: {erro}',
                ['erro' => $erro->getMessage()]
            );

            return $this->erro('inesperado', 'ERRO_AO_VALIDAR_TOKEN', 'Não foi possível concluir a redefinição.');
        }
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

            if ($identidade === null || ! is_string($identidade->secret2)) {
                log_message(
                    'error',
                    '[RedefinirSenhaService::redefinir] Usuário {id} não possui identidade email_password.',
                    ['id' => $usuario->id]
                );

                return $this->erro(
                    'regra_negocio',
                    'IDENTIDADE_NAO_ENCONTRADA',
                    'Não foi possível redefinir a senha desta conta.'
                );
            }

            /** @var Passwords $senhas */
            $senhas = service('passwords');

            if ($senhas->verify($senha, $identidade->secret2)) {
                return $this->erro(
                    'validacao',
                    'NOVA_SENHA_IGUAL_ATUAL',
                    'A nova senha deve ser diferente da senha atual.'
                );
            }

            $hashSenha = $senhas->hash($senha);

            if (! is_string($hashSenha)) {
                log_message(
                    'error',
                    '[RedefinirSenhaService::redefinir] Não foi possível gerar hash para o usuário {id}.',
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
                    '[RedefinirSenhaService::redefinir] Falha ao atualizar a senha do usuário {id}.',
                    ['id' => $usuario->id]
                );

                return $this->erro('inesperado', 'SENHA_NAO_ATUALIZADA', 'Erro ao redefinir senha.');
            }

            log_message('info', '[RedefinirSenhaService::redefinir] Senha redefinida para o usuário {id}.', [
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
                '[RedefinirSenhaService::redefinir] Erro inesperado para o usuário {id}: {erro}',
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

        return [
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'SENHA_VALIDA',
            'mensagem' => 'Senha válida.',
        ];
    }

    private function traduzirMotivoShield(?string $motivo): string
    {
        $tamanhoMinimo = config('Auth')->minimumPasswordLength;
        $mensagens = [
            lang('Auth.errorPasswordEmpty') => 'Informe a nova senha.',
            lang('Auth.errorPasswordLength', [$tamanhoMinimo]) =>
                'A senha deve ter pelo menos ' . $tamanhoMinimo . ' caracteres.',
            lang('Auth.errorPasswordCommon') => 'A senha informada é muito comum. Escolha outra senha.',
            lang('Auth.errorPasswordPersonal') =>
                'A senha não pode conter informações do nome de usuário ou do e-mail.',
            lang('Auth.errorPasswordTooSimilar') => 'A senha é muito semelhante ao nome de usuário.',
        ];

        return $mensagens[$motivo ?? ''] ?? 'A senha não atende às regras de segurança do sistema.';
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
