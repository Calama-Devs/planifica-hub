# Banco de dados (MySQL)

Base: Manual Calama Devs, capítulo 8 e item 11.5. Em caso de dúvida, o manual prevalece.

## Modelagem

- Cada tabela representa uma entidade ou conceito bem definido.
- Toda tabela tem chave primária `id`, numérica e autoincrementável (exceções precisam de justificativa).
- Relacionamentos usam chave estrangeira real (`FOREIGN KEY`) sempre que aplicável.
- Evitar duplicar dados: informação reutilizada vai para tabela própria e é referenciada por `<entidade>_id` (singular, ex.: `curso_id`).
- Tipos adequados: números em tipos numéricos, datas em `DATE`/`DATETIME`, booleanos em `TINYINT(1)`, textos com tamanho compatível.
- Proibido criar campos genéricos: `valor1`, `campo_extra`, `descricao_extra`, `info`, `dados`, `texto`.

## Nomenclatura

| Elemento | Regra | Exemplos |
|---|---|---|
| Tabela | plural, minúsculas, sem acento, `snake_case` | `usuarios`, `solicitacao_arquivos`, `usuario_permissoes` |
| Campo | minúsculas, sem acento, `snake_case`, sem o tipo no nome | `nome`, `data_nascimento`, `curso_id` |
| Chave primária | `id` | `id` |
| Chave estrangeira | `<entidade>_id` (singular) | `usuario_id`, `curso_id` |
| Booleano | afirmativo, indicando estado | `ativo`, `publicado`, `bloqueado` |
| Situação de processo | `status` (valores documentados) ou termo específico | `status` |

Nomes proibidos para tabelas: `dados`, `registros`, `infos`, `tabela1`, `auxiliar`. Nunca usar maiúsculas, espaços, acentos ou caracteres especiais.

## Auditoria (manual 8.4)

- Campos padrão: `created_at`, `updated_at`, `deleted_at` (integração nativa com Models e Soft Deletes do CI4).
- Quando houver rastreio de autoria: `created_by`, `updated_by`, `deleted_by`, como chaves estrangeiras para a tabela de usuários.
- Obrigatórios em tabelas de processos críticos, permissões, movimentações administrativas, registros acadêmicos, documentos, solicitações e aprovações.
- Exclusão lógica (`deleted_at`) é a regra para dados relevantes; exclusão física só com justificativa.

## Migrations (manual 8.5)

- **Toda** alteração estrutural é feita por migration: criar, alterar ou remover tabela, campo, índice ou chave.
- A migration entra no mesmo conjunto de alterações (mesma issue e PR) da funcionalidade que depende dela.
- Uma migration por mudança coerente; nada de migrations gigantes misturando assuntos.
- Sempre implementar `down()` de forma reversível quando possível.
- Nome do arquivo no padrão do CI4, com descrição em português: `2026-05-29-143000_AdicionaStatusEmUsuarios.php` (gerado com `php spark make:migration AdicionaStatusEmUsuarios`).
- Proibido alterar estrutura manualmente em produção (phpMyAdmin, Workbench, SQL direto). Exceção emergencial precisa ser registrada, justificada, comunicada ao responsável técnico e depois refletida em migration.
- O rollback automático do DevCore desfaz só o código. Migration destrutiva (remover coluna, mudar tipo, mover dados) exige backup e plano de reversão registrado no PR.

```php
public function up(): void
{
    $this->forge->addColumn('usuarios', [
        'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ativo', 'after' => 'email'],
    ]);
}

public function down(): void
{
    $this->forge->dropColumn('usuarios', 'status');
}
```

## Seeds (manual 8.6)

- Dados iniciais obrigatórios (perfis, permissões, tipos, status, parâmetros, menus) vão em seeders.
- Seeders devem ser idempotentes (não duplicar ao rodar duas vezes).
- Uma classe por finalidade (`PerfisSeeder`, `PermissoesSeeder`).
- Nunca inserir dados fictícios em produção, salvo se fizerem parte da configuração oficial.

## Consultas (manual 6.4 e 11.5)

- Padrão: métodos da Model e Query Builder.
- SQL manual só com parametrização:

```php
// Correto
$this->db->table('usuarios')->where('id', $id)->get()->getRowArray();
$this->db->query('SELECT * FROM usuarios WHERE id = ?', [$id])->getRowArray();

// Proibido
$this->db->query("SELECT * FROM usuarios WHERE id = $id");
```

- Nomes de tabela, coluna ou ordenação vindos do usuário só podem ser usados após validação contra lista permitida (whitelist).
- Evitar consulta dentro de laço (N+1): buscar em lote com `whereIn` ou `join`.
- Operações que alteram várias tabelas de uma vez usam transação (`$db->transStart()` / `$db->transComplete()`) e verificam `transStatus()`.

## Dados sensíveis (manual 8.7 e 11.9)

- Senhas só como hash (`password_hash` / `password_verify`).
- Proibido versionar dumps, backups ou exportações com dados reais.
- Ambientes de desenvolvimento e teste usam dados fictícios ou anonimizados (`Usuario Teste`, `000.000.000-00`, `teste@example.com`).
- Coletar só os dados estritamente necessários à funcionalidade.

## Sinais de alerta para o revisor

- Alteração de estrutura sem migration no PR (bloqueante).
- SQL com variável concatenada (bloqueante).
- Migration sem `down()` ou destrutiva sem plano de backup.
- Tabela ou campo fora do padrão de nomes.
- Seed com dado pessoal real.
