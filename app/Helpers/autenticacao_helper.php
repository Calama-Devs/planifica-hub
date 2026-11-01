<?php

declare(strict_types=1);

if (! function_exists('dados_campo_login')) {
    /**
     * Retorna as configurações do campo de identificador de login (nome, tipo, label e placeholder)
     * com base nas credenciais aceitas em Auth.validFields.
     *
     * @return array{nome: string, tipo: string, label: string, placeholder: string}
     */
    function dados_campo_login(): array
    {
        $validFields = setting('Auth.validFields') ?? ['email'];
        $isUsernameOnly = $validFields === ['username'];
        $isMultiple = count($validFields) > 1;

        if ($isMultiple) {
            return [
                'nome'        => 'login',
                'tipo'        => 'text',
                'label'       => 'E-mail ou Usuário',
                'placeholder' => 'nome@exemplo.com ou usuário',
            ];
        }

        if ($isUsernameOnly) {
            return [
                'nome'        => 'username',
                'tipo'        => 'text',
                'label'       => 'Usuário',
                'placeholder' => 'Digite seu usuário',
            ];
        }

        return [
            'nome'        => 'email',
            'tipo'        => 'email',
            'label'       => 'E-mail',
            'placeholder' => 'nome@exemplo.com',
        ];
    }
}
