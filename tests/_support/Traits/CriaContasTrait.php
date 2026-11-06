<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

/**
 * Cria contas fictícias para os testes de acesso: sem e-mail e sem senha reais (manual 11.9).
 */
trait CriaContasTrait
{
    /**
     * @param list<string> $permissoesDiretas
     */
    private function criarConta(string $username, ?string $grupo = null, array $permissoesDiretas = []): User
    {
        $contas = model(UserModel::class);
        $contas->save(new User(['username' => $username]));

        /** @var User $conta */
        $conta = $contas->findById($contas->getInsertID());

        if ($grupo !== null) {
            $conta->addGroup($grupo);
        }

        if ($permissoesDiretas !== []) {
            $conta->addPermission(...$permissoesDiretas);
        }

        return $conta;
    }

    /**
     * Lê a conta de novo no banco, como uma requisição nova faria. O Shield guarda grupos e
     * permissões em cache na entidade: reaproveitar a instância antiga esconderia o efeito real.
     */
    private function recarregar(User $conta): User
    {
        /** @var User $atual */
        $atual = model(UserModel::class)->findById($conta->id);

        return $atual;
    }
}
