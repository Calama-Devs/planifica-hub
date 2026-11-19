<?php

declare(strict_types=1);

namespace App\Services;

class FinalizarCadastroService
{
    public const ESTADO_VALIDO                 = 'valido';
    public const ESTADO_EXPIRADO                = 'expirado';
    public const ESTADO_INVALIDO                = 'invalido';
    public const ESTADO_CANCELADO               = 'cancelado';
    public const ESTADO_INVALIDO_OU_CANCELADO   = 'invalido_ou_cancelado';
    public const ESTADO_UTILIZADO               = 'utilizado';
    public const ESTADO_ERRO_TEMPORARIO         = 'erro_temporario';

    /**
     * Resolvedor de convites plugável para adaptadores ou fontes integradas.
     *
     * @var (callable(string): (array<string, mixed>|null))|null
     */
    private $resolvedorConvite = null;

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
     * Configura um resolvedor customizado ou adaptador de convites plugável.
     *
     * @param (callable(string): (array<string, mixed>|null))|null $resolvedor
     */
    public function definirResolvedorConvite(?callable $resolvedor): void
    {
        $this->resolvedorConvite = $resolvedor;
    }

    /**
     * Retorna a lista de estados de convite suportados pelo contrato do serviço.
     *
     * @return list<string>
     */
    public function obterEstadosSuportados(): array
    {
        return [
            self::ESTADO_VALIDO,
            self::ESTADO_EXPIRADO,
            self::ESTADO_INVALIDO,
            self::ESTADO_CANCELADO,
            self::ESTADO_INVALIDO_OU_CANCELADO,
            self::ESTADO_UTILIZADO,
            self::ESTADO_ERRO_TEMPORARIO,
        ];
    }

    /**
     * Valida o token de convite e retorna o estado correspondente.
     *
     * Estados possíveis: valido, expirado, invalido, cancelado, invalido_ou_cancelado, utilizado, erro_temporario.
     *
     * @return array{
     *     sucesso: bool,
     *     tipo: string,
     *     codigo: string,
     *     estado: 'valido'|'expirado'|'invalido'|'cancelado'|'invalido_ou_cancelado'|'utilizado'|'erro_temporario',
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
                'estado'   => self::ESTADO_INVALIDO,
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
                'estado'   => self::ESTADO_INVALIDO,
                'mensagem' => 'O formato do link de convite é inválido.',
                'dados'    => null,
            ];
        }

        // 1. Consulta adaptador/resolvedor plugável se registrado
        if ($this->resolvedorConvite !== null) {
            $resultadoResolvedor = ($this->resolvedorConvite)($tokenLimpo);
            if ($resultadoResolvedor !== null) {
                return $resultadoResolvedor;
            }
        }

        // 2. Em ambiente de desenvolvimento/teste, reconhece tokens de demonstração locais
        if (ENVIRONMENT !== 'production') {
            $resultadoDemonstracao = $this->obterConviteDemonstracao($tokenLimpo);
            if ($resultadoDemonstracao !== null) {
                return $resultadoDemonstracao;
            }
        }

        // Sem infraestrutura de persistência de convites conectada,
        // o token é considerado não localizado no servidor.
        return [
            'sucesso'  => false,
            'tipo'     => 'regra_negocio',
            'codigo'   => 'CONVITE_NAO_ENCONTRADO',
            'estado'   => self::ESTADO_INVALIDO,
            'mensagem' => 'Este link de convite não está disponível para utilização.',
            'dados'    => null,
        ];
    }

    /**
     * Retorna dados simulados de demonstração para desenvolvimento e testes locais.
     *
     * @return array{
     *     sucesso: bool,
     *     tipo: string,
     *     codigo: string,
     *     estado: string,
     *     mensagem: string,
     *     dados: array{email: string, grupos: list<array{codigo: string, rotulo: string}>, token: string}|null
     * }|null
     */
    private function obterConviteDemonstracao(string $token): ?array
    {
        return match ($token) {
            'dev-token-valido', 'dev-convite-valido' => [
                'sucesso'  => true,
                'tipo'     => 'sucesso',
                'codigo'   => 'CONVITE_VALIDO',
                'estado'   => self::ESTADO_VALIDO,
                'mensagem' => 'Convite válido.',
                'dados'    => [
                    'token'  => $token,
                    'email'  => 'convidado.calama@ifro.edu.br',
                    'grupos' => $this->formatarGrupos(['user']),
                ],
            ],
            'dev-token-multiplos' => [
                'sucesso'  => true,
                'tipo'     => 'sucesso',
                'codigo'   => 'CONVITE_VALIDO',
                'estado'   => self::ESTADO_VALIDO,
                'mensagem' => 'Convite válido.',
                'dados'    => [
                    'token'  => $token,
                    'email'  => 'coordenador.calama@ifro.edu.br',
                    'grupos' => $this->formatarGrupos(['user', 'admin', 'beta']),
                ],
            ],
            'dev-token-expirado' => [
                'sucesso'  => false,
                'tipo'     => 'regra_negocio',
                'codigo'   => 'CONVITE_EXPIRADO',
                'estado'   => self::ESTADO_EXPIRADO,
                'mensagem' => 'O prazo de validade deste convite terminou.',
                'dados'    => null,
            ],
            'dev-token-cancelado' => [
                'sucesso'  => false,
                'tipo'     => 'regra_negocio',
                'codigo'   => 'CONVITE_CANCELADO',
                'estado'   => self::ESTADO_CANCELADO,
                'mensagem' => 'Este convite foi cancelado pela administração.',
                'dados'    => null,
            ],
            'dev-token-utilizado' => [
                'sucesso'  => false,
                'tipo'     => 'regra_negocio',
                'codigo'   => 'CONVITE_UTILIZADO',
                'estado'   => self::ESTADO_UTILIZADO,
                'mensagem' => 'Este convite já foi utilizado.',
                'dados'    => null,
            ],
            'dev-token-erro' => [
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'ERRO_TEMPORARIO',
                'estado'   => self::ESTADO_ERRO_TEMPORARIO,
                'mensagem' => 'Não foi possível consultar os dados do convite no momento.',
                'dados'    => null,
            ],
            default => null,
        };
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
