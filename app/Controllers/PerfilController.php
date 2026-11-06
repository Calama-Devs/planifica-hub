<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Validation\ValidationRules;

class PerfilController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $usuario = auth()->user();

        if (! $usuario instanceof User) {
            return redirect()->to(url_to('login'));
        }

        return view('perfil/index', [
            'nomeAtual'          => $usuario->username ?? '',
            'comprimentoMaximo'  => $this->obterComprimentoMaximo(),
        ]);
    }

    public function atualizar(): RedirectResponse
    {
        $usuario = auth()->user();

        if (! $usuario instanceof User) {
            return redirect()->to(url_to('login'));
        }

        $nome = $this->request->getPost('nome');

        if (! is_string($nome)) {
            return redirect()->back()->with('errors', [
                'nome' => 'Informe um nome válido.',
            ]);
        }

        if (
            ! $this->validate($this->obterRegrasNome((string) $usuario->id), [
            'nome' => [
                'required'    => 'Informe seu nome.',
                'min_length'  => 'O nome deve ter pelo menos {param} caracteres.',
                'max_length'  => 'O nome deve ter no máximo {param} caracteres.',
                'regex_match' => 'O nome contém caracteres não permitidos.',
                'is_unique'   => 'Este nome já está em uso.',
            ],
            ])
        ) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $dadosValidados = $this->validator->getValidated();
        $usuarios = model(UserModel::class);

        if (! $usuarios->update($usuario->id, ['username' => $dadosValidados['nome']])) {
            log_message(
                'error',
                'Falha ao atualizar o nome exibido da conta autenticada.',
            );

            return redirect()->back()->withInput()->with(
                'error',
                'Não foi possível atualizar seu nome. Tente novamente.',
            );
        }

        return redirect()->to(url_to('perfil'))->with('success', 'Nome atualizado com sucesso.');
    }

    private function obterRegrasNome(string $usuarioId): array
    {
        $configuracaoAuth = config('Auth');
        $regrasNome       = (new ValidationRules())->getRegistrationRules()['username']['rules'] ?? [];

        if (is_string($regrasNome)) {
            $regrasNome = explode('|', $regrasNome);
        }

        $regrasNome = array_values(array_filter(
            $regrasNome,
            static fn (string $regra): bool => ! str_starts_with($regra, 'is_unique['),
        ));

        $regrasNome[] = sprintf(
            'is_unique[%s.username,id,%s]',
            $configuracaoAuth->tables['users'],
            $usuarioId,
        );

        return [
            'nome' => [
                'label' => 'nome',
                'rules' => array_merge(['required', 'string'], $regrasNome),
            ],
        ];
    }

    private function obterComprimentoMaximo(): ?int
    {
        $regrasNome = (new ValidationRules())->getRegistrationRules()['username']['rules'] ?? [];
        $regrasNome = is_array($regrasNome) ? $regrasNome : explode('|', $regrasNome);

        foreach ($regrasNome as $regra) {
            if (preg_match('/^max_length\[(\d+)\]$/', $regra, $correspondencia) === 1) {
                return (int) $correspondencia[1];
            }
        }

        return null;
    }
}
