<?php

declare(strict_types=1);

namespace App\Services;

class FinalizarCadastroService
{
    /**
     * Mapeamento de rótulos amigáveis para os grupos de acesso do sistema.
     *
     * @var array<string, string>
     */
    private const ROTULOS_GRUPOS = [
        'user'       => 'Usuário (user)',
        'admin'      => 'Administrador (admin)',
        'superadmin' => 'Superadministrador (superadmin)',
        'developer'  => 'Desenvolvedor (developer)',
        'beta'       => 'Testador Beta (beta)',
    ];

    /**
     * Valida o token de convite e retorna o estado correspondente.
     *
     * Estados possíveis: valido, expirado, invalido, cancelado, utilizado, erro_temporario.
     *
     * @return array{
     *     sucesso: bool,
     *     tipo: string,
     *     codigo: string,
     *     estado: string,
     *     mensagem: string,
     *     dados: array{email: string, grupos: list<array{codigo: string, rotulo: string}>, token: string}|null
     * }
     */
    public function validarConvite(?string $token): array
    {
        if ($token === null || trim($token) === '') {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'CONVITE_INVALIDO',
                'estado'   => 'invalido',
                'mensagem' => 'O link de convite informado não é válido ou está incompleto.',
                'dados'    => null,
            ];
        }

        $tokenLimpo = trim($token);

        // Formato básico do token: alfanumérico seguro com hífen ou underscore
        if (! preg_match('/^[a-zA-Z0-9\-_]{8,128}$/', $tokenLimpo)) {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'CONVITE_INVALIDO',
                'estado'   => 'invalido',
                'mensagem' => 'O formato do link de convite é inválido.',
                'dados'    => null,
            ];
        }

        // Sem infraestrutura de persistência de convites conectada,
        // o token é considerado não localizado no servidor.
        return [
            'sucesso'  => false,
            'tipo'     => 'regra_negocio',
            'codigo'   => 'CONVITE_NAO_ENCONTRADO',
            'estado'   => 'invalido',
            'mensagem' => 'Este link de convite não está disponível para utilização.',
            'dados'    => null,
        ];
    }

    /**
     * Processa a conclusão do cadastro com os dados validados.
     *
     * @param array{
     *     token: string,
     *     nome_completo: string,
     *     password: string,
     *     password_confirm: string
     * } $dados
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}
     */
    public function concluirCadastro(array $dados): array
    {
        // Sem infraestrutura de banco de dados/Shield conectada,
        // retorna serviço indisponível sem gerar sucesso fictício.
        return [
            'sucesso'  => false,
            'tipo'     => 'regra_negocio',
            'codigo'   => 'SERVICO_INDISPONIVEL',
            'mensagem' => 'O serviço de criação de conta por convite ainda não está disponível.',
        ];
    }

    /**
     * Formata uma lista de identificadores de grupos para exibição amigável.
     *
     * @param list<string> $grupos
     * @return list<array{codigo: string, rotulo: string}>
     */
    public function formatarGrupos(array $grupos): array
    {
        if ($grupos === []) {
            $grupos = ['user'];
        }

        $formatados = [];

        foreach ($grupos as $grupo) {
            $codigo = strtolower(trim($grupo));

            if ($codigo === '') {
                continue;
            }

            $rotulo = self::ROTULOS_GRUPOS[$codigo] ?? ucfirst($codigo);

            $formatados[] = [
                'codigo' => $codigo,
                'rotulo' => $rotulo,
            ];
        }

        return $formatados !== [] ? $formatados : [['codigo' => 'user', 'rotulo' => 'Usuário (user)']];
    }
}
