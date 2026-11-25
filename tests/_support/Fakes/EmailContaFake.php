<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use CodeIgniter\Email\Email;

final class EmailContaFake extends Email
{
    /** @var list<string> */
    public array $corpos = [];
    public bool $enviar = true;
    public bool $lancarExcecao = false;

    public function setMessage($body)
    {
        $this->corpos[] = (string) $body;

        return parent::setMessage($body);
    }

    public function send($autoClear = true)
    {
        if ($this->lancarExcecao) {
            throw new \RuntimeException('Falha simulada no envio de confirmação.');
        }

        return $this->enviar;
    }
}
