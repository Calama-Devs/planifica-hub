<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditoriaEdicaoContaModel;
use App\Models\UsuarioAutenticacaoModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use Throwable;

class EdicaoContaService
{
    private const PERMISSAO = 'contas.editar';
    private const OPERACAO = 'editar_dados_conta';
    private const JUSTIFICATIVA_MINIMA = 15;
    private const JUSTIFICATIVA_MAXIMA = 500;

    private UserModel $contas;
    private AuditoriaEdicaoContaModel $auditoria;
    private BaseConnection $banco;
    private ConfirmacaoOperacaoService $confirmacao;

    public function __construct(
        ?UserModel $contas = null,
        ?AuditoriaEdicaoContaModel $auditoria = null,
        ?BaseConnection $banco = null,
        ?ConfirmacaoOperacaoService $confirmacao = null,
    ) {
        $this->contas = $contas ?? model(UsuarioAutenticacaoModel::class);
        $this->auditoria = $auditoria ?? model(AuditoriaEdicaoContaModel::class);
        $this->banco = $banco ?? db_connect();
        $this->confirmacao = $confirmacao ?? service('confirmacaoOperacaoService');
    }

    /** @param array<string, mixed> $parametros @return array<string, mixed> */
    public function listarDataTable(array $parametros): array
    {
        $grupos = config('AuthGroups')->groups;
        $grupo = $parametros['grupo'] ?? '';
        $grupo = is_string($grupo) ? trim($grupo) : '';

        if ($grupo !== '' && ! array_key_exists($grupo, $grupos)) {
            return [
                'sucesso' => false,
                'tipo' => 'validacao',
                'codigo' => 'GRUPO_INVALIDO',
                'mensagem' => 'O grupo selecionado não é válido.',
            ];
        }

        $draw = $this->inteiroParametrizado($parametros['draw'] ?? 0, 0, PHP_INT_MAX, 0);
        $inicio = $this->inteiroParametrizado($parametros['start'] ?? 0, 0, PHP_INT_MAX, 0);
        $limite = $this->inteiroParametrizado($parametros['length'] ?? 10, 1, 100, 10);
        $busca = $parametros['search']['value'] ?? '';
        $busca = is_string($busca) ? trim($busca) : '';

        $configAuth = config('Auth');
        $tabelaUsuarios = $configAuth->tables['users'];
        $tabelaIdentidades = $configAuth->tables['identities'];
        $tabelaGrupos = $configAuth->tables['groups_users'];
        $query = $this->banco->table($tabelaUsuarios)
            ->select(
                $tabelaUsuarios . '.id, ' . $tabelaUsuarios . '.username, '
                . $tabelaUsuarios . '.nome_completo, ' . $tabelaIdentidades . '.secret AS email',
            )
            ->join(
                $tabelaIdentidades,
                $tabelaIdentidades . '.user_id = ' . $tabelaUsuarios . '.id AND '
                    . $tabelaIdentidades . '.type = ' . $this->banco->escape(Session::ID_TYPE_EMAIL_PASSWORD),
                'left',
            );
        $queryTotal = clone $query;
        $total = $queryTotal->countAllResults();

        if ($busca !== '') {
            $query->groupStart()
                ->like($tabelaUsuarios . '.nome_completo', $busca)
                ->groupEnd();
        }

        if ($grupo !== '') {
            $membros = $this->banco->table($tabelaGrupos)
                ->select('user_id')
                ->where('group', $grupo)
                ->get()
                ->getResultArray();
            $ids = array_map(static fn (array $linha): int => (int) $linha['user_id'], $membros);
            $query->whereIn($tabelaUsuarios . '.id', $ids === [] ? [0] : $ids);
        }

        $queryFiltrado = clone $query;
        $filtrados = $queryFiltrado->countAllResults();
        $linhas = $query->orderBy($tabelaUsuarios . '.nome_completo', 'ASC')
            ->orderBy($tabelaUsuarios . '.username', 'ASC')
            ->limit($limite, $inicio)
            ->get()
            ->getResultArray();
        $idsPagina = array_map(static fn (array $linha): int => (int) $linha['id'], $linhas);
        $gruposPorUsuario = $this->gruposDosUsuarios($idsPagina, $tabelaGrupos, $grupos);
        $dados = [];

        foreach ($linhas as $linha) {
            $id = (int) $linha['id'];
            $dados[] = [
                'id' => $id,
                'username' => (string) ($linha['nome_completo'] ?: $linha['username']),
                'email' => (string) ($linha['email'] ?? ''),
                'grupo' => implode(', ', $gruposPorUsuario[$id] ?? []),
            ];
        }

        return ['sucesso' => true, 'dados' => [
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtrados,
            'data' => $dados,
        ]];
    }

    public function consultar(int $contaId): ?array
    {
        $conta = $this->contas->findById($contaId);

        if ($conta === null) {
            return null;
        }

        return [
            'id' => (int) $conta->id,
            'nome' => (string) ($conta->nome_completo ?? $conta->username),
            'email' => (string) $conta->email,
        ];
    }

    /** @return array<string, mixed> */
    public function atualizar(
        int $contaId,
        string $nome,
        string $senha,
        User $operador,
        ?string $email = null,
        ?string $justificativa = null,
    ): array {
        if (! $operador->can(self::PERMISSAO)) {
            return $this->falha('permissao', 'CONTA_SEM_PERMISSAO', 'Você não tem permissão para editar contas.');
        }

        if ((int) $operador->id === $contaId) {
            return $this->falha('regra_negocio', 'CONTA_PROPRIA_NAO_EDITAVEL', 'Atualize seus dados em Meu Perfil.');
        }

        $conta = $this->contas->findById($contaId);

        if ($conta === null) {
            return $this->falha('regra_negocio', 'CONTA_NAO_ENCONTRADA', 'Conta não encontrada.');
        }

        $nome = preg_replace('/\s+/u', ' ', trim($nome)) ?? '';
        $nomeAtual = (string) ($conta->nome_completo ?? $conta->username);
        $emailAtual = (string) $conta->email;
        $emailNovo = $email === null || trim($email) === '' ? $emailAtual : mb_strtolower(trim($email));
        $alterarEmail = mb_strtolower($emailNovo) !== mb_strtolower(trim($emailAtual));

        if (! $this->nomeValido($nome)) {
            return $this->falha(
                'validacao',
                'CONTA_NOME_INVALIDO',
                'Informe um nome completo válido, com até 255 caracteres.',
            );
        }

        if ($alterarEmail) {
            $motivo = trim((string) $justificativa);

            if (
                mb_strlen($motivo) < self::JUSTIFICATIVA_MINIMA
                || mb_strlen($motivo) > self::JUSTIFICATIVA_MAXIMA
            ) {
                return $this->falha(
                    'validacao',
                    'JUSTIFICATIVA_EMAIL_INVALIDA',
                    'A justificativa deve ter entre 15 e 500 caracteres.',
                );
            }

            if (mb_strlen($emailNovo) > 254 || ! filter_var($emailNovo, FILTER_VALIDATE_EMAIL)) {
                return $this->falha('validacao', 'EMAIL_INVALIDO', 'Informe um e-mail válido.');
            }

            if (! $this->dominioPermitido($emailNovo)) {
                $dominios = implode(', ', config('Contas')->dominiosPermitidos);

                return $this->falha(
                    'validacao',
                    'EMAIL_DOMINIO_NAO_PERMITIDO',
                    'O domínio do e-mail não é permitido. Domínios aceitos: ' . $dominios . '.',
                );
            }

            if ($this->emailEmUso($emailNovo, $contaId)) {
                return $this->falha(
                    'validacao',
                    'EMAIL_DUPLICADO',
                    'Este e-mail já está vinculado a outra conta.',
                );
            }

            $justificativa = $motivo;
        } else {
            $justificativa = null;
        }

        if (
            ! $this->confirmacao->confirmar($operador, $senha, self::OPERACAO, $contaId)
            || ! $this->confirmacao->consumir($operador, self::OPERACAO, $contaId)
        ) {
            return $this->falha('permissao', 'CONFIRMACAO_INVALIDA', 'Confirme a operação com sua senha atual.');
        }

        $campos = [];
        if ($nome !== $nomeAtual) {
            $campos[] = 'nome_completo';
        }
        if ($alterarEmail) {
            $campos[] = 'email';
        }
        if ($campos === []) {
            return $this->falha('validacao', 'CONTA_SEM_ALTERACAO', 'Informe ao menos um dado diferente do atual.');
        }

        $this->banco->transBegin();
        $donaDaTransacao = $this->banco->transDepth === 1;

        try {
            if ($alterarEmail && ! $this->identidadeEmailExiste($contaId)) {
                $this->banco->transRollback();

                if ($donaDaTransacao) {
                    $this->banco->resetTransStatus();
                }

                return $this->falha(
                    'validacao',
                    'IDENTIDADE_EMAIL_AUSENTE',
                    'Esta conta não possui uma identidade de e-mail para atualizar.',
                );
            }

            if ($nome !== $nomeAtual && ! $this->contas->update($contaId, ['nome_completo' => $nome])) {
                throw new \RuntimeException('Falha ao atualizar nome.');
            }

            if ($alterarEmail && ! $this->atualizarEmail($contaId, $emailNovo)) {
                throw new \RuntimeException('Falha ao atualizar identidade de e-mail.');
            }

            $auditada = $this->auditoria->insert([
                'conta_id' => $contaId,
                'operador_id' => (int) $operador->id,
                'campos_alterados' => implode(',', $campos),
                'nome_anterior' => $nome !== $nomeAtual ? $nomeAtual : null,
                'nome_novo' => $nome !== $nomeAtual ? $nome : null,
                'email_anterior' => $alterarEmail ? $emailAtual : null,
                'email_novo' => $alterarEmail ? $emailNovo : null,
                'justificativa' => $justificativa,
                'resultado' => 'sucesso',
                'ip' => $this->minimizarIp(service('request')->getIPAddress()),
                'created_at' => date('Y-m-d H:i:s'),
            ]) !== false;

            if (! $auditada || ! $this->banco->transStatus()) {
                throw new \RuntimeException('Falha ao registrar auditoria.');
            }

            $this->banco->transCommit();
        } catch (Throwable $erro) {
            $this->banco->transRollback();

            if ($donaDaTransacao) {
                $this->banco->resetTransStatus();
            }

            log_message(
                'error',
                '[EdicaoContaService] Falha ao atualizar conta; tipo da exceção: {tipo}.',
                ['tipo' => get_class($erro)],
            );
            return $this->falha('inesperado', 'CONTA_ATUALIZACAO_FALHOU', 'Não foi possível concluir a atualização.');
        }

        return [
            'sucesso' => true,
            'tipo' => 'sucesso',
            'codigo' => 'CONTA_ATUALIZADA',
            'mensagem' => 'Dados atualizados com sucesso.',
        ];
    }

    private function nomeValido(string $nome): bool
    {
        return mb_strlen($nome) >= 2 && mb_strlen($nome) <= 255
            && preg_match('/\A[\p{L}\p{M}]+(?:[ \'\x{2019}-][\p{L}\p{M}]+)*\z/u', $nome) === 1;
    }

    private function emailEmUso(string $email, int $contaId): bool
    {
        $identidade = config('Auth')->tables['identities'];

        return $this->banco->table($identidade)
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->where('user_id !=', $contaId)
            ->where('LOWER(secret) =', mb_strtolower($email))
            ->countAllResults() > 0;
    }

    private function atualizarEmail(int $contaId, string $email): bool
    {
        $identidade = model(UserIdentityModel::class)
            ->where('user_id', $contaId)
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->first();

        return $identidade !== null
            && model(UserIdentityModel::class)->update($identidade->id, ['secret' => $email]);
    }

    private function identidadeEmailExiste(int $contaId): bool
    {
        return model(UserIdentityModel::class)
            ->where('user_id', $contaId)
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->first() !== null;
    }

    /** @param list<int> $ids @param array<string, mixed> $grupos @return array<int, list<string>> */
    private function gruposDosUsuarios(array $ids, string $tabelaGrupos, array $grupos): array
    {
        if ($ids === []) {
            return [];
        }

        $vinculos = $this->banco->table($tabelaGrupos)
            ->select('user_id, `group`')
            ->whereIn('user_id', $ids)
            ->get()
            ->getResultArray();
        $resultado = [];

        foreach ($vinculos as $vinculo) {
            $id = (int) $vinculo['user_id'];
            $chave = (string) $vinculo['group'];
            $resultado[$id][] = (string) ($grupos[$chave]['title'] ?? $chave);
        }

        return $resultado;
    }

    private function inteiroParametrizado(mixed $valor, int $minimo, int $maximo, int $padrao): int
    {
        $inteiro = filter_var($valor, FILTER_VALIDATE_INT);

        return $inteiro !== false && $inteiro >= $minimo && $inteiro <= $maximo ? $inteiro : $padrao;
    }

    private function dominioPermitido(string $email): bool
    {
        $email = mb_strtolower(trim($email));
        $posicao = strrpos($email, '@');

        if ($posicao === false) {
            return false;
        }

        $dominio = substr($email, $posicao + 1);

        return in_array($dominio, array_map('mb_strtolower', config('Contas')->dominiosPermitidos), true);
    }

    /**
     * Minimiza o endereço IP para auditoria (LGPD / minimização de dados).
     *
     * IPv4: último octeto zerado (ex.: 192.168.1.0).
     * IPv6: mantém os 3 primeiros grupos (ex.: 2001:db8:85a3).
     * Valor inválido ou vazio retorna null.
     *
     * Registros anteriores a esta regra permanecem como estão.
     */
    private function minimizarIp(string $ip): ?string
    {
        $ip = trim($ip);

        if ($ip === '' || $ip === '0.0.0.0') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $octetos = explode('.', $ip);
            $octetos[3] = '0';

            return implode('.', $octetos);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = inet_pton($ip);

            if ($bin === false) {
                return null;
            }

            $hex = bin2hex($bin);
            $grupos = str_split($hex, 4);
            $tresPrimeiros = array_map(
                static fn (string $g): string => ltrim($g, '0') ?: '0',
                array_slice($grupos, 0, 3),
            );

            return implode(':', $tresPrimeiros);
        }

        return null;
    }

    /** @return array{sucesso: false, tipo: string, codigo: string, mensagem: string} */
    private function falha(string $tipo, string $codigo, string $mensagem): array
    {
        return ['sucesso' => false, 'tipo' => $tipo, 'codigo' => $codigo, 'mensagem' => $mensagem];
    }
}
