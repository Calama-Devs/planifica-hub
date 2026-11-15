<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventoAuditoriaModel;
use RuntimeException;

class AuditoriaService
{
    private EventoAuditoriaModel $eventos;

    public function __construct(?EventoAuditoriaModel $eventos = null)
    {
        $this->eventos = $eventos ?? new EventoAuditoriaModel();
    }

    public function registrar(array $evento): void
    {
        $evento['usuario_id'] = auth()->id();

        if ($this->eventos->insert($evento) === false) {
            throw new RuntimeException('O evento de auditoria não foi gravado.');
        }
    }

    public function registrarVarios(array $eventos): void
    {
        if ($eventos === []) {
            return;
        }

        $usuarioId = auth()->id();

        $eventos   = array_map(
            static function (array $evento) use ($usuarioId): array {
                $evento['usuario_id'] = $usuarioId;
                return $evento;
            },
            $eventos,
        );

        if ($this->eventos->insertBatch($eventos) === false) {
            throw new RuntimeException('Os eventos de auditoria não foram gravados.');
        }
    }
}
