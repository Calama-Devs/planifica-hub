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
    /** @var list<string> Grupos do menor para o maior nível de acesso, configuráveis por índice no .env. */
    public array $hierarquiaGrupos = ['user', 'beta', 'developer', 'admin', 'superadmin'];

    /**
     * @param list<string>|null $gruposOperador
     * @param list<string>|null $gruposAlvo
     */
    public function operadorSuperaAlvo(?array $gruposOperador, ?array $gruposAlvo): bool
    {
        return $this->maiorNivel($gruposOperador ?? [], -1)
            > $this->maiorNivel($gruposAlvo ?? [], count($this->hierarquiaGrupos));
    }

    /** @param list<string> $grupos */
    private function maiorNivel(array $grupos, int $nivelDesconhecido): int
    {
        $maior = -1;
        foreach ($grupos as $grupo) {
            $nivel = array_search($grupo, $this->hierarquiaGrupos, true);
            $maior = max($maior, $nivel === false ? $nivelDesconhecido : $nivel);
        }

        return $maior;
    }

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
