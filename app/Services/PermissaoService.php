<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PermissaoModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Shield\Models\UserModel;
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
        if (! $this->usuarioPodeAdministrar($usuarioId)) {
            return [
                'sucesso'  => false,
                'tipo'     => 'permissao',
                'codigo'   => 'ACESSOS_SEM_PERMISSAO',
                'mensagem' => 'Sem permissão para administrar acessos.',
            ];
        }

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

        try {
            $id = $this->permissaoModel->insert([
                'identificador' => $identificador,
                'descricao'     => $descricao,
                'origem'        => 'customizada',
                'created_by'    => $usuarioId,
                'updated_by'    => $usuarioId,
            ]);
        } catch (\Throwable $e) {
            $mensagemErro = $e->getMessage();
            $isDuplicada  = $e->getCode() === 1062
                || str_contains($mensagemErro, 'Duplicate entry')
                || str_contains($mensagemErro, '1062');

            if ($isDuplicada) {
                log_message('notice', 'Tentativa concorrente de duplicidade para permissão: {identificador}', [
                    'identificador' => $identificador,
                ]);

                return [
                    'sucesso'  => false,
                    'tipo'     => 'regra_negocio',
                    'codigo'   => 'PERMISSAO_DUPLICADA',
                    'mensagem' => 'Já existe uma permissão cadastrada com este identificador.',
                ];
            }

            log_message('error', 'Exceção de banco ao persistir permissão {identificador}: {mensagem}', [
                'identificador' => $identificador,
                'mensagem'      => $mensagemErro,
            ]);

            return [
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'PERMISSAO_ERRO_PERSISTENCIA',
                'mensagem' => 'Não foi possível salvar a permissão no momento.',
            ];
        }

        if (! $id) {
            /** @var array{code?: int|string, message?: string} $dbError */
            $dbError      = $this->permissaoModel->db->error();
            $mensagemErro = (string) ($dbError['message'] ?? '');
            $codigoErro   = (int) ($dbError['code'] ?? 0);

            if ($codigoErro === 1062 || str_contains($mensagemErro, 'Duplicate entry')) {
                return [
                    'sucesso'  => false,
                    'tipo'     => 'regra_negocio',
                    'codigo'   => 'PERMISSAO_DUPLICADA',
                    'mensagem' => 'Já existe uma permissão cadastrada com este identificador.',
                ];
            }

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
     * Atualiza a descrição de uma permissão existente.
     * Identificadores são imutáveis após o cadastro (preservação de identificadores estáveis).
     *
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string}
     */
    public function atualizarDescricao(int $id, string $descricao, int $usuarioId): array
    {
        if (! $this->usuarioPodeAdministrar($usuarioId)) {
            return [
                'sucesso'  => false,
                'tipo'     => 'permissao',
                'codigo'   => 'ACESSOS_SEM_PERMISSAO',
                'mensagem' => 'Sem permissão para administrar acessos.',
            ];
        }

        $permissao = $this->buscarPorId($id);
        if ($permissao === null) {
            return [
                'sucesso'  => false,
                'tipo'     => 'regra_negocio',
                'codigo'   => 'PERMISSAO_NAO_ENCONTRADA',
                'mensagem' => 'Permissão não encontrada.',
            ];
        }

        $descricaoLimpa = trim($descricao);
        if ($descricaoLimpa === '' || mb_strlen($descricaoLimpa) < 5 || mb_strlen($descricaoLimpa) > 255) {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'PERMISSAO_DESCRICAO_INVALIDA',
                'mensagem' => 'A descrição é obrigatória e deve ter entre 5 e 255 caracteres.',
            ];
        }

        $dadosAtualizacao = [
            'descricao'  => $descricaoLimpa,
            'updated_by' => $usuarioId,
        ];

        $atualizado = $this->permissaoModel->update($id, $dadosAtualizacao);

        if (! $atualizado) {
            log_message('error', 'Falha ao atualizar permissão ID {id}', ['id' => $id]);

            return [
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'PERMISSAO_ERRO_ATUALIZACAO',
                'mensagem' => 'Não foi possível atualizar a permissão no momento.',
            ];
        }

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
     * e garante a mescla transparente em memória sem sobrescrever settings.
     *
     * @return array<string, string> Mapa consolidado [identificador => descricao]
     */
    public function sincronizarCatalogo(): array
    {
        $permissoesCodigo = config(AuthGroups::class)->permissions;

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

        $settings = setting();
        if (is_object($settings)) {
            if (method_exists($settings, 'forget')) {
                $settings->forget('AuthGroups.permissions');
            } elseif (method_exists($settings, 'delete')) {
                $settings->delete('AuthGroups.permissions');
            }
        }

        $authGroups = config(AuthGroups::class);
        if ($authGroups instanceof AuthGroups) {
            $authGroups->carregarPermissoes();
        }

        return $this->permissaoModel->obterMapaCatalogo();
    }

    /**
     * Valida se o usuário informado possui autorização para administrar acessos (defesa em profundidade).
     */
    private function usuarioPodeAdministrar(int $usuarioId): bool
    {
        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        /** @var \CodeIgniter\Shield\Entities\User|null $usuario */
        $usuario = $userModel->findById($usuarioId);

        return $usuario !== null && $usuario->can('acessos.administrar');
    }
}
