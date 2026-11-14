<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Config\Auth;

/**
 * Consultas e persistência de dados de usuários do sistema.
 */
class UsuarioModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'username',
        'status',
        'status_message',
        'active',
        'last_active',
        'deleted_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected function initialize(): void
    {
        parent::initialize();

        $configuracao = config(Auth::class);
        $this->table  = $configuracao->tables['users'] ?? 'users';
    }

    /**
     * Verifica se já existe um usuário com o nome informado, incluindo contas com soft delete.
     */
    public function nomeJaExiste(string $nome): bool
    {
        $nome = trim($nome);

        if ($nome === '') {
            return false;
        }

        return $this->withDeleted()
            ->where('username', $nome)
            ->countAllResults() > 0;
    }

    /**
     * Verifica se já existe uma identidade de e-mail cadastrada para autenticação.
     */
    public function emailJaExiste(string $email): bool
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return false;
        }

        $identidades = model(UserIdentityModel::class);

        return $identidades->getIdentityBySecret(Session::ID_TYPE_EMAIL_PASSWORD, $email) !== null;
    }
}
