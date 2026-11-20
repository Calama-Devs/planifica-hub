<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FalhaEnvioConfirmacaoEmailException;
use App\Models\AuditoriaEdicaoContaModel;
use App\Models\SolicitacaoTrocaEmailModel;
use App\Models\UsuarioAutenticacaoModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Email\Email;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Throwable;

/** Confirma a posse do novo e-mail antes de torná-lo identidade de login. */
class ConfirmacaoEmailContaService
{
    private const TIPO_TOKEN_REDEFINICAO = 'magic-link-reset';

    private SolicitacaoTrocaEmailModel $solicitacoes;
    private AuditoriaEdicaoContaModel $auditoria;
    private UserIdentityModel $identidades;
    private UsuarioAutenticacaoModel $contas;
    private BaseConnection $banco;
    private Email $email;

    public function __construct(
        ?SolicitacaoTrocaEmailModel $solicitacoes = null,
        ?AuditoriaEdicaoContaModel $auditoria = null,
        ?UserIdentityModel $identidades = null,
        ?UsuarioAutenticacaoModel $contas = null,
        ?BaseConnection $banco = null,
        ?Email $email = null,
    ) {
        $this->solicitacoes = $solicitacoes ?? model(SolicitacaoTrocaEmailModel::class);
        $this->auditoria = $auditoria ?? model(AuditoriaEdicaoContaModel::class);
        $this->identidades = $identidades ?? model(UserIdentityModel::class);
        $this->contas = $contas ?? model(UsuarioAutenticacaoModel::class);
        $this->banco = $banco ?? db_connect();
        $this->email = $email ?? service('email');
    }

    public function emailPendenteEmUso(string $email, int $contaId): bool
    {
        return $this->solicitacoes->where('conta_id !=', $contaId)
            ->where('email_novo', mb_strtolower(trim($email)))
            ->where('expira_em >', Time::now()->toDateTimeString())
            ->countAllResults() > 0;
    }

    /** Participa da transação de EdicaoContaService; este método não controla a transação. */
    public function solicitar(
        int $contaId,
        int $operadorId,
        string $nomeExibido,
        string $emailAnterior,
        string $emailNovo,
        string $justificativa,
        ?string $ip,
    ): void {
        helper('text');
        $token = random_string('crypto', 64);
        $agora = Time::now();

        $this->cancelarSolicitacoesAnteriores($contaId);

        if ($this->emailEmUso($emailNovo, $contaId) || $this->emailPendenteEmUso($emailNovo, $contaId)) {
            throw new \RuntimeException('O e-mail já possui uma solicitação pendente.');
        }

        $auditoriaId = $this->auditoria->insert([
            'conta_id' => $contaId,
            'operador_id' => $operadorId,
            'campos_alterados' => 'email',
            'email_anterior' => $emailAnterior,
            'email_novo' => $emailNovo,
            'justificativa' => $justificativa,
            'resultado' => 'pendente_confirmacao',
            'ip' => $ip,
            'created_at' => $agora->toDateTimeString(),
        ]);

        if (! is_int($auditoriaId) || $auditoriaId < 1) {
            throw new \RuntimeException('Falha ao auditar a solicitação de troca de e-mail.');
        }

        $solicitacaoCriada = $this->solicitacoes->insert([
            'conta_id' => $contaId,
            'operador_id' => $operadorId,
            'auditoria_id' => $auditoriaId,
            'email_anterior' => $emailAnterior,
            'email_novo' => $emailNovo,
            'justificativa' => $justificativa,
            'token_hash' => hash('sha256', $token),
            'expira_em' => $agora->addSeconds(config('Contas')->prazoConfirmacaoEmailSegundos)->toDateTimeString(),
            'created_at' => $agora->toDateTimeString(),
        ]);

        if (! is_int($solicitacaoCriada) || $solicitacaoCriada < 1) {
            throw new \RuntimeException('Falha ao criar a solicitação de troca de e-mail.');
        }

        $urlConfirmacao = url_to('contas.email.confirmar.form') . '?token=' . rawurlencode($token);

        if (! $this->enviarConfirmacao($emailNovo, $nomeExibido, $urlConfirmacao)) {
            throw new FalhaEnvioConfirmacaoEmailException('Falha ao enviar a confirmação do novo e-mail.');
        }
    }

    /** @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string} */
    public function confirmar(string $token): array
    {
        if ($token === '' || mb_strlen($token) > 128) {
            return $this->falha('validacao', 'TOKEN_EMAIL_INVALIDO', 'O link de confirmação é inválido ou expirou.');
        }

        $hashToken = hash('sha256', $token);
        $solicitacao = $this->solicitacoes->where('token_hash', $hashToken)->first();

        if ($solicitacao === null) {
            return $this->falha('validacao', 'TOKEN_EMAIL_INVALIDO', 'O link de confirmação é inválido ou expirou.');
        }

        $contaId = (int) $solicitacao['conta_id'];
        $emailNovo = mb_strtolower(trim((string) $solicitacao['email_novo']));
        $this->banco->transBegin();
        $donaDaTransacao = $this->banco->transDepth === 1;

        try {
            $tokenSubstituto = hash('sha256', random_bytes(32));
            $reivindicado = $this->banco->table('solicitacoes_troca_email')
                ->where('id', $solicitacao['id'])
                ->where('token_hash', $hashToken)
                ->update(['token_hash' => $tokenSubstituto]);

            if (! $reivindicado || $this->banco->affectedRows() !== 1) {
                $this->banco->transRollback();

                if ($donaDaTransacao) {
                    $this->banco->resetTransStatus();
                }

                return $this->falha(
                    'validacao',
                    'TOKEN_EMAIL_INVALIDO',
                    'O link de confirmação é inválido ou expirou.',
                );
            }

            if (strtotime((string) $solicitacao['expira_em']) < time()) {
                $this->finalizarAuditoria((int) $solicitacao['auditoria_id'], 'expirada');
                $this->solicitacoes->delete((int) $solicitacao['id']);
                $this->confirmarTransacao();

                return $this->falha(
                    'validacao',
                    'TOKEN_EMAIL_EXPIRADO',
                    'O link de confirmação é inválido ou expirou.',
                );
            }

            $conta = $this->contas->findById($contaId);
            $identidade = $this->identidades->where('user_id', $contaId)
                ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
                ->first();
            $identidadeCorresponde = $identidade !== null
                && mb_strtolower((string) $identidade->secret)
                    === mb_strtolower((string) $solicitacao['email_anterior']);

            if (! $conta instanceof User || ! $identidadeCorresponde) {
                $this->finalizarAuditoria((int) $solicitacao['auditoria_id'], 'cancelada');
                $this->solicitacoes->delete((int) $solicitacao['id']);
                $this->confirmarTransacao();

                return $this->falha(
                    'regra_negocio',
                    'SOLICITACAO_EMAIL_DESATUALIZADA',
                    'A solicitação de e-mail não pode mais ser confirmada.',
                );
            }

            if (mb_strlen($emailNovo) > 254 || filter_var($emailNovo, FILTER_VALIDATE_EMAIL) === false) {
                $this->finalizarAuditoria((int) $solicitacao['auditoria_id'], 'email_invalido');
                $this->solicitacoes->delete((int) $solicitacao['id']);
                $this->confirmarTransacao();

                return $this->falha(
                    'regra_negocio',
                    'EMAIL_INVALIDO',
                    'Este link não pode ser confirmado. Solicite uma nova alteração ao administrador.',
                );
            }

            if (! config('Contas')->emailInstitucionalPermitido($emailNovo)) {
                $this->finalizarAuditoria((int) $solicitacao['auditoria_id'], 'dominio_bloqueado');
                $this->solicitacoes->delete((int) $solicitacao['id']);
                $this->confirmarTransacao();

                return $this->falha(
                    'regra_negocio',
                    'EMAIL_DOMINIO_NAO_PERMITIDO',
                    'Este link não pode ser confirmado. Solicite uma nova alteração ao administrador.',
                );
            }

            if ($this->emailEmUso($emailNovo, $contaId) || $this->emailPendenteEmUso($emailNovo, $contaId)) {
                $this->finalizarAuditoria((int) $solicitacao['auditoria_id'], 'conflito');
                $this->solicitacoes->delete((int) $solicitacao['id']);
                $this->confirmarTransacao();

                return $this->falha('regra_negocio', 'EMAIL_DUPLICADO', 'Este e-mail já está vinculado a outra conta.');
            }

            if (! $this->identidades->update($identidade->id, ['secret' => $emailNovo])) {
                throw new \RuntimeException('Falha ao atualizar a identidade de e-mail.');
            }

            $this->identidades->deleteIdentitiesByType($conta, self::TIPO_TOKEN_REDEFINICAO);
            $this->identidades->deleteIdentitiesByType($conta, Session::ID_TYPE_MAGIC_LINK);

            $this->finalizarAuditoria((int) $solicitacao['auditoria_id'], 'sucesso');
            $this->solicitacoes->delete((int) $solicitacao['id']);

            $this->confirmarTransacao();
        } catch (Throwable $erro) {
            $this->banco->transRollback();

            if ($donaDaTransacao) {
                $this->banco->resetTransStatus();
            }

            if ($this->emailEstaEmUsoAposFalha($emailNovo, $contaId)) {
                $this->banco->transBegin();

                try {
                    $this->finalizarAuditoria((int) $solicitacao['auditoria_id'], 'conflito');
                    $this->solicitacoes->delete((int) $solicitacao['id']);
                    $this->confirmarTransacao();
                } catch (Throwable $erroAuditoria) {
                    $this->banco->transRollback();
                    $this->banco->resetTransStatus();
                    log_message(
                        'error',
                        '[ConfirmacaoEmailContaService] Falha ao auditar conflito de e-mail ({tipo}).',
                        ['tipo' => $erroAuditoria::class],
                    );

                    return $this->falha(
                        'inesperado',
                        'EMAIL_NAO_ATUALIZADO',
                        'Não foi possível confirmar o novo e-mail.',
                    );
                }

                return $this->falha(
                    'regra_negocio',
                    'EMAIL_DUPLICADO',
                    'Este e-mail já está vinculado a outra conta.',
                );
            }

            log_message('error', '[ConfirmacaoEmailContaService] Falha ao confirmar troca de e-mail ({tipo}).', [
                'tipo' => $erro::class,
            ]);

            return $this->falha('inesperado', 'EMAIL_NAO_ATUALIZADO', 'Não foi possível confirmar o novo e-mail.');
        }

        $this->avisarEnderecoAnterior(
            (string) $solicitacao['email_anterior'],
            Time::now()->toDateTimeString(),
        );

        return [
            'sucesso' => true,
            'tipo' => 'sucesso',
            'codigo' => 'EMAIL_ATUALIZADO',
            'mensagem' => 'Novo e-mail confirmado e atualizado como login da conta.',
        ];
    }

    private function cancelarSolicitacoesAnteriores(int $contaId): void
    {
        $anteriores = $this->solicitacoes->where('conta_id', $contaId)->findAll();

        foreach ($anteriores as $anterior) {
            $this->finalizarAuditoria((int) $anterior['auditoria_id'], 'substituida');
        }

        if ($anteriores !== [] && ! $this->solicitacoes->where('conta_id', $contaId)->delete()) {
            throw new \RuntimeException('Falha ao cancelar solicitações anteriores.');
        }
    }

    private function emailEmUso(string $email, int $contaId): bool
    {
        $identidades = config('Auth')->tables['identities'];

        return $this->banco->table($identidades)
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->where('user_id !=', $contaId)
            ->where('LOWER(secret) =', mb_strtolower($email))
            ->countAllResults() > 0;
    }

    private function emailEstaEmUsoAposFalha(string $email, int $contaId): bool
    {
        try {
            return $this->emailEmUso($email, $contaId);
        } catch (Throwable) {
            return false;
        }
    }

    private function finalizarAuditoria(int $auditoriaId, string $resultado): void
    {
        $dados = [
            'resultado' => $resultado,
            'updated_at' => Time::now()->toDateTimeString(),
        ];

        if (! $this->auditoria->update($auditoriaId, $dados)) {
            throw new \RuntimeException('Falha ao atualizar a auditoria da troca de e-mail.');
        }
    }

    private function confirmarTransacao(): void
    {
        if (! $this->banco->transStatus()) {
            throw new \RuntimeException('Falha ao persistir a troca de e-mail.');
        }

        $this->banco->transCommit();
    }

    private function enviarConfirmacao(string $emailDestino, string $nome, string $url): bool
    {
        try {
            $this->email->setMailType('html')
                ->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '')
                ->setTo($emailDestino)
                ->setSubject('Confirme o novo e-mail da sua conta')
                ->setMessage(view('conta/email_confirmacao_troca', [
                    'nome' => $nome,
                    'urlConfirmacao' => $url,
                ], ['debug' => false]));

            return $this->email->send(false);
        } catch (Throwable $erro) {
            log_message('error', '[ConfirmacaoEmailContaService] Falha no envio da confirmação ({tipo}).', [
                'tipo' => $erro::class,
            ]);

            return false;
        } finally {
            $this->email->clear();
        }
    }

    private function avisarEnderecoAnterior(string $emailAnterior, string $dataAlteracao): void
    {
        try {
            $this->email->setMailType('html')
                ->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '')
                ->setTo($emailAnterior)
                ->setSubject('O e-mail de login da sua conta foi atualizado')
                ->setMessage(view('conta/email_aviso_troca', [
                    'dataAlteracao' => $dataAlteracao,
                ], ['debug' => false]));

            if (! $this->email->send(false)) {
                log_message('error', '[ConfirmacaoEmailContaService] Não foi possível avisar o endereço anterior.');
            }
        } catch (Throwable $erro) {
            log_message('error', '[ConfirmacaoEmailContaService] Falha no aviso ao endereço anterior ({tipo}).', [
                'tipo' => $erro::class,
            ]);
        } finally {
            $this->email->clear();
        }
    }

    /** @return array{sucesso: false, tipo: string, codigo: string, mensagem: string} */
    private function falha(string $tipo, string $codigo, string $mensagem): array
    {
        return ['sucesso' => false, 'tipo' => $tipo, 'codigo' => $codigo, 'mensagem' => $mensagem];
    }
}
