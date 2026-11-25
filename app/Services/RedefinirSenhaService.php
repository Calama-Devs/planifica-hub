<?php

namespace App\Services;

use App\Models\IdentidadeRecuperacaoModel;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\I18n\Time;
use RuntimeException;
use Throwable;

class RedefinirSenhaService
{
    private IdentidadeRecuperacaoModel $modeloIdentidade;

    private AuditoriaService $auditoria;

    public function __construct(
        ?IdentidadeRecuperacaoModel $modeloIdentidade = null,
        ?AuditoriaService $auditoria = null
    ) {
        $this->modeloIdentidade = $modeloIdentidade ?? model(IdentidadeRecuperacaoModel::class);
        $this->auditoria        = $auditoria ?? new AuditoriaService();
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

        return $this->gravarNovaSenha($usuario, $senhaNova, $confirmacao, [
            'modulo'        => 'perfil',
            'acao'          => 'alterar_senha',
            'entidade_tipo' => 'usuario',
            'entidade_id'   => (string) $usuario->id,
            'descricao'     => 'Senha alterada pelo próprio usuário na área Meu Perfil.',
            'resultado'     => 'concluida',
        ]);
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

            $this->modeloIdentidade->deleteIdentitiesByType($usuario, IdentidadeRecuperacaoModel::TIPO_TOKEN);

            helper('text');
            $token = random_string('crypto', 20);
            $this->modeloIdentidade->insert([
                'user_id' => $usuario->id,
                'type'    => IdentidadeRecuperacaoModel::TIPO_TOKEN,
                'secret'  => $token,
                'extra'   => (string) $usuario->versao_sessao,
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
                $this->modeloIdentidade->deleteIdentitiesByType($usuario, IdentidadeRecuperacaoModel::TIPO_TOKEN);
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
            $identidade = $this->modeloIdentidade->getIdentityBySecret(IdentidadeRecuperacaoModel::TIPO_TOKEN, $token);

            if ($identidade === null || ! $this->modeloIdentidade->consumirToken((int) $identidade->id)) {
                return $this->erro('validacao', 'TOKEN_INVALIDO', 'Token de redefinição inválido ou expirado.');
            }

            if (Time::now()->isAfter($identidade->expires)) {
                return $this->erro('validacao', 'TOKEN_EXPIRADO', 'Token de redefinição inválido ou expirado.');
            }

            /** @var UserModel $modeloUsuario */
            $modeloUsuario = model(UserModel::class);
            $usuario       = $modeloUsuario->findById($identidade->user_id);

            if (
                $usuario === null || (int) $usuario->active !== 1 || (int) $usuario->versao_sessao < 1
                || $identidade->extra !== (string) $usuario->versao_sessao
            ) {
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
        return $this->gravarNovaSenha($usuario, $senha, $confirmacao, null);
    }

    /**
     * @param array<string, string>|null $eventoAuditoria Evento gravado na mesma transação da nova senha.
     *
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}
     */
    private function gravarNovaSenha(User $usuario, string $senha, string $confirmacao, ?array $eventoAuditoria): array
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

            if (! $this->salvarCredencial($usuario, (int) $identidade->id, $hashSenha, $eventoAuditoria)) {
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
     * Grava a credencial e, quando houver, o evento de auditoria na mesma transação.
     *
     * @param array<string, string>|null $eventoAuditoria
     */
    private function salvarCredencial(
        User $usuario,
        int $identidadeId,
        string $hashSenha,
        ?array $eventoAuditoria
    ): bool {
        $banco = db_connect();

        if (! $banco->transBegin()) {
            return false;
        }

        try {
            if (! $this->modeloIdentidade->atualizarSenhaDaContaAtiva($usuario, $identidadeId, $hashSenha)) {
                throw new RuntimeException('A nova senha não foi gravada.');
            }

            if ($eventoAuditoria !== null) {
                $this->auditoria->registrar($eventoAuditoria);
            }

            if ($banco->transStatus() === false || ! $banco->transCommit()) {
                throw new RuntimeException('Falha na transação.');
            }
        } catch (Throwable $erro) {
            $banco->transRollback();
            log_message('error', '[RedefinirSenhaService::salvarCredencial] {erro}', ['erro' => $erro->getMessage()]);

            return false;
        }

        return true;
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
