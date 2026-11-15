<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use Throwable;

/** Trilha de inclusão das operações administrativas de usuários. */
class AuditoriaUsuarioModel extends Model
{
    protected $table            = 'auditoria_usuarios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'usuario_id',
        'operador_id',
        'operacao',
        'resultado',
        'grupo',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    /**
     * Grava um evento de auditoria. Uma falha de gravação não interrompe quem chamou: o método devolve false
     * e registra no log só o tipo da exceção, sem SQL nem dados do evento.
     */
    public function registrar(
        ?int $usuarioId,
        int $operadorId,
        string $operacao,
        string $resultado,
        ?string $grupo = null,
    ): bool {
        try {
            return $this->insert([
                'usuario_id'  => $usuarioId,
                'operador_id' => $operadorId,
                'operacao'    => $operacao,
                'resultado'   => $resultado,
                'grupo'       => $grupo,
            ]) !== false;
        } catch (Throwable $excecao) {
            log_message('error', 'Falha ao gravar a auditoria de usuários: {tipo}', [
                'tipo' => $excecao::class,
            ]);

            return false;
        }
    }
}
