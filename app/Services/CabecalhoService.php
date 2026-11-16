<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Router\Exceptions\RouterException;

class CabecalhoService 
{
    public function obter(): ?array 
    {
        $usuario = auth()->user();

        if ($usuario === null) {
            return null;
        }

        $nome = trim((string) ($usuario->username ?? ''));
        $email = trim((string) ($usuario->email ?? ''));

        return [
            'rotulo'    => $nome !== '' ? $nome : ($email !== '' ? $email : 'Minha conta'),
            'email'     => $email,
            'urlPerfil' => $this->urlSeExistir('perfil'),
        ];
    }

    private function urlSeExistir(string $nomeRota): ?string
    {
        helper('url');

        try {
            return url_to($nomeRota);
        } catch (RouterException) {
            return null;
        }
    }
}
