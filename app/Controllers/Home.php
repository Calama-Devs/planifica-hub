<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Home extends BaseController
{
    public function index(): string
    {
        return view('home/index');
    }

    public function sobre(): string
    {
        return view('home/sobre');
    }

    public function testarValidacao(): RedirectResponse
    {
        $regras = [
            'nome'  => 'required|min_length[3]|max_length[100]',
            'email' => 'required|valid_email|max_length[254]',
        ];

        $mensagens = [
            'nome' => [
                'required'   => 'O campo Nome Completo é obrigatório.',
                'min_length' => 'O campo Nome Completo deve ter no mínimo 3 caracteres.',
                'max_length' => 'O campo Nome Completo não pode exceder 100 caracteres.',
            ],
            'email' => [
                'required'    => 'O campo E-mail Institucional é obrigatório.',
                'valid_email' => 'Informe um endereço de e-mail institucional válido.',
                'max_length'  => 'O campo E-mail Institucional não pode exceder 254 caracteres.',
            ],
        ];

        if (! $this->validate($regras, $mensagens)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        return redirect()->to('sobre')->with('success', 'Formulário validado e enviado com sucesso!');
    }
}
