<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Parâmetros da confirmação de senha exigida nas operações administrativas sensíveis.
 *
 * Guia de integração: docs/autorizacao/confirmacao-administrativa.md.
 */
class ConfirmacaoAdministrativa extends BaseConfig
{
    /** Senhas incorretas que cada operador pode informar antes do bloqueio temporário. */
    public int $maxTentativas = 5;

    /** Tempo, em segundos, para o limite de tentativas se recompor por completo. */
    public int $janelaSegundos = 900;

    /**
     * Operações que exigem a confirmação de senha e a permissão que cada uma exige do operador.
     *
     * Só entram aqui operações que já existem: cada nova operação sensível acrescenta a sua linha, junto com a
     * permissão correspondente em `Config\AuthGroups`. O identificador é escolhido pelo código do servidor; o
     * navegador nunca informa a ação.
     *
     * @var array<string, string>
     */
    public array $acoes = [
        'editar_conta'    => 'contas.editar',
        'desativar_conta' => 'contas.desativar',
        'reativar_conta'  => 'contas.reativar',
    ];

    /**
     * Status HTTP de cada recusa da confirmação, para os endpoints JSON que a usam.
     *
     * @var array<string, int>
     */
    public array $statusHttp = [
        'AUTENTICACAO_NECESSARIA'           => 401,
        'CONFIRMACAO_SEM_PERMISSAO'         => 403,
        'CONFIRMACAO_INVALIDA'              => 422,
        'CONFIRMACAO_CONTEXTO_INVALIDO'     => 422,
        'CONFIRMACAO_LIMITE_EXCEDIDO'       => 429,
        'CONFIRMACAO_OPERACAO_INDISPONIVEL' => 501,
        'CONFIRMACAO_ERRO'                  => 500,
    ];

    /** Permissão exigida pela ação, ou null quando a ação não está cadastrada. */
    public function permissaoDaAcao(string $acao): ?string
    {
        return $this->acoes[$acao] ?? null;
    }

    /** Status HTTP da recusa, ou null quando o código não pertence à confirmação. */
    public function statusDaRecusa(string $codigo): ?int
    {
        return $this->statusHttp[$codigo] ?? null;
    }
}
