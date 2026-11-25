<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UsuarioAutenticacaoModel;
use App\Validation\ValidadorNomeCompleto;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;
use Throwable;

class PerfilService
{
    private const COMPRIMENTO_MINIMO_NOME = 4;

    private const COMPRIMENTO_MAXIMO_NOME = 255;

    private UsuarioAutenticacaoModel $usuarios;

    private AuditoriaService $auditoria;

    public function __construct(?UsuarioAutenticacaoModel $usuarios = null, ?AuditoriaService $auditoria = null)
    {
        $this->usuarios  = $usuarios ?? model(UsuarioAutenticacaoModel::class);
        $this->auditoria = $auditoria ?? new AuditoriaService();
    }

    public function obterComprimentoMinimoNome(): int
    {
        return self::COMPRIMENTO_MINIMO_NOME;
    }

    public function obterComprimentoMaximoNome(): int
    {
        return self::COMPRIMENTO_MAXIMO_NOME;
    }

    /**
     * Grupos da conta pelo título configurado em AuthGroups; grupo sem título aparece pelo identificador.
     *
     * @return list<string>
     */
    public function listarGrupos(User $usuario): array
    {
        $gruposConfigurados = config('AuthGroups')->groups;

        return array_map(
            static fn (string $grupo): string => $gruposConfigurados[$grupo]['title'] ?? $grupo,
            $usuario->getGroups() ?? [],
        );
    }

    /**
     * Descrições das permissões do catálogo que a conta possui de fato, herdadas dos grupos
     * ou concedidas diretamente. Usa o can() do Shield em vez de deduzir pelo nome do grupo.
     *
     * @return list<string>
     */
    public function listarPermissoesEfetivas(User $usuario): array
    {
        $permissoes = [];

        foreach (config('AuthGroups')->permissions as $permissao => $descricao) {
            if ($usuario->can($permissao)) {
                $permissoes[] = $descricao;
            }
        }

        return $permissoes;
    }

    /**
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string, erros?: array<string, string>}
     */
    public function atualizarNome(User $usuario, string $nome): array
    {
        // Espaços repetidos entre as palavras são reduzidos a um, para não recusar um nome válido por digitação.
        $nome = trim((string) preg_replace('/\s+/u', ' ', $nome));

        $nomeAtual = (string) ($usuario->nome_completo ?? $usuario->username);

        if ($nome === trim($nomeAtual)) {
            return $this->erroValidacao('PERFIL_NOME_SEM_ALTERACAO', 'Informe um nome diferente do atual.');
        }

        $erroNome = $this->validarNome($nome);

        if ($erroNome !== null) {
            return $this->erroValidacao('PERFIL_NOME_INVALIDO', $erroNome);
        }

        if (! $this->salvarNome($usuario, $nome)) {
            return [
                'sucesso'  => false,
                'tipo'     => 'inesperado',
                'codigo'   => 'PERFIL_NOME_NAO_ATUALIZADO',
                'mensagem' => 'Não foi possível atualizar seu nome. Tente novamente.',
            ];
        }

        return [
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'PERFIL_NOME_ATUALIZADO',
            'mensagem' => 'Nome atualizado com sucesso.',
        ];
    }

    private function validarNome(string $nome): ?string
    {
        if ($nome === '') {
            return 'Informe seu nome.';
        }

        if (mb_strlen($nome) < self::COMPRIMENTO_MINIMO_NOME) {
            return 'O nome deve ter pelo menos ' . self::COMPRIMENTO_MINIMO_NOME . ' caracteres.';
        }

        if (mb_strlen($nome) > self::COMPRIMENTO_MAXIMO_NOME) {
            return 'O nome deve ter no máximo ' . self::COMPRIMENTO_MAXIMO_NOME . ' caracteres.';
        }

        if (! (new ValidadorNomeCompleto())->validar($nome)) {
            return 'O nome deve conter apenas letras, acentos e espaços.';
        }

        return null;
    }

    /**
     * Grava o nome e o evento de auditoria na mesma transação: sem o evento, a alteração é desfeita.
     */
    private function salvarNome(User $usuario, string $nome): bool
    {
        $banco = db_connect();

        if (! $banco->transBegin()) {
            log_message('error', '[PerfilService::salvarNome] Não foi possível iniciar a transação.');

            return false;
        }

        try {
            if (! $this->usuarios->update($usuario->id, ['nome_completo' => $nome])) {
                throw new RuntimeException('O nome não foi gravado.');
            }

            $this->auditoria->registrar([
                'modulo'        => 'perfil',
                'acao'          => 'alterar_nome',
                'entidade_tipo' => 'usuario',
                'entidade_id'   => (string) $usuario->id,
                'descricao'     => $this->descreverAlteracaoNome($usuario->nome_completo, $nome),
                'resultado'     => 'concluida',
            ]);

            if ($banco->transStatus() === false || ! $banco->transCommit()) {
                throw new RuntimeException('Falha na transação.');
            }
        } catch (Throwable $erro) {
            $banco->transRollback();
            log_message('error', '[PerfilService::salvarNome] Falha ao atualizar o nome da conta {id}: {erro}', [
                'id'   => $usuario->id,
                'erro' => $erro->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    private function descreverAlteracaoNome(?string $nomeAnterior, string $nomeNovo): string
    {
        if ($nomeAnterior === null || trim($nomeAnterior) === '') {
            return 'Nome de exibição definido como "' . $nomeNovo . '".';
        }

        return 'Nome de exibição alterado de "' . $nomeAnterior . '" para "' . $nomeNovo . '".';
    }

    /**
     * @return array{sucesso: false, tipo: string, codigo: string, mensagem: string, erros: array<string, string>}
     */
    private function erroValidacao(string $codigo, string $mensagem): array
    {
        return [
            'sucesso'  => false,
            'tipo'     => 'validacao',
            'codigo'   => $codigo,
            'mensagem' => $mensagem,
            'erros'    => ['nome' => $mensagem],
        ];
    }
}
