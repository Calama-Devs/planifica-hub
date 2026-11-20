<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditoriaEdicaoContaModel;
use App\Models\UsuarioAutenticacaoModel;
use App\Validation\ValidadorNomeCompleto;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\DatabaseException as ShieldDatabaseException;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use Throwable;

class EdicaoContaService
{
    private const PERMISSAO = 'contas.editar';
    private const OPERACAO = 'editar_dados_conta';
    private const JUSTIFICATIVA_MINIMA = 15;
    private const JUSTIFICATIVA_MAXIMA = 500;

    private UserModel $contas;
    private AuditoriaEdicaoContaModel $auditoria;
    private BaseConnection $banco;
    private ConfirmacaoOperacaoService $confirmacao;
    private ConfirmacaoEmailContaService $confirmacaoEmail;

    public function __construct(
        ?UserModel $contas = null,
        ?AuditoriaEdicaoContaModel $auditoria = null,
        ?BaseConnection $banco = null,
        ?ConfirmacaoOperacaoService $confirmacao = null,
        ?ConfirmacaoEmailContaService $confirmacaoEmail = null,
    ) {
        $this->contas = $contas ?? model(UsuarioAutenticacaoModel::class);
        $this->auditoria = $auditoria ?? model(AuditoriaEdicaoContaModel::class);
        $this->banco = $banco ?? db_connect();
        $this->confirmacao = $confirmacao ?? service('confirmacaoOperacaoService');
        $this->confirmacaoEmail = $confirmacaoEmail ?? service('confirmacaoEmailContaService');
    }

    public function consultar(int $contaId): ?array
    {
        $conta = $this->contas->findById($contaId);

        if ($conta === null) {
            return null;
        }

        return [
            'id' => (int) $conta->id,
            'nome' => (string) ($conta->nome_completo ?? $conta->username),
            'email' => (string) $conta->email,
        ];
    }

    /** @return array<string, mixed> */
    public function atualizar(
        int $contaId,
        string $nome,
        string $senha,
        User $operador,
        ?string $email = null,
        ?string $justificativa = null,
    ): array {
        if (! $operador->can(self::PERMISSAO)) {
            return $this->falha('permissao', 'CONTA_SEM_PERMISSAO', 'Você não tem permissão para editar contas.');
        }

        if ((int) $operador->id === $contaId) {
            return $this->falha('regra_negocio', 'CONTA_PROPRIA_NAO_EDITAVEL', 'Atualize seus dados em Meu Perfil.');
        }

        $conta = $this->contas->findById($contaId);

        if ($conta === null) {
            return $this->falha('regra_negocio', 'CONTA_NAO_ENCONTRADA', 'Conta não encontrada.');
        }

        $nome = preg_replace('/\s+/u', ' ', trim($nome)) ?? '';
        $nomeAtual = (string) ($conta->nome_completo ?? $conta->username);
        $emailAtual = (string) $conta->email;
        $emailNovo = $email === null || trim($email) === '' ? $emailAtual : mb_strtolower(trim($email));
        $alterarEmail = mb_strtolower($emailNovo) !== mb_strtolower(trim($emailAtual));

        if (! $this->nomeValido($nome)) {
            return $this->falha(
                'validacao',
                'CONTA_NOME_INVALIDO',
                'Informe um nome completo válido, com até 255 caracteres.',
            );
        }

        if ($alterarEmail) {
            $motivo = trim((string) $justificativa);

            if (
                mb_strlen($motivo) < self::JUSTIFICATIVA_MINIMA
                || mb_strlen($motivo) > self::JUSTIFICATIVA_MAXIMA
            ) {
                return $this->falha(
                    'validacao',
                    'JUSTIFICATIVA_EMAIL_INVALIDA',
                    'A justificativa deve ter entre 15 e 500 caracteres.',
                );
            }

            if (mb_strlen($emailNovo) > 254 || ! filter_var($emailNovo, FILTER_VALIDATE_EMAIL)) {
                return $this->falha('validacao', 'EMAIL_INVALIDO', 'Informe um e-mail válido.');
            }

            if (! $this->dominioPermitido($emailNovo)) {
                $dominios = implode(', ', config('Contas')->dominiosPermitidos);

                return $this->falha(
                    'validacao',
                    'EMAIL_DOMINIO_NAO_PERMITIDO',
                    'O domínio do e-mail não é permitido. Domínios aceitos: ' . $dominios . '.',
                );
            }

            if (
                $this->emailEmUso($emailNovo, $contaId)
                || $this->confirmacaoEmail->emailPendenteEmUso($emailNovo, $contaId)
            ) {
                return $this->falha(
                    'validacao',
                    'EMAIL_DUPLICADO',
                    'Este e-mail já está vinculado a outra conta.',
                );
            }

            $justificativa = $motivo;
        } else {
            $justificativa = null;
        }

        if (
            ! $this->confirmacao->confirmar($operador, $senha, self::OPERACAO, $contaId)
            || ! $this->confirmacao->consumir($operador, self::OPERACAO, $contaId)
        ) {
            return $this->falha('permissao', 'CONFIRMACAO_INVALIDA', 'Confirme a operação com sua senha atual.');
        }

        $campos = [];
        if ($nome !== $nomeAtual) {
            $campos[] = 'nome_completo';
        }
        if ($alterarEmail) {
            $campos[] = 'email';
        }
        if ($campos === []) {
            return $this->falha('validacao', 'CONTA_SEM_ALTERACAO', 'Informe ao menos um dado diferente do atual.');
        }

        $this->banco->transBegin();
        $donaDaTransacao = $this->banco->transDepth === 1;

        try {
            if ($alterarEmail && ! $this->identidadeEmailExiste($contaId)) {
                $this->banco->transRollback();

                if ($donaDaTransacao) {
                    $this->banco->resetTransStatus();
                }

                return $this->falha(
                    'validacao',
                    'IDENTIDADE_EMAIL_AUSENTE',
                    'Esta conta não possui uma identidade de e-mail para atualizar.',
                );
            }

            if ($nome !== $nomeAtual && ! $this->contas->update($contaId, ['nome_completo' => $nome])) {
                throw new \RuntimeException('Falha ao atualizar nome.');
            }

            if ($nome !== $nomeAtual) {
                $auditado = $this->auditoria->insert([
                    'conta_id' => $contaId,
                    'operador_id' => (int) $operador->id,
                    'campos_alterados' => 'nome_completo',
                    'nome_anterior' => $nomeAtual,
                    'nome_novo' => $nome,
                    'resultado' => 'sucesso',
                    'ip' => $this->minimizarIp(service('request')->getIPAddress()),
                    'created_at' => date('Y-m-d H:i:s'),
                ]) !== false;

                if (! $auditado) {
                    throw new \RuntimeException('Falha ao registrar auditoria do nome.');
                }
            }

            if ($alterarEmail) {
                $this->confirmacaoEmail->solicitar(
                    $contaId,
                    (int) $operador->id,
                    $nome,
                    $emailAtual,
                    $emailNovo,
                    (string) $justificativa,
                    $this->minimizarIp(service('request')->getIPAddress()),
                );
            }

            if (! $this->banco->transStatus()) {
                throw new \RuntimeException('Falha ao registrar auditoria.');
            }

            $this->banco->transCommit();
        } catch (Throwable $erro) {
            $emailDuplicado = $alterarEmail
                && (
                    (($erro instanceof DatabaseException || $erro instanceof ShieldDatabaseException)
                        && $this->violacaoEmailDuplicado($erro))
                    || $this->emailEstaEmUsoAposFalha($emailNovo, $contaId)
                );
            $this->banco->transRollback();

            if ($donaDaTransacao) {
                $this->banco->resetTransStatus();
            }

            if ($emailDuplicado) {
                return $this->falha(
                    'validacao',
                    'EMAIL_DUPLICADO',
                    'Este e-mail já está vinculado a outra conta.',
                );
            }

            log_message(
                'error',
                '[EdicaoContaService] Falha ao atualizar conta; tipo da exceção: {tipo}.',
                ['tipo' => get_class($erro)],
            );
            return $this->falha('inesperado', 'CONTA_ATUALIZACAO_FALHOU', 'Não foi possível concluir a atualização.');
        }

        return [
            'sucesso' => true,
            'tipo' => 'sucesso',
            'codigo' => $alterarEmail ? 'CONFIRMACAO_EMAIL_ENVIADA' : 'CONTA_ATUALIZADA',
            'mensagem' => $this->mensagemAtualizacao($nome !== $nomeAtual, $alterarEmail),
        ];
    }

    private function nomeValido(string $nome): bool
    {
        return (new ValidadorNomeCompleto())->validar($nome);
    }

    protected function emailEmUso(string $email, int $contaId): bool
    {
        $identidade = config('Auth')->tables['identities'];

        return $this->banco->table($identidade)
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->where('user_id !=', $contaId)
            ->where('LOWER(secret) =', mb_strtolower($email))
            ->countAllResults() > 0;
    }

    private function violacaoEmailDuplicado(Throwable $erro): bool
    {
        for ($excecao = $erro; $excecao !== null; $excecao = $excecao->getPrevious()) {
            if ((int) $excecao->getCode() === 1062) {
                return true;
            }

            if (
                (int) $excecao->getCode() === 19
                || (
                    str_contains($excecao->getMessage(), 'UNIQUE constraint failed')
                    && str_contains($excecao->getMessage(), 'auth_identities.type')
                    && str_contains($excecao->getMessage(), 'auth_identities.secret')
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function emailEstaEmUsoAposFalha(string $email, int $contaId): bool
    {
        try {
            return $this->emailEmUso($email, $contaId);
        } catch (Throwable) {
            return false;
        }
    }

    private function identidadeEmailExiste(int $contaId): bool
    {
        return model(UserIdentityModel::class)
            ->where('user_id', $contaId)
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->first() !== null;
    }

    private function dominioPermitido(string $email): bool
    {
        $email = mb_strtolower(trim($email));
        $posicao = strrpos($email, '@');

        if ($posicao === false) {
            return false;
        }

        $dominio = substr($email, $posicao + 1);

        return in_array($dominio, array_map('mb_strtolower', config('Contas')->dominiosPermitidos), true);
    }

    private function mensagemAtualizacao(bool $nomeAlterado, bool $emailPendente): string
    {
        if ($emailPendente && $nomeAlterado) {
            return 'Nome atualizado. Enviamos um link para confirmar o novo e-mail; '
                . 'o login atual permanece ativo até a confirmação.';
        }

        if ($emailPendente) {
            return 'Enviamos um link para confirmar o novo e-mail; '
                . 'o login atual permanece ativo até a confirmação.';
        }

        return 'Dados atualizados com sucesso.';
    }

    /**
     * Minimiza o endereço IP para auditoria (LGPD / minimização de dados).
     *
     * IPv4: último octeto zerado (ex.: 192.168.1.0).
     * IPv6: mantém os 3 primeiros grupos (ex.: 2001:db8:85a3).
     * Valor inválido ou vazio retorna null.
     *
     * Registros anteriores a esta regra permanecem como estão.
     */
    private function minimizarIp(string $ip): ?string
    {
        $ip = trim($ip);

        if ($ip === '' || $ip === '0.0.0.0') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $octetos = explode('.', $ip);
            $octetos[3] = '0';

            return implode('.', $octetos);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = inet_pton($ip);

            if ($bin === false) {
                return null;
            }

            $hex = bin2hex($bin);
            $grupos = str_split($hex, 4);
            $tresPrimeiros = array_map(
                static fn (string $g): string => ltrim($g, '0') ?: '0',
                array_slice($grupos, 0, 3),
            );

            return implode(':', $tresPrimeiros);
        }

        return null;
    }

    /** @return array{sucesso: false, tipo: string, codigo: string, mensagem: string} */
    private function falha(string $tipo, string $codigo, string $mensagem): array
    {
        return ['sucesso' => false, 'tipo' => $tipo, 'codigo' => $codigo, 'mensagem' => $mensagem];
    }
}
