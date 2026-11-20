<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configurações da administração de contas de usuário.
 *
 * Valores podem ser sobrescritos em `.env` por índice
 * (ex.: `contas.dominiosPermitidos.0 = ifro.edu.br` e `contas.dominiosPermitidos.1 = estudante.ifro.edu.br`).
 */
class Contas extends BaseConfig
{
    /**
     * Domínios institucionais aceitos na troca excepcional de e-mail.
     * Comparação exata do domínio completo (após o último `@`), sem
     * curinga de subdomínio.
     *
     * @var list<string>
     */
    public array $dominiosPermitidos = [
        'ifro.edu.br',
        'estudante.ifro.edu.br',
    ];

    /** Prazo, em segundos, para confirmar o novo endereço institucional. */
    public int $prazoConfirmacaoEmailSegundos = 86400;
}
