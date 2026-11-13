<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventoAuditoriaModel;
use RuntimeException;
use Throwable;

class AuditoriaService
{
    private EventoAuditoriaModel $eventos;

    public function __construct(?EventoAuditoriaModel $eventos = null)
    {
        $this->eventos = $eventos ?? new EventoAuditoriaModel(db_connect(null, false));
    }

    public function registrar(array $evento): void
    {
        try {
            $evento['created_by'] = auth()->id();

            if (!$this->eventos->registrar($evento)) {
                throw new RuntimeException('O evento de auditoria não foi gravado.');
            }
        } catch (Throwable $erro) {
            log_message(
                'error',
                'Falha ao registrar auditoria unitária ({erro}).',
                ['erro' => $erro::class],
            );
        }
    }

    public function registrarVarios(array $eventos): void
    {
        try {
            $createdBy = auth()->id();
            $eventos   = array_map(
                static function (array $evento) use ($createdBy): array {
                    $evento['created_by'] = $createdBy;
                    return $evento;
                },
                $eventos,
            );

            if (!$this->eventos->registrarVarios($eventos)) {
                throw new RuntimeException('Os eventos de auditoria não foram gravados.');
            }
        } catch (Throwable $erro) {
            log_message(
                'error',
                'Falha ao registrar auditoria em lote ({erro}).',
                ['erro' => $erro::class],
            );
        }
    }
}
