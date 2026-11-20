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

    public function emailInstitucionalPermitido(string $email): bool
    {
        $email = mb_strtolower(trim($email));
        $posicaoArroba = strrpos($email, '@');

        if ($posicaoArroba === false) {
            return false;
        }

        $dominio = substr($email, $posicaoArroba + 1);
        $dominiosPermitidos = array_map(
            static fn (string $dominioPermitido): string => mb_strtolower(trim($dominioPermitido)),
            $this->dominiosPermitidos,
        );

        return in_array($dominio, $dominiosPermitidos, true);
    }
}
