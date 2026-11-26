<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Throttle\Throttler;
use Config\ConfirmacaoAdministrativa;
use Throwable;

/**
 * Confirma a senha do operador autenticado antes de uma operação administrativa sensível.
 *
 * A confirmação vale só para a requisição em curso: o chamador a usa imediatamente antes de executar a operação
 * e não guarda o resultado. O serviço não lembra nenhuma confirmação anterior; o único estado mantido é o
 * contador de senhas incorretas do operador, guardado no cache sem a senha.
 *
 * Ordem das verificações, todas no servidor: ação cadastrada, conta-alvo válida, sessão autenticada, permissão
 * efetiva da ação (grupos e permissões diretas do Shield), senha informada, limite de tentativas e, por fim, a
 * senha do próprio operador. Qualquer recusa impede a operação.
 *
 * @phpstan-type Recusa array{sucesso: false, tipo: string, codigo: string, mensagem: string, espera_segundos?: int}
 */
class ConfirmacaoAdministrativaService
{
    private const TAMANHO_MAXIMO_SENHA = 4096;

    private ConfirmacaoAdministrativa $config;
    private Throttler $limitador;

    public function __construct(?ConfirmacaoAdministrativa $config = null, ?Throttler $limitador = null)
    {
        $this->config = $config ?? config(ConfirmacaoAdministrativa::class);
        $this->limitador = $limitador ?? service('throttler');
    }

    /**
     * Confirma a operação `$acao` sobre a conta `$alvoId` com a senha do operador da sessão atual.
     *
     * A ação e a conta são definidas pelo código do servidor que vai executar a operação, nunca lidas do
     * navegador. Devolve o operador confirmado, que deve ser usado pela operação (auditoria, regras de
     * hierarquia), ou a recusa estruturada: nesse caso nada pode ser alterado.
     *
     * @return User|Recusa
     */
    public function confirmar(string $acao, int $alvoId, #[\SensitiveParameter] string $senha): User|array
    {
        $operador = $this->autorizarOperador($acao, $alvoId);

        if (! $operador instanceof User) {
            return $operador;
        }

        if ($senha === '') {
            return $this->recusa('permissao', 'CONFIRMACAO_INVALIDA', 'Informe a sua senha para confirmar a operação.');
        }

        if (strlen($senha) > self::TAMANHO_MAXIMO_SENHA) {
            return $this->senhaIncorreta();
        }

        return $this->conferirSenha($operador, $acao, $alvoId, $senha);
    }

    /**
     * Confere, antes de qualquer uso da senha, a ação cadastrada, a conta afetada, a sessão e a permissão efetiva
     * do operador (grupos e permissões diretas do Shield).
     *
     * @return User|Recusa
     */
    private function autorizarOperador(string $acao, int $alvoId): User|array
    {
        $permissao = $this->config->permissaoDaAcao($acao);

        if ($permissao === null) {
            return $this->recusa(
                'regra_negocio',
                'CONFIRMACAO_OPERACAO_INDISPONIVEL',
                'Esta operação não está disponível.',
            );
        }

        if ($alvoId < 1) {
            return $this->recusa(
                'validacao',
                'CONFIRMACAO_CONTEXTO_INVALIDO',
                'Não foi possível identificar a conta da operação.',
            );
        }

        $operador = $this->operadorAutenticado();

        if ($operador === null) {
            return $this->recusa('permissao', 'AUTENTICACAO_NECESSARIA', 'Entre novamente para continuar.');
        }

        if (! $operador->can($permissao)) {
            return $this->recusa(
                'permissao',
                'CONFIRMACAO_SEM_PERMISSAO',
                'Você não tem permissão para realizar esta operação.',
            );
        }

        return $operador;
    }

    /**
     * O operador vem exclusivamente da sessão do Shield: nenhum dado da requisição o substitui.
     */
    private function operadorAutenticado(): ?User
    {
        if (! auth()->loggedIn()) {
            return null;
        }

        $operador = auth()->user();

        return $operador instanceof User ? $operador : null;
    }

    /**
     * A tentativa é contada antes da conferência, que é a etapa lenta: reduz a margem para rajadas de requisições
     * simultâneas. O Throttler lê e grava o cache sem atomicidade, então é uma barreira contra tentativas repetidas,
     * não uma garantia exata contra rajadas.
     *
     * @return User|Recusa
     */
    private function conferirSenha(
        User $operador,
        string $acao,
        int $alvoId,
        #[\SensitiveParameter] string $senha,
    ): User|array {
        $contexto = ['operador' => (int) $operador->id, 'acao' => $acao, 'alvo' => $alvoId];

        try {
            $espera = $this->esperaPorExcessoDeTentativas($operador);

            if ($espera !== null) {
                $this->registrarRecusa('excesso de tentativas', $contexto);

                return $this->recusaDeLimite($espera);
            }

            if (! $this->senhaConfere($operador, $senha)) {
                $this->registrarRecusa('senha incorreta', $contexto);

                return $this->senhaIncorreta();
            }

            $this->limitador->remove($this->chaveDoLimite($operador));
        } catch (Throwable $erro) {
            log_message('error', 'Falha inesperada na confirmação administrativa; exceção {classe}.', [
                'classe' => $erro::class,
            ]);

            return $this->recusa(
                'inesperado',
                'CONFIRMACAO_ERRO',
                'Não foi possível confirmar a operação. Tente novamente.',
            );
        }

        return $operador;
    }

    /** @param array{operador: int, acao: string, alvo: int} $contexto */
    private function registrarRecusa(string $motivo, array $contexto): void
    {
        log_message(
            'warning',
            'Confirmação administrativa recusada: ' . $motivo . '. Operador {operador}, ação {acao}, conta {alvo}.',
            $contexto,
        );
    }

    /**
     * Consome uma tentativa do operador e devolve, em segundos, quanto falta para a próxima ser liberada
     * quando o limite foi excedido. O contador é do operador: não muda com a ação, a conta nem a rota.
     */
    private function esperaPorExcessoDeTentativas(User $operador): ?int
    {
        $liberada = $this->limitador->check(
            $this->chaveDoLimite($operador),
            max(1, $this->config->maxTentativas),
            max(1, $this->config->janelaSegundos),
        );

        return $liberada ? null : max(1, $this->limitador->getTokenTime());
    }

    private function chaveDoLimite(User $operador): string
    {
        return 'confirmacao_administrativa_' . (int) $operador->id;
    }

    /**
     * Compara com o hash da própria identidade de e-mail e senha do operador, pelo serviço de senhas do Shield.
     */
    private function senhaConfere(User $operador, #[\SensitiveParameter] string $senha): bool
    {
        $hash = $operador->getEmailIdentity()?->secret2;

        if (! is_string($hash) || $hash === '') {
            return false;
        }

        /** @var Passwords $senhas */
        $senhas = service('passwords');

        return $senhas->verify($senha, $hash);
    }

    /** @return Recusa */
    private function senhaIncorreta(): array
    {
        return $this->recusa(
            'permissao',
            'CONFIRMACAO_INVALIDA',
            'Senha incorreta. Confirme a operação com a senha da sua conta.',
        );
    }

    /** @return Recusa */
    private function recusaDeLimite(int $esperaSegundos): array
    {
        return $this->recusa(
            'permissao',
            'CONFIRMACAO_LIMITE_EXCEDIDO',
            'Muitas tentativas incorretas. Aguarde ' . $this->descreverEspera($esperaSegundos)
                . ' para tentar novamente.',
        ) + ['espera_segundos' => $esperaSegundos];
    }

    private function descreverEspera(int $segundos): string
    {
        if ($segundos < 60) {
            return $segundos . ($segundos === 1 ? ' segundo' : ' segundos');
        }

        $minutos = (int) ceil($segundos / 60);

        return $minutos . ($minutos === 1 ? ' minuto' : ' minutos');
    }

    /** @return Recusa */
    private function recusa(string $tipo, string $codigo, string $mensagem): array
    {
        return ['sucesso' => false, 'tipo' => $tipo, 'codigo' => $codigo, 'mensagem' => $mensagem];
    }
}
