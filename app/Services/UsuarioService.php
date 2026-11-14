<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditoriaUsuarioModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Pager\Pager;
use CodeIgniter\Shield\Authorization\PermissionMatcher;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\AuthGroups;
use RuntimeException;
use Throwable;

/** Regras de consulta e cadastro administrativo de usuários. */
class UsuarioService
{
    private const PERMISSAO_CONSULTAR = 'contas.consultar';
    private const PERMISSAO_CRIAR     = 'contas.criar';

    private UserModel $usuarios;
    private AuditoriaUsuarioModel $auditoria;
    private BaseConnection $banco;

    public function __construct(
        ?UserModel $usuarios = null,
        ?BaseConnection $banco = null,
        ?AuditoriaUsuarioModel $auditoria = null,
    ) {
        $this->usuarios = $usuarios ?? model(UserModel::class);
        $this->auditoria = $auditoria ?? model(AuditoriaUsuarioModel::class);
        $this->banco   = $banco ?? db_connect();
    }

    /**
     * Lista usuários com busca opcional por nome ou e-mail e paginação.
     *
     * @return array{usuarios: list<array<string, mixed>>, paginador: Pager}
     */
    public function listar(int $operadorId, string $busca = '', int $porPagina = 15): array
    {
        if (! $this->operadorPode($operadorId, self::PERMISSAO_CONSULTAR)) {
            $this->registrarEvento(null, $operadorId, 'consulta', 'recusada');

            return ['usuarios' => [], 'paginador' => service('pager')];
        }

        $modelo = model(UserModel::class, false);
        $tabelaUsuarios    = $this->banco->prefixTable('users');
        $tabelaIdentidades = $this->banco->prefixTable('auth_identities');
        // Mantém contas sem identidade de e-mail no resultado e evita duplicar usuários com outras identidades.
        $condicaoEmail     = 'identidade_email.user_id = ' . $tabelaUsuarios . '.id'
            . " AND identidade_email.type = 'email_password'";

        $modelo->select('users.id, users.username, identidade_email.secret AS email')
            ->join(
                $tabelaIdentidades . ' AS identidade_email',
                $condicaoEmail,
                'left',
                false,
            )
            ->withGroups()
            ->orderBy('users.username', 'ASC');

        $busca = trim($busca);

        if ($busca !== '') {
            $modelo->groupStart()
                ->like('users.username', $busca)
                ->orLike('identidade_email.secret', $busca)
                ->groupEnd();
        }

        $resultado = $modelo->paginate(max(1, min($porPagina, 100)));
        $lista     = [];

        foreach ($resultado as $usuario) {
            if (! $usuario instanceof User) {
                continue;
            }

            $lista[] = [
                'id'     => (int) $usuario->id,
                'nome'   => (string) $usuario->username,
                'email'  => (string) ($usuario->email ?? ''),
                'grupos' => $this->titulosDosGrupos($usuario->getGroups() ?? []),
            ];
        }

        return ['usuarios' => $lista, 'paginador' => $modelo->pager];
    }

    /** @return array<string, string> Grupos compatíveis com as autorizações do operador. */
    public function gruposConcediveis(int $operadorId): array
    {
        if (! $this->operadorPode($operadorId, self::PERMISSAO_CRIAR)) {
            return [];
        }

        $configuracao = config(AuthGroups::class);
        $grupos       = [];

        foreach ($configuracao->groups as $identificador => $dados) {
            if ($this->grupoPodeSerConcedido($operadorId, $identificador, $configuracao->matrix)) {
                $grupos[$identificador] = $dados['title'];
            }
        }

        return $grupos;
    }

    public function registrarGrupoRecusado(int $operadorId): void
    {
        $this->registrarEvento(null, $operadorId, 'cadastro', 'recusada');
    }

    /**
     * Cria a conta e identidade de e-mail/senha pelas APIs do Shield e associa um único grupo permitido.
     *
     * @param array{nome: string, email: string, senha: string, grupo: string} $dados
     * @return array{sucesso: bool, tipo: string, codigo: string, mensagem: string, id?: int}
     */
    public function criar(array $dados, int $operadorId): array
    {
        if (! $this->operadorPode($operadorId, self::PERMISSAO_CRIAR)) {
            $this->registrarEvento(null, $operadorId, 'cadastro', 'recusada');

            return $this->falha('permissao', 'CONTAS_SEM_PERMISSAO', 'Sem permissão para cadastrar contas.');
        }

        $grupo = $dados['grupo'] ?? '';

        if (! is_string($grupo) || ! array_key_exists($grupo, $this->gruposConcediveis($operadorId))) {
            $this->registrarEvento(null, $operadorId, 'cadastro', 'recusada');

            return $this->falha('permissao', 'GRUPO_NAO_CONCEDIVEL', 'O grupo selecionado não pode ser concedido.');
        }

        $nome  = trim($dados['nome']);
        $email = strtolower(trim($dados['email']));

        $contaParaValidarSenha = new User(['username' => $nome]);
        $contaParaValidarSenha->setEmail($email);
        $validacaoSenha = service('passwords')->check($dados['senha'], $contaParaValidarSenha);

        if (! $validacaoSenha->isOK()) {
            return [
                'sucesso'  => false,
                'tipo'     => 'validacao',
                'codigo'   => 'SENHA_INVALIDA',
                'mensagem' => 'A senha não atende aos requisitos de segurança do sistema.',
                'erros'    => [
                    'senha' => 'Escolha uma senha diferente do nome e que atenda aos requisitos do sistema.',
                ],
            ];
        }

        if ($this->nomeJaExiste($nome) || $this->emailJaExiste($email)) {
            return $this->falha('validacao', 'CONTA_DUPLICADA', 'Já existe uma conta com os dados informados.');
        }

        $this->banco->transBegin();
        $dona = $this->banco->transDepth === 1;

        try {
            $conta = new User(['username' => $nome, 'active' => true]);

            if (! $this->usuarios->save($conta)) {
                throw new RuntimeException('Falha ao salvar a conta no Shield.');
            }

            $id = (int) $this->usuarios->getInsertID();
            /** @var User|null $contaSalva */
            $contaSalva = $this->usuarios->findById($id);

            if ($contaSalva === null) {
                throw new RuntimeException('A conta salva não foi encontrada.');
            }

            $contaSalva->createEmailIdentity([
                'email'    => $email,
                'password' => $dados['senha'],
            ]);

            $contaSalva->addGroup($grupo);

            if (! $this->registrarEvento($id, $operadorId, 'cadastro', 'sucesso', $grupo)) {
                throw new RuntimeException('Falha ao registrar auditoria da conta.');
            }

            $this->banco->transCommit();
        } catch (Throwable) {
            $this->banco->transRollback();

            if ($dona) {
                $this->banco->resetTransStatus();
            }

            $this->registrarEvento(null, $operadorId, 'cadastro', 'falha');
            // A exceção do Shield pode conter SQL e hash de senha; registre só o contexto seguro.
            log_message('error', 'Falha técnica no cadastro de conta pelo operador {operador}', [
                'operador' => $operadorId,
            ]);

            return $this->falha('inesperado', 'CONTAS_ERRO_PERSISTENCIA', 'Não foi possível cadastrar a conta.');
        }

        return [
            'sucesso'  => true,
            'tipo'     => 'sucesso',
            'codigo'   => 'CONTA_CADASTRADA',
            'mensagem' => 'Conta cadastrada com sucesso.',
            'id'       => $id,
        ];
    }

    /**
     * @param array<string, list<string>> $matriz
     */
    private function grupoPodeSerConcedido(int $operadorId, string $grupo, array $matriz): bool
    {
        $catalogo  = setting('AuthGroups.permissions');
        $catalogo  = is_array($catalogo) ? $catalogo : [];
        $permissoes = $matriz[$grupo] ?? [];
        $operador   = $this->usuarioPorId($operadorId);

        if ($operador === null) {
            return false;
        }

        foreach ($permissoes as $regra) {
            $encontrada = false;

            foreach (array_keys($catalogo) as $permissao) {
                if (! PermissionMatcher::matches($permissao, [$regra])) {
                    continue;
                }

                $encontrada = true;

                if (! $operador->can($permissao)) {
                    return false;
                }
            }

            if (! $encontrada) {
                return false;
            }
        }

        return true;
    }

    private function operadorPode(int $operadorId, string $permissao): bool
    {
        $operador = $this->usuarioPorId($operadorId);

        return $operador !== null && $operador->can($permissao);
    }

    private function usuarioPorId(int $id): ?User
    {
        $usuario = $this->usuarios->findById($id);

        return $usuario instanceof User ? $usuario : null;
    }

    private function nomeJaExiste(string $nome): bool
    {
        return $this->banco->table('users')->where('username', $nome)->countAllResults() > 0;
    }

    private function emailJaExiste(string $email): bool
    {
        return $this->banco->table('auth_identities')
            ->where('type', 'email_password')
            ->where('secret', $email)
            ->countAllResults() > 0;
    }

    /** @param list<string> $grupos */
    private function titulosDosGrupos(array $grupos): array
    {
        $definidos = setting('AuthGroups.groups');
        $definidos = is_array($definidos) ? $definidos : [];

        return array_map(
            static fn (string $grupo): string => (string) ($definidos[$grupo]['title'] ?? $grupo),
            $grupos,
        );
    }

    /** Persiste auditoria sem e-mail, nome, senha ou hash. */
    private function registrarEvento(
        ?int $usuarioId,
        int $operadorId,
        string $operacao,
        string $resultado,
        ?string $grupo = null,
    ): bool {
        $gravado = $this->auditoria->registrar($usuarioId, $operadorId, $operacao, $resultado, $grupo);

        if (! $gravado) {
            log_message('error', 'Falha ao registrar auditoria da operação {operacao}; operador {operador}', [
                'operacao' => $operacao,
                'operador' => $operadorId,
            ]);
        }

        return $gravado;
    }

    /** @return array{sucesso: false, tipo: string, codigo: string, mensagem: string} */
    private function falha(string $tipo, string $codigo, string $mensagem): array
    {
        return ['sucesso' => false, 'tipo' => $tipo, 'codigo' => $codigo, 'mensagem' => $mensagem];
    }
}
