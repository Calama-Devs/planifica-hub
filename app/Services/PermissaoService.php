<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PermissaoModel;
use Config\AuthGroups;

class PermissaoService
{
    private const REGEX_IDENTIFICADOR = '/^[a-z]+(_[a-z]+)*\.[a-z]+(_[a-z]+)*$/';

    private PermissaoModel $permissaoModel;

    public function __construct(?PermissaoModel $permissaoModel = null)
    {
        $this->permissaoModel = $permissaoModel ?? model(PermissaoModel::class);
    }

    /**
     * Retorna todas as permissões ativas ordenadas por identificador.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPermissoes(): array
    {
        return $this->permissaoModel->listarTodasAtivas();
    }

    /**
     * Busca uma permissão ativa pelo seu ID.
     *
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->permissaoModel->find($id);
    }

    /**
     * Cadastra uma nova permissão customizada, validando identificador e descrição.
     *
     * @param array<string, mixed> $dados
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string, id?: int}
     */
    public function cadastrarPermissao(array $dados, int $usuarioId): array
    {
        $identificador = trim((string) ($dados['identificador'] ?? ''));
        $descricao     = trim((string) ($dados['descricao'] ?? ''));

        if (! preg_match(self::REGEX_IDENTIFICADOR, $identificador)) {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'PERMISSAO_FORMATO_INVALIDO',
                'mensagem' => 'O identificador deve seguir o formato contexto.acao, '
                    . 'em minúsculas e sem acentos.',
            ];
        }

        if ($descricao === '' || mb_strlen($descricao) < 5 || mb_strlen($descricao) > 255) {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'PERMISSAO_DESCRICAO_INVALIDA',
                'mensagem' => 'A descrição é obrigatória e deve ter entre 5 e 255 caracteres.',
            ];
        }

        $existente = $this->permissaoModel->buscarPorIdentificador($identificador);
        if ($existente !== null) {
            return [
                'sucesso'  => false,
                'tipo'     => 'regra_negocio',
                'codigo'   => 'PERMISSAO_DUPLICADA',
                'mensagem' => 'Já existe uma permissão cadastrada com este identificador.',
            ];
        }

        $id = $this->permissaoModel->insert([
            'identificador' => $identificador,
            'descricao'     => $descricao,
            'origem'        => 'customizada',
            'created_by'    => $usuarioId,
            'updated_by'    => $usuarioId,
        ]);

        if (! $id) {
            log_message('error', 'Falha ao persistir permissão: {identificador}', ['identificador' => $identificador]);

            return [
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'PERMISSAO_ERRO_PERSISTENCIA',
                'mensagem' => 'Não foi possível salvar a permissão no momento.',
            ];
        }

        $this->sincronizarCatalogo();

        log_message('info', 'Permissão cadastrada: {identificador} (ID: {id}) pelo usuário ID {usuario_id}', [
            'identificador' => $identificador,
            'id'            => $id,
            'usuario_id'    => $usuarioId,
        ]);

        return [
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'PERMISSAO_CADASTRADA',
            'mensagem' => 'Permissão cadastrada com sucesso.',
            'id'       => (int) $id,
        ];
    }

    /**
     * Atualiza a descrição de uma permissão existente, preservando identificadores estáveis.
     *
     * @param array<string, mixed> $dados
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}
     */
    public function atualizarPermissao(int $id, array $dados, int $usuarioId): array
    {
        $permissao = $this->buscarPorId($id);
        if ($permissao === null) {
            return [
                'sucesso'  => false,
                'tipo'     => 'regra_negocio',
                'codigo'   => 'PERMISSAO_NAO_ENCONTRADA',
                'mensagem' => 'Permissão não encontrada.',
            ];
        }

        $descricao = trim((string) ($dados['descricao'] ?? ''));
        if ($descricao === '' || mb_strlen($descricao) < 5 || mb_strlen($descricao) > 255) {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'PERMISSAO_DESCRICAO_INVALIDA',
                'mensagem' => 'A descrição é obrigatória e deve ter entre 5 e 255 caracteres.',
            ];
        }

        $dadosAtualizacao = [
            'descricao'  => $descricao,
            'updated_by' => $usuarioId,
        ];

        // Se for informada tentativa de mudança de identificador
        if (isset($dados['identificador'])) {
            $novoIdentificador = trim((string) $dados['identificador']);

            if ($novoIdentificador !== $permissao['identificador']) {
                if ($permissao['origem'] === 'sistema') {
                    return [
                        'sucesso'  => false,
                        'tipo'     => 'regra_negocio',
                        'codigo'   => 'PERMISSAO_SISTEMA_IDENTIFICADOR_FIXO',
                        'mensagem' => 'O identificador de permissões do sistema é fixo e não pode ser alterado.',
                    ];
                }

                if (! preg_match(self::REGEX_IDENTIFICADOR, $novoIdentificador)) {
                    return [
                        'sucesso'  => false,
                        'tipo'     => 'validacao',
                        'codigo'   => 'PERMISSAO_FORMATO_INVALIDO',
                        'mensagem' => 'O identificador deve seguir o formato contexto.acao, '
                            . 'em minúsculas e sem acentos.',
                    ];
                }

                $existente = $this->permissaoModel->buscarPorIdentificador($novoIdentificador);
                if ($existente !== null && (int) $existente['id'] !== $id) {
                    return [
                        'sucesso'  => false,
                        'tipo'     => 'regra_negocio',
                        'codigo'   => 'PERMISSAO_DUPLICADA',
                        'mensagem' => 'Já existe outra permissão cadastrada com este identificador.',
                    ];
                }

                $dadosAtualizacao['identificador'] = $novoIdentificador;
            }
        }

        $this->permissaoModel->update($id, $dadosAtualizacao);
        $this->sincronizarCatalogo();

        log_message('info', 'Permissão atualizada (ID: {id}) pelo usuário ID {usuario_id}', [
            'id'         => $id,
            'usuario_id' => $usuarioId,
        ]);

        return [
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'PERMISSAO_ATUALIZADA',
            'mensagem' => 'Permissão atualizada com sucesso.',
        ];
    }

    /**
     * Sincroniza as permissões de código (AuthGroups.php) com o banco de dados
     * e consolida o catálogo ativo para consumo pelo Shield via setting().
     *
     * @return array<string, string> Mapa consolidado [identificador => descricao]
     */
    public function sincronizarCatalogo(): array
    {
        $permissoesCodigo = config(AuthGroups::class)->permissions;

        // Garante que todas as permissões declaradas em código existam no banco
        foreach ($permissoesCodigo as $identificador => $descricao) {
            $existente = $this->permissaoModel->buscarPorIdentificador($identificador);

            if ($existente === null) {
                $this->permissaoModel->insert([
                    'identificador' => $identificador,
                    'descricao'     => $descricao,
                    'origem'        => 'sistema',
                ]);
            }
        }

        // Obtém o catálogo consolidado de todas as permissões ativas
        $catalogoConsolidado = $this->permissaoModel->obterMapaCatalogo();

        // Atualiza a persistência de configurações consumida pelo Shield
        setting('AuthGroups.permissions', $catalogoConsolidado);

        return $catalogoConsolidado;
    }
}
