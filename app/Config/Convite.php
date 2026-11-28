<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Convite extends BaseConfig
{
    /** Prazo, em minutos, durante o qual o convite pode ser utilizado. */
    public int $validadeMinutos = 30;
}
