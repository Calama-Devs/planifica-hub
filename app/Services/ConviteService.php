<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConviteModel;
use Config\AuthGroups;
use Config\Email as EmailConfig;
use Throwable;

class ConviteService
{
    private AuditoriaService $auditoria;

    public function __construct(?AuditoriaService $auditoria = null)
    {
        $this->auditoria = $auditoria ?? service('auditoriaService');
    }

    /** @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string} */
    public function emitirConvite(string $emailInformado, int $emissorId, array $gruposInformados = []): array
    {
        $usuario = auth()->getProvider()->findById($emissorId);
        if ($usuario === null || ! $usuario->can('contas.criar')) {
            return $this->resultado('permissao', 'CONTAS_SEM_PERMISSAO', 'Sem permissão para emitir convites.');
        }

        $grupos = $this->resolverGrupos($gruposInformados, $usuario->can('acessos.administrar'));
        if (isset($grupos['sucesso'])) {
            return $grupos;
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

        return $this->registrarEEnviar($email, config('Convite')->validadeMinutos, $grupos);
    }

    /** @param list<string> $grupos */
    private function registrarEEnviar(string $email, int $validadeMinutos, array $grupos): array
    {
        $configuracaoEmail = config(EmailConfig::class);
        $remetenteEmail    = trim($configuracaoEmail->fromEmail);
        $remetenteNome     = trim($configuracaoEmail->fromName);

        if (filter_var($remetenteEmail, FILTER_VALIDATE_EMAIL) === false) {
            log_message('error', 'Configuração do remetente de e-mail ausente ou inválida.');

            return $this->resultado(
                'inesperado',
                'CONVITE_REMETENTE_INVALIDO',
                'Não foi possível enviar o convite. Verifique a configuração de e-mail.'
            );
        }

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
                'grupos'         => json_encode($grupos, JSON_THROW_ON_ERROR),
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

        $linkFinalizacao = site_url('finalizar-cadastro?token=' . rawurlencode($token));
        $emailService     = service('email');

        try {
            $emailService->clear();
            $emailService->setMailType('html');
            $emailService->setFrom($remetenteEmail, $remetenteNome);
            $emailService->setTo($email);
            $emailService->setSubject('Convite para cadastro no PlanIFica Hub');
            $emailService->setMessage(view('emails/convite_cadastro', [
                'linkFinalizacao' => $linkFinalizacao,
                'validadeMinutos' => $validadeMinutos,
            ]));
            $emailService->setAltMessage(
                "Você recebeu um convite para cadastro no PlanIFica Hub.\n\n"
                . "Finalize seu cadastro: {$linkFinalizacao}\n\n"
                . "Este convite é válido por {$validadeMinutos} minutos."
            );

            $envioAceito = $emailService->send();
        } catch (Throwable $erro) {
            log_message('error', 'Falha operacional no envio do convite: {tipo}', [
                'tipo' => $erro::class,
            ]);
            $envioAceito = false;
        }

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

                if ($convites->marcarFalhaEnvio((int) $conviteId, $momentoResultado)) {
                    return $this->resultado(
                        'inesperado',
                        'CONVITE_STATUS_FALHOU',
                        'Não foi possível concluir a emissão do convite.'
                    );
                }
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

    /** @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}|list<string> */
    private function resolverGrupos(array $gruposInformados, bool $podeAdministrarAcessos): array
    {
        $grupos = [];

        foreach ($gruposInformados as $grupo) {
            if (! is_string($grupo) || trim($grupo) === '') {
                return $this->resultado('validacao', 'CONVITE_GRUPO_INVALIDO', 'Informe grupos válidos.');
            }

            $grupos[] = trim($grupo);
        }

        $grupos = array_values(array_unique($grupos));
        $grupos = $grupos === [] ? ['user'] : $grupos;

        $gruposDisponiveis = config(AuthGroups::class)->groups;
        foreach ($grupos as $grupo) {
            if (! array_key_exists($grupo, $gruposDisponiveis)) {
                return $this->resultado('validacao', 'CONVITE_GRUPO_INVALIDO', 'Informe grupos válidos.');
            }
        }

        if (! $podeAdministrarAcessos && $grupos !== ['user']) {
            return $this->resultado(
                'permissao',
                'CONVITE_GRUPOS_SEM_PERMISSAO',
                'Sem permissão para definir grupos no convite.'
            );
        }

        return $grupos;
    }
}
