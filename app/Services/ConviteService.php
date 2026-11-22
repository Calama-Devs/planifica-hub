<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConviteModel;
use CodeIgniter\Shield\Entities\User;
use Throwable;

class ConviteService
{
    private AuditoriaService $auditoria;

    public function __construct(?AuditoriaService $auditoria = null)
    {
        $this->auditoria = $auditoria ?? service('auditoriaService');
    }

    /** @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string} */
    public function emitirConvite(string $emailInformado, int $emissorId): array
    {
        $usuario = auth()->getProvider()->findById($emissorId);
        if ($usuario === null || ! $usuario->can('contas.criar')) {
            return $this->resultado('permissao', 'CONTAS_SEM_PERMISSAO', 'Sem permissão para emitir convites.');
        }

        $email = strtolower(trim($emailInformado));
        if (
            filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ! str_ends_with($email, '@ifro.edu.br')
            || substr_count($email, '@') !== 1
        ) {
            return $this->resultado(
                'validacao',
                'CONVITE_EMAIL_INVALIDO',
                'Informe um e-mail institucional @ifro.edu.br válido.'
            );
        }

        if (auth()->getProvider()->findByCredentials(['email' => $email]) !== null) {
            return $this->resultado(
                'regra_negocio',
                'CONVITE_CONTA_EXISTENTE',
                'Já existe uma conta cadastrada para este e-mail.'
            );
        }

        return $this->registrarEEnviar($email, config('Convite')->validadeMinutos);
    }

    /** @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string, dados: array{email: string}|null} */
    public function validarConvite(?string $token): array
    {
        $token = is_string($token) ? trim($token) : '';
        if (! preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            return $this->resultadoInvalido('CONVITE_INVALIDO', 'Este convite não é válido.');
        }

        $convite = model(ConviteModel::class)->buscarPorTokenHash(hash('sha256', $token));
        if ($convite === null) {
            return $this->resultadoInvalido('CONVITE_INVALIDO', 'Este convite não é válido.');
        }

        if ($convite['status'] === 'utilizado') {
            return $this->resultadoInvalido('CONVITE_UTILIZADO', 'Este convite já foi utilizado.');
        }

        if ($convite['expira_em'] <= date('Y-m-d H:i:s')) {
            return $this->resultadoInvalido('CONVITE_EXPIRADO', 'Este convite expirou.');
        }

        if (! in_array($convite['status'], ['preparado', 'enviado'], true)) {
            return $this->resultadoInvalido('CONVITE_INVALIDO', 'Este convite não é válido.');
        }

        if (auth()->getProvider()->findByCredentials(['email' => $convite['email']]) !== null) {
            return $this->resultadoInvalido('CONVITE_CONTA_EXISTENTE', 'Já existe uma conta para este convite.');
        }

        return [
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'CONVITE_VALIDO',
            'mensagem' => 'Convite válido.',
            'dados'    => ['email' => $convite['email']],
        ];
    }

    /** @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string} */
    public function concluirCadastro(string $token, string $senha, string $nomeCompleto): array
    {
        $validacao = $this->validarConvite($token);
        if (! $validacao['sucesso']) {
            unset($validacao['dados']);

            return $validacao;
        }

        $usuarioParaValidacao = new User([
            'username' => null,
            'email'    => $validacao['dados']['email'],
        ]);
        if (! service('passwords')->check($senha, $usuarioParaValidacao)->isOK()) {
            return $this->resultado(
                'validacao',
                'SENHA_INVALIDA',
                'Informe uma senha que atenda aos requisitos de segurança.'
            );
        }

        $convites = model(ConviteModel::class);
        $convite  = $convites->buscarPorTokenHash(hash('sha256', trim($token)));
        $agora    = date('Y-m-d H:i:s');
        $db       = db_connect();
        $nomeCompleto = preg_replace('/\s+/', ' ', trim($nomeCompleto)) ?? '';

        $db->transException(true)->transBegin();

        try {
            if ($convite === null || ! $convites->consumirValido((int) $convite['id'], $agora)) {
                $db->transRollback();

                return $this->resultado(
                    'regra_negocio',
                    'CONVITE_UTILIZADO',
                    'Este convite não está mais disponível.'
                );
            }

            $usuarios = auth()->getProvider();
            if ($usuarios->findByCredentials(['email' => $convite['email']]) !== null) {
                $db->transRollback();

                return $this->resultado(
                    'regra_negocio',
                    'CONVITE_CONTA_EXISTENTE',
                    'Já existe uma conta para este convite.'
                );
            }

            $usuario = $usuarios->createNewUser([
                'username'      => null,
                'nome_completo' => $nomeCompleto,
                'email'         => $convite['email'],
                'password'      => $senha,
            ]);
            $usuario->username = null;

            if (! $usuarios->save($usuario)) {
                throw new \RuntimeException('Não foi possível concluir o cadastro.');
            }

            $usuarioCriado = $usuarios->findById($usuarios->getInsertID());
            if ($usuarioCriado === null) {
                throw new \RuntimeException('Não foi possível concluir o cadastro.');
            }

            $usuarios->activate($usuarioCriado);

            $this->auditoria->registrar([
                'modulo'        => 'convites',
                'acao'          => 'concluir_cadastro',
                'entidade_tipo' => 'usuario',
                'entidade_id'   => (string) $usuarioCriado->id,
                'descricao'     => 'Cadastro concluído a partir de um convite.',
                'resultado'     => 'concluida',
            ]);

            if (! $db->transCommit()) {
                throw new \RuntimeException('Não foi possível concluir o cadastro.');
            }
        } catch (Throwable) {
            $db->transRollback();
            log_message('error', 'Não foi possível concluir o cadastro por convite.');

            return $this->resultado('inesperado', 'CADASTRO_FALHOU', 'Não foi possível concluir o cadastro.');
        }

        return $this->resultado(
            'sucesso',
            'CADASTRO_CONCLUIDO',
            'Cadastro concluído. Agora você já pode entrar.',
            true
        );
    }

    /** @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string} */
    private function registrarEEnviar(string $email, int $validadeMinutos): array
    {
        $convites = model(ConviteModel::class);
        $agora    = date('Y-m-d H:i:s');
        $expiraEm = date('Y-m-d H:i:s', strtotime("+{$validadeMinutos} minutes"));
        $token    = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $db       = db_connect();

        $db->transException(true)->transBegin();

        try {
            if (! $convites->expirarPendentes($email, $agora)) {
                throw new \RuntimeException('Não foi possível expirar os convites anteriores.');
            }

            if ($convites->buscarPendenteValidoPorEmail($email, $agora) !== null) {
                $db->transRollback();

                return $this->resultado(
                    'regra_negocio',
                    'CONVITE_PENDENTE',
                    'Já existe um convite válido para este e-mail.'
                );
            }

            $conviteId = $convites->insert([
                'email'          => $email,
                'email_pendente' => $email,
                'token_hash'      => hash('sha256', $token),
                'status'         => 'preparado',
                'expira_em'      => $expiraEm,
            ], true);

            if ($conviteId === false || ! $db->transCommit()) {
                throw new \RuntimeException('Não foi possível preparar o convite.');
            }
        } catch (Throwable) {
            $db->transRollback();

            if ($convites->buscarPendenteValidoPorEmail($email, $agora) !== null) {
                return $this->resultado(
                    'regra_negocio',
                    'CONVITE_PENDENTE',
                    'Já existe um convite válido para este e-mail.'
                );
            }

            log_message('error', 'Não foi possível preparar a emissão de convite.');

            return $this->resultado('inesperado', 'CONVITE_PREPARO_FALHOU', 'Não foi possível emitir o convite.');
        }

        $emailService = service('email');
        $emailService->clear();
        $emailService->setMailType('html');
        $emailService->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
        $emailService->setTo($email);
        $emailService->setSubject('Convite para cadastro no PlanIFica Hub');
        $linkFinalizacao = site_url('finalizar-cadastro?token=' . rawurlencode($token));
        $emailService->setMessage(view('emails/convite_cadastro', [
            'linkFinalizacao' => $linkFinalizacao,
            'validadeMinutos' => $validadeMinutos,
        ]));
        $emailService->setAltMessage(
            "Você recebeu um convite para finalizar seu cadastro no PlanIFica Hub.\n\n"
            . "Finalize seu cadastro: {$linkFinalizacao}\n\n"
            . "Este convite é válido por {$validadeMinutos} minutos."
        );

        $envioAceito      = $emailService->send();
        $momentoResultado = date('Y-m-d H:i:s');

        if ($envioAceito) {
            $db->transException(true)->transBegin();

            try {
                if (! $convites->marcarEnvioAceito((int) $conviteId, $momentoResultado)) {
                    throw new \RuntimeException('Falha ao atualizar o estado do convite.');
                }

                $this->auditoria->registrar([
                    'modulo'        => 'convites',
                    'acao'          => 'emitir_convite',
                    'entidade_tipo' => 'convite',
                    'entidade_id'   => (string) $conviteId,
                    'descricao'     => 'Convite emitido com sucesso.',
                    'resultado'     => 'concluida',
                ]);

                if (! $db->transCommit()) {
                    throw new \RuntimeException('Falha ao confirmar o estado do convite.');
                }

                return $this->resultado('sucesso', 'CONVITE_ENVIO_ACEITO', 'Convite enviado com sucesso.', true);
            } catch (Throwable $erro) {
                $db->transRollback();
                log_message('error', 'Falha ao registrar auditoria da emissao do convite: {erro}', [
                    'erro' => $erro->getMessage(),
                ]);
            }
        } elseif ($convites->marcarFalhaEnvio((int) $conviteId, $momentoResultado)) {
            return $this->resultado('inesperado', 'CONVITE_ENVIO_FALHOU', 'Não foi possível enviar o convite.');
        }

        log_message('error', 'Não foi possível atualizar o estado do envio do convite.');

        return $this->resultado(
            'inesperado',
            'CONVITE_STATUS_FALHOU',
            'Não foi possível concluir a emissão do convite.'
        );
    }

    /** @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string} */
    private function resultado(string $tipo, string $codigo, string $mensagem, bool $sucesso = false): array
    {
        return [
            'sucesso'  => $sucesso,
            'tipo'     => $tipo,
            'codigo'   => $codigo,
            'mensagem' => $mensagem,
        ];
    }

    /** @return array{sucesso: false, tipo: string, codigo: string, mensagem: string, dados: null} */
    private function resultadoInvalido(string $codigo, string $mensagem): array
    {
        return [
            'sucesso'  => false,
            'tipo'     => 'regra_negocio',
            'codigo'   => $codigo,
            'mensagem' => $mensagem,
            'dados'    => null,
        ];
    }
}
