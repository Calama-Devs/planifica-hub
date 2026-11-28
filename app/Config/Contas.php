<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/** Configurações da administração de contas de usuário. */
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

    /** Prazo, em segundos, para confirmar o novo endereço. */
    public int $prazoConfirmacaoEmailSegundos = 86400;
}
