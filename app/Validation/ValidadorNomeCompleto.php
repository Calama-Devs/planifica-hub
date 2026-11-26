<?php

declare(strict_types=1);

namespace App\Validation;

class ValidadorNomeCompleto
{
    public function validar(string $nome): bool
    {
        return mb_strlen($nome) >= 2
            && mb_strlen($nome) <= 255
            && preg_match('/\A[\p{L}\p{M}]+(?:[ \'\x{2019}-][\p{L}\p{M}]+)*\z/u', $nome) === 1;
    }
}
