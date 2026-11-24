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
    public function emitirConvite(string $emailInformado, array $gruposInformados = []): array
    {
        $usuario = auth()->user();
        if ($usuario === null || ! $usuario->can('contas.criar')) {
            return [
                'sucesso'  => false,
                'tipo'     => 'permissao',
                'codigo'   => 'CONTAS_SEM_PERMISSAO',
                'mensagem' => 'Sem permissão para emitir convites.',
            ];
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
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'CONVITE_EMAIL_INVALIDO',
                'mensagem' => 'Informe um e-mail institucional @ifro.edu.br válido.',
            ];
        }

        if (auth()->getProvider()->findByCredentials(['email' => $email]) !== null) {
            return [
                'sucesso'  => false,
                'tipo'     => 'regra_negocio',
                'codigo'   => 'CONVITE_CONTA_EXISTENTE',
                'mensagem' => 'Já existe uma conta cadastrada para este e-mail.',
            ];
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

            return [
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'CONVITE_REMETENTE_INVALIDO',
                'mensagem' => 'Não foi possível enviar o convite. Verifique a configuração de e-mail.',
            ];
        }

        $convites = model(ConviteModel::class);
        $agora    = date('Y-m-d H:i:s');
        $expiraEm = date('Y-m-d H:i:s', strtotime("+{$validadeMinutos} minutes"));
        $token    = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $db       = db_connect();
        $etapaEnvio = false;
        $emissorId  = (int) auth()->id();

        $db->transException(true)->transBegin();

        try {
            if (! $convites->expirarPendentes($email, $agora)) {
                throw new \RuntimeException('Não foi possível expirar os convites anteriores.');
            }

            if ($convites->buscarPendenteValidoPorEmail($email, $agora) !== null) {
                $db->transRollback();

                return [
                    'sucesso'  => false,
                    'tipo'     => 'regra_negocio',
                    'codigo'   => 'CONVITE_PENDENTE',
                    'mensagem' => 'Já existe um convite válido para este e-mail.',
                ];
            }

            $conviteId = $convites->insert([
                'emissor_id'    => $emissorId,
                'email'          => $email,
                'email_pendente' => $email,
                'token_hash'      => hash('sha256', $token),
                'status'         => 'pendente',
                'expira_em'      => $expiraEm,
                'grupos'         => json_encode($grupos, JSON_THROW_ON_ERROR),
            ], true);

            if ($conviteId === false) {
                throw new \RuntimeException('Não foi possível preparar o convite.');
            }

            $this->auditoria->registrar([
                'modulo'        => 'convites',
                'acao'          => 'emitir_convite',
                'entidade_tipo' => 'convite',
                'entidade_id'   => (string) $conviteId,
                'descricao'     => 'Convite registrado para envio.',
                'resultado'     => 'concluida',
            ]);

            if (! $db->transCommit()) {
                throw new \RuntimeException('Não foi possível confirmar a emissão do convite.');
            }
            $etapaEnvio    = true;
            $linkFinalizacao = site_url('finalizar-cadastro?token=' . rawurlencode($token));
            $emailService     = service('email');

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
            if (! $envioAceito) {
                throw new \RuntimeException('Resultado do transporte de e-mail não confirmado.');
            }
        } catch (Throwable $erro) {
            if (! $etapaEnvio) {
                $db->transRollback();
            }

            if (! $etapaEnvio && $convites->buscarPendenteValidoPorEmail($email, $agora) !== null) {
                return [
                    'sucesso'  => false,
                    'tipo'     => 'regra_negocio',
                    'codigo'   => 'CONVITE_PENDENTE',
                    'mensagem' => 'Já existe um convite válido para este e-mail.',
                ];
            }

            log_message(
                'error',
                'Falha na emissão do convite. Operador {emissor_id}, exceção {tipo}.',
                [
                'emissor_id' => $emissorId,
                'tipo'       => $erro::class,
                ]
            );

            if ($etapaEnvio) {
                return [
                    'sucesso'  => false,
                    'tipo'     => 'inesperado',
                    'codigo'   => 'CONVITE_ENVIO_FALHOU',
                    'mensagem' => 'Não foi possível confirmar o envio do convite.',
                ];
            }

            return [
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'CONVITE_PREPARO_FALHOU',
                'mensagem' => 'Não foi possível emitir o convite.',
            ];
        }

        return [
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'CONVITE_ENVIO_ACEITO',
            'mensagem' => 'Convite enviado com sucesso.',
        ];
    }

    /** @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}|list<string> */
    private function resolverGrupos(array $gruposInformados, bool $podeAdministrarAcessos): array
    {
        $grupos = [];

        foreach ($gruposInformados as $grupo) {
            if (! is_string($grupo) || trim($grupo) === '') {
                return [
                    'sucesso'  => false,
                    'tipo'     => 'validacao',
                    'codigo'   => 'CONVITE_GRUPO_INVALIDO',
                    'mensagem' => 'Informe grupos válidos.',
                ];
            }

            $grupos[] = trim($grupo);
        }

        $grupos = array_values(array_unique($grupos));
        $grupos = $grupos === [] ? ['user'] : $grupos;

        $gruposDisponiveis = config(AuthGroups::class)->groups;
        foreach ($grupos as $grupo) {
            if (! array_key_exists($grupo, $gruposDisponiveis)) {
                return [
                    'sucesso'  => false,
                    'tipo'     => 'validacao',
                    'codigo'   => 'CONVITE_GRUPO_INVALIDO',
                    'mensagem' => 'Informe grupos válidos.',
                ];
            }
        }

        if (! $podeAdministrarAcessos && $grupos !== ['user']) {
            return [
                'sucesso'  => false,
                'tipo'     => 'permissao',
                'codigo'   => 'CONVITE_GRUPOS_SEM_PERMISSAO',
                'mensagem' => 'Sem permissão para definir grupos no convite.',
            ];
        }

        return $grupos;
    }
}
