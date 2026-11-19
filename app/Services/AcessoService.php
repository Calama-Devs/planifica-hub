<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;

/**
 * Consulta paginada de contas e catálogo de grupos para a tela de Gerenciamento de Usuários.
 */
class AcessoService
{
    /**
     * Lista contas com paginação, filtro de situação e busca no servidor (compatível com DataTables server-side).
     *
     * @return array{
     *     contas: list<array{
     *         id: int,
     *         rotulo: string,
     *         username: string,
     *         email: string,
     *         grupos: list<string>,
     *         raw_grupos: list<string>,
     *         ativo: bool
     *     }>,
     *     total: int,
     *     filtrados: int
     * }
     */
    public function listarContas(
        int $inicio = 0,
        int $limite = 10,
        string $pesquisa = '',
        string $situacao = 'ativos',
    ): array {
        $inicio   = max(0, $inicio);
        $limite   = max(1, min($limite, 100));
        $pesquisa = trim($pesquisa);

        $modeloTotal = $this->criarConsultaPorSituacao($situacao);
        $total       = $modeloTotal->countAllResults();

        $modeloFiltrado = $this->criarConsultaPorSituacao($situacao);
        $this->filtrarContasPorPesquisa($modeloFiltrado, $pesquisa);

        $filtrados = $pesquisa === ''
            ? $total
            : $modeloFiltrado->countAllResults(false);

        $usuarios = $modeloFiltrado
            ->withIdentities()
            ->withGroups()
            ->orderBy('username', 'ASC')
            ->findAll($limite, $inicio);

        $contas = [];

        foreach ($usuarios as $usuario) {
            if ($usuario instanceof User) {
                $contas[] = $this->resumirConta($usuario);
            }
        }

        return [
            'contas'    => $contas,
            'total'     => $total,
            'filtrados' => $filtrados,
        ];
    }

    /**
     * Retorna o catálogo de grupos disponíveis e suas descrições.
     *
     * @return array<string, array{title: string, description: string}>
     */
    public function gruposDisponiveis(): array
    {
        $definidos = setting('AuthGroups.groups');

        return is_array($definidos) ? $definidos : [];
    }

    /**
     * Filtra pela coluna canônica `users.active` do CodeIgniter Shield (1 = ativo, 0 = inativo),
     * adotada no PlanIFica Hub (ex.: RedefinirSenhaController e RedefinirSenhaService).
     */
    private function criarConsultaPorSituacao(string $situacao): UserModel
    {
        // Instância própria: evita acumular filtros e withX() do Shield entre consultas.
        $modelo = model(UserModel::class, false);

        if ($situacao === 'inativos') {
            $modelo->where('active', 0);
        } elseif ($situacao === 'ativos') {
            $modelo->where('active', 1);
        }

        return $modelo;
    }

    private function filtrarContasPorPesquisa(UserModel $modelo, string $pesquisa): void
    {
        if ($pesquisa === '') {
            return;
        }

        $escape   = $modelo->builder()->db()->likeEscapeChar;
        $pesquisa = str_replace([$escape, '%', '_'], [$escape . $escape, $escape . '%', $escape . '_'], $pesquisa);

        $identidades = model(UserIdentityModel::class, false)->builder()
            ->select('user_id')
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->like('secret', $pesquisa, 'both', null, true);

        $modelo->groupStart()
            ->like('username', $pesquisa, 'both', null, true)
            ->orWhereIn('id', $identidades)
            ->groupEnd();
    }

    /**
     * @param list<string> $grupos
     *
     * @return list<string>
     */
    private function titulosDosGrupos(array $grupos): array
    {
        $definidos = $this->gruposDisponiveis();

        return array_values(array_map(
            static fn (string $grupo): string => (string) ($definidos[$grupo]['title'] ?? $grupo),
            $grupos,
        ));
    }

    /**
     * @return array{
     *     id: int,
     *     rotulo: string,
     *     username: string,
     *     email: string,
     *     grupos: list<string>,
     *     raw_grupos: list<string>,
     *     ativo: bool
     * }
     */
    private function resumirConta(User $conta): array
    {
        $rawGrupos = array_values($conta->getGroups() ?? []);

        return [
            'id'         => (int) $conta->id,
            'rotulo'     => $this->rotular($conta),
            'username'   => (string) $conta->username,
            'email'      => (string) $conta->email,
            'grupos'     => $this->titulosDosGrupos($rawGrupos),
            'raw_grupos' => $rawGrupos,
            'ativo'      => (bool) $conta->active,
        ];
    }

    private function rotular(User $conta): string
    {
        $nome = (string) $conta->username;

        if ($nome !== '') {
            return $nome;
        }

        $email = (string) $conta->email;

        return $email !== '' ? $email : 'Conta ' . $conta->id;
    }
}
