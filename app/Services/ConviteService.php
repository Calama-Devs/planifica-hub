<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConviteModel;
use Config\AuthGroups;
use Config\Email as EmailConfig;
use Throwable;

class ConviteService
{
    private const VALIDADE_CONVITE_MINUTOS = 30;

    public const ESTADO_VALIDO = 'valido';
    public const ESTADO_EXPIRADO = 'expirado';
    public const ESTADO_INVALIDO = 'invalido';
    public const ESTADO_UTILIZADO = 'utilizado';

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
        if (auth()->getProvider()->findByCredentials(['email' => $email]) !== null) {
            return [
                'sucesso'  => false,
                'tipo'     => 'regra_negocio',
                'codigo'   => 'CONVITE_CONTA_EXISTENTE',
                'mensagem' => 'Já existe uma conta cadastrada para este e-mail.',
            ];
        }

        return $this->registrarEEnviar($email, $grupos);
    }

    /** @return array<string, mixed> */
    public function validarConvite(?string $token): array
    {
        if ($token === null || ! preg_match('/^[a-zA-Z0-9_-]{8,128}$/', trim($token))) {
            return $this->estado(
                self::ESTADO_INVALIDO,
                'CONVITE_INVALIDO',
                'Este link de convite não está disponível para utilização.'
            );
        }
        try {
            $convite = model(ConviteModel::class)->where('token_hash', hash('sha256', trim($token)))->first();
        } catch (Throwable $erro) {
            log_message('error', 'Falha na consulta do convite. Exceção {tipo}.', ['tipo' => $erro::class]);
            return $this->estado(
                'erro_temporario',
                'CONVITE_ERRO',
                'Não foi possível consultar o convite. Tente novamente.'
            );
        }
        if ($convite === null) {
            return $this->estado(
                self::ESTADO_INVALIDO,
                'CONVITE_NAO_ENCONTRADO',
                'Este link de convite não está disponível para utilização.'
            );
        }
        if ($convite['status'] === 'consumido') {
            return $this->estado(self::ESTADO_UTILIZADO, 'CONVITE_UTILIZADO', 'Este convite já foi utilizado.');
        }
        if ($convite['status'] === 'expirado') {
            return $this->estado(self::ESTADO_EXPIRADO, 'CONVITE_EXPIRADO', 'O prazo deste convite terminou.');
        }
        if ($convite['status'] !== 'pendente') {
            return $this->estado(
                self::ESTADO_INVALIDO,
                'CONVITE_INDISPONIVEL',
                'Este link de convite não está disponível para utilização.'
            );
        }
        if (strtotime($convite['expira_em']) <= time()) {
            return $this->estado(self::ESTADO_EXPIRADO, 'CONVITE_EXPIRADO', 'O prazo deste convite terminou.');
        }
        try {
            $contaExistente = auth()->getProvider()->findByCredentials(['email' => $convite['email']]);
        } catch (Throwable $erro) {
            log_message('error', 'Falha na consulta da conta convidada. Exceção {tipo}.', ['tipo' => $erro::class]);
            return $this->estado(
                'erro_temporario',
                'CONVITE_ERRO',
                'Não foi possível consultar o convite. Tente novamente.'
            );
        }
        if ($contaExistente !== null) {
            return $this->estado(
                self::ESTADO_INVALIDO,
                'CONVITE_CONTA_EXISTENTE',
                'Este link de convite não está disponível para utilização.'
            );
        }
        $grupos = $this->gruposDoConvite($convite['grupos']);
        if ($grupos === null) {
            return $this->estado(
                self::ESTADO_INVALIDO,
                'CONVITE_GRUPO_INVALIDO',
                'Este link de convite não está disponível para utilização.'
            );
        }
        $gruposDisponiveis = config(AuthGroups::class)->groups;
        return [
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'CONVITE_VALIDO',
            'estado'   => self::ESTADO_VALIDO,
            'mensagem' => 'Convite válido.',
            'dados'    => [
                'token'  => trim($token),
                'email'  => $convite['email'],
                'grupos' => array_map(
                    static fn (string $grupo): array => [
                        'codigo' => $grupo,
                        'rotulo' => $gruposDisponiveis[$grupo]['title'] . ' (' . $grupo . ')',
                    ],
                    $grupos
                ),
            ],
        ];
    }

    /** @param array{token:string,nome_completo:string,password:string,password_confirm:string} $dados */
    public function concluirCadastro(array $dados): array
    {
        if (auth()->loggedIn()) {
            return [
                'sucesso' => false,
                'tipo' => 'regra_negocio',
                'codigo' => 'SESSAO_ATIVA',
                'mensagem' => 'Você já está conectado em outra conta. Saia dessa conta e abra o convite novamente, '
                    . 'ou abra o link em uma janela anônima.',
            ];
        }
        $db = db_connect();
        $convites = model(ConviteModel::class);
        $tokenHash = hash('sha256', trim($dados['token']));
        try {
            $db->transException(true)->transBegin();
            $agora = date('Y-m-d H:i:s');
            if (! $convites->consumirPendente($tokenHash, $agora)) {
                $db->transRollback();
                return [
                    'sucesso' => false,
                    'tipo' => 'regra_negocio',
                    'codigo' => 'CONVITE_INDISPONIVEL',
                    'mensagem' => 'Este convite é inválido, expirou ou já foi utilizado. '
                        . 'Confira o link ou solicite um novo convite ao responsável.',
                ];
            }
            $convite = $convites->where('token_hash', $tokenHash)->first();
            if (
                $convite === null
                || auth()->getProvider()->findByCredentials(['email' => $convite['email']]) !== null
            ) {
                throw new \RuntimeException('Convite ou e-mail indisponível.');
            }
            $provider = auth()->getProvider();
            $grupos = $this->gruposDoConvite($convite['grupos']);
            if ($grupos === null) {
                throw new \RuntimeException('Grupos do convite inválidos.');
            }
            $user = $provider->createNewUser([
                'nome_completo' => $dados['nome_completo'],
                'email' => $convite['email'],
                'password' => $dados['password'],
                'active' => true,
            ]);
            $validacaoSenha = service('passwords')->check($dados['password'], $user);
            if (! $validacaoSenha->isOK()) {
                $db->transRollback();
                return [
                    'sucesso' => false,
                    'tipo' => 'validacao',
                    'codigo' => 'SENHA_INVALIDA',
                    'mensagem' => 'A senha não atende aos requisitos de segurança. '
                        . 'Escolha uma senha menos comum e que não contenha dados pessoais.',
                ];
            }
            if (! $provider->save($user)) {
                throw new \RuntimeException('Falha ao criar usuário.');
            }
            $user = $provider->findById((int) $provider->getInsertID());
            if ($user === null) {
                throw new \RuntimeException('Usuário criado não encontrado.');
            }
            $user->addGroup(...$grupos);
            $this->auditoria->registrar([
                'modulo' => 'convites',
                'acao' => 'concluir_cadastro',
                'entidade_tipo' => 'usuario',
                'entidade_id' => (string) $user->id,
                'descricao' => 'Cadastro concluído por convite ' . $convite['id']
                    . ', emitido por ' . $convite['emissor_id'] . '.',
                'resultado' => 'concluida',
            ]);
            if ($db->transStatus() === false || ! $db->transCommit()) {
                throw new \RuntimeException('Falha na transação.');
            }
            return [
                'sucesso' => true,
                'tipo' => 'sucesso',
                'codigo' => 'CADASTRO_CONCLUIDO',
                'mensagem' => 'Cadastro concluído. Faça login para continuar.',
            ];
        } catch (Throwable $erro) {
            $db->transRollback();
            log_message('error', 'Falha ao concluir cadastro por convite. Exceção {tipo}.', ['tipo' => $erro::class]);
            return [
                'sucesso' => false,
                'tipo' => 'inesperado',
                'codigo' => 'CADASTRO_FALHOU',
                'mensagem' => 'Não foi possível concluir o cadastro. Tente novamente.',
            ];
        }
    }

    private function estado(string $estado, string $codigo, string $mensagem): array
    {
        return [
            'sucesso' => false,
            'tipo' => 'regra_negocio',
            'codigo' => $codigo,
            'estado' => $estado,
            'mensagem' => $mensagem,
            'dados' => null,
        ];
    }

    /** @return list<string>|null */
    private function gruposDoConvite(?string $gruposJson): ?array
    {
        if ($gruposJson === null) {
            return [config(AuthGroups::class)->defaultGroup];
        }
        $grupos = json_decode($gruposJson, true);
        if (! is_array($grupos) || ! array_is_list($grupos) || $grupos === []) {
            return null;
        }
        foreach ($grupos as $grupo) {
            if (! is_string($grupo) || ! isset(config(AuthGroups::class)->groups[$grupo])) {
                return null;
            }
        }
        return $grupos;
    }

    /** @param list<string> $grupos */
    private function registrarEEnviar(string $email, array $grupos): array
    {
        $validadeMinutos = self::VALIDADE_CONVITE_MINUTOS;
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
            if (is_string($grupo)) {
                $grupo = trim($grupo);
            }
            if (! is_string($grupo) || $grupo === '') {
                return [
                    'sucesso'  => false,
                    'tipo'     => 'validacao',
                    'codigo'   => 'CONVITE_GRUPO_INVALIDO',
                    'mensagem' => 'Informe grupos válidos.',
                ];
            }

            $grupos[] = $grupo;
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
