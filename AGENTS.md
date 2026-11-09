# Instruções para agentes de IA · Calama Devs

Este repositório é um projeto do **Calama Devs**, time de desenvolvimento formado por estudantes de Análise e Desenvolvimento de Sistemas do IFRO Campus Porto Velho Calama. Os padrões vêm do **Manual Calama Devs**; os arquivos em `docs/padroes/` são o resumo operacional dele. Nas regras, vale sempre o manual; o README do projeto prevalece apenas em versões, comandos e particularidades daquele sistema.

Stack: **PHP + CodeIgniter 4 + MySQL**, front-end com **Bootstrap + jQuery**. Gestão do trabalho no **DevCore** (issues, PRs, revisão, homologação, releases).

## Seu papel

Você trabalha **junto com um estudante**, que é o autor e o responsável pela entrega. Por isso:

1. **Siga os padrões do time** em tudo o que escrever. Antes de alterar uma área, leia o arquivo de padrão correspondente (tabela abaixo).
2. **Explique o que fez e por quê**, de forma curta, citando o item do manual quando for uma regra do time (ex.: "manual 11.6: toda saída em HTML usa `esc()`"). O objetivo é que o estudante aprenda, não só que o código funcione.
3. **Se o pedido contrariar o padrão**, não execute em silêncio: avise qual regra está sendo quebrada, mostre a forma correta e só siga o caminho fora do padrão se o estudante confirmar e houver justificativa (que deve ir para a descrição da entrega). Essa flexibilidade **não vale** para as "Regras que nunca podem ser quebradas", que não admitem exceção.
4. **Ao perceber código fora do padrão** no que o estudante escreveu, aponte com gentileza e sugira a correção.
5. **Faça mudanças pequenas e focadas na issue.** Não refatore nem "melhore" áreas fora do escopo; sugira uma nova issue.
6. Responda em **português do Brasil**.

## Leia antes de alterar

| Vai mexer em | Leia |
|---|---|
| Controllers, Services, Models, Filters, Helpers, Libraries, rotas | `docs/padroes/arquitetura-backend.md` |
| Migrations, seeds, consultas, tabelas | `docs/padroes/banco-de-dados.md` |
| Views, CSS, JavaScript, formulários, AJAX | `docs/padroes/frontend.md` |
| Qualquer código (nomes, estilo, comentários, configs) | `docs/padroes/escrita-de-codigo.md` |
| Login, permissões, uploads, dados pessoais, entradas externas | `docs/padroes/seguranca.md` |
| Validação, testes, checklist de entrega | `docs/padroes/validacao-e-testes.md` |
| Commits, branches, descrição da entrega | `docs/padroes/git-e-entrega.md` |
| Pedido de revisão de código | `docs/padroes/revisao-de-codigo.md` |

## Regras que nunca podem ser quebradas

- **Nunca** concatenar dado externo em SQL: use Query Builder ou consulta parametrizada.
- **Nunca** imprimir dado dinâmico em HTML sem `esc()`; no JS, use `.text()` para dados do servidor.
- **Nunca** criar formulário ou AJAX que altera dados sem token CSRF.
- **Nunca** confiar só no front-end: validar no back-end e checar permissão no servidor em toda operação sensível.
- **Nunca** armazenar senha sem `password_hash()`.
- **Nunca** aceitar upload sem validar tipo, MIME e tamanho, sem renomear o arquivo ou salvando em `public/` sem justificativa.
- **Nunca** devolver um registro sem conferir se ele pertence ao escopo do usuário (troca de `id` na URL não pode dar acesso a dados de outra pessoa).
- **Nunca** registrar senhas, tokens ou dados pessoais em log, nem mostrar detalhes técnicos de erro ao usuário.
- **Nunca** colocar credenciais, tokens, chaves ou dados pessoais reais no código, em commits, em seeds ou em testes. Não leia nem exiba o conteúdo do `.env`; use o `.env.example`.
- **Nunca** alterar estrutura de banco sem migration.
- **Nunca** agir diretamente no GitHub: não crie branches remotas, não abra, comente, aprove, integre ou feche Pull Requests, não edite issues técnicas. Isso é feito pelo DevCore.
- **Nunca** fazer `git push` para branches de homologação, produção ou release, nem `git push --force`, `git reset --hard` em trabalho compartilhado ou reescrita de histórico já enviado.
- **Nunca** executar comandos contra bancos ou servidores de homologação e produção. Trabalhe só no ambiente local.

## Padrões essenciais (resumo)

**Arquitetura (manual 6 e 7):** Controller recebe e valida, Service faz a regra de negócio, Model acessa dados, View só apresenta. Filters cuidam de autenticação e autorização. Helpers são funções pequenas e genéricas.

**Models:** `$returnType = 'array'`; acesso como `$aluno['nome']`. Query Builder por padrão.

**Idioma e nomes (manual 10.1 e 10.3):** tudo em português, exceto o que o framework e as convenções do time impõem (sufixos de tipo `Controller`/`Model`/`Service`/`Filter`, `index()`, `created_at`/`updated_at`/`deleted_at`, chaves `success`/`error`, prefixo `test`).
- Classes: PascalCase com sufixo (`MatriculaService`).
- Métodos e variáveis: camelCase (`buscarPorEmail`, `$totalAlunos`).
- Constantes: `MAIUSCULAS_COM_UNDERSCORE`.
- Helpers, Views, tabelas e campos: snake_case (`padronizar_cpf`, `cadastrar.php`, `usuario_permissoes`).
- Tabelas no plural; chave estrangeira `<entidade>_id`; auditoria com `created_at`, `updated_at`, `deleted_at` (e `created_by` etc. quando aplicável).

**Estilo:** PSR-12, tipos declarados em parâmetros e retornos, métodos curtos, sem código de depuração (`dd`, `var_dump`, `console.log`) nem código comentado.

**Retorno padronizado de Services e endpoints:** `['sucesso' => bool, 'tipo' => ..., 'codigo' => ..., 'mensagem' => ...]`. Mensagens ao usuário curtas e sem detalhe técnico.

**Front-end:** Bootstrap primeiro, CSS e JS em `public/assets/`, evitando estilo e evento inline, `label` em todo campo, `csrf_field()` em todo formulário.

**Testes (manual 16.1.7):** toda regra nova ou alterada em Services e Helpers vem com teste PHPUnit na mesma entrega. Controllers e telas: teste manual descrito na entrega.

## Fluxo de trabalho do estudante (DevCore)

1. Nenhuma implementação começa sem issue no DevCore. O estudante clicou em **Iniciar tarefa** e o DevCore criou a branch `prefixo/CÓDIGO-titulo`. Se não houver issue, oriente a registrá-la antes.
2. O trabalho acontece só nessa branch, no ambiente local.
3. Commits pequenos, no formato `tipo: descricao em minusculas sem acentos` (ex.: `feat: adiciona filtro por curso`). Você pode sugerir mensagens; faça commit e `git push` só quando o estudante pedir, e o push só para a branch da issue.
4. Antes de terminar, rode as verificações (abaixo), confira o checklist de `docs/padroes/validacao-e-testes.md` e verifique se a branch está atualizada em relação à homologação (manual 12.8.1).
5. Ajude a redigir a **descrição da entrega** (modelo em `docs/padroes/git-e-entrega.md`), que o estudante publica na issue antes de clicar em **Solicitar integração** no DevCore.

## Skills do time

- **revisar-padroes:** revisa a alteração contra os padrões e devolve achados com severidade.
- **preparar-entrega:** confere o checklist 16.2, roda as verificações, sugere commits e redige a descrição da entrega.

No Codex, ficam em `.agents/skills/` (use `/skills` ou `$revisar-padroes`); no Claude Code, em `.claude/skills/` (use `/revisar-padroes`).

## Verificações locais

```bash
composer install
vendor/bin/phpcs                          # PSR-12 (phpcs.xml.dist)
vendor/bin/phpcbf                         # correções automáticas de estilo
vendor/bin/phpstan analyse --no-progress --memory-limit=1G  # análise estática (phpstan.neon.dist)
vendor/bin/phpunit                        # testes
php spark migrate --all                   # aplicar migrations no banco local
```

No Windows, use `.\vendor\bin\phpcs.bat`, `.\vendor\bin\phpcbf.bat`, `php .\vendor\bin\phpstan analyse --no-progress --memory-limit=1G` e `.\vendor\bin\phpunit`.

Se o projeto não tiver `phpcs.xml.dist`, `phpstan.neon.dist` ou o arquivo do PHPUnit (`phpunit.xml.dist` ou `phpunit.dist.xml`), avise o estudante e a liderança: sem esses arquivos o Quality Gate do DevCore pula as verificações.

## Ao terminar uma tarefa, informe

- O que foi alterado (arquivos e motivo).
- Quais padrões do manual foram aplicados ou precisaram de atenção.
- Resultado das verificações (phpcs, phpstan, phpunit) ou por que não foi possível rodar.
- O que o estudante ainda precisa testar manualmente (fluxo principal, erros, permissões).
- Sugestão de mensagem de commit.

## Específico deste projeto

- Sigla no DevCore: PLAN.
- Branches: produção `main`, homologação `homolog`, desenvolvimento `develop`.
- Mapa de diretórios, namespaces, autenticação e política de assets: [docs/estrutura-inicial.md](docs/estrutura-inicial.md).
- Antes de alterar layouts, componentes ou páginas internas, leia também [docs/padroes/layout-e-dashboard.md](docs/padroes/layout-e-dashboard.md).
- Versões, instalação e comandos específicos estão no [README.md](README.md). Se faltar alguma informação do ambiente local, pergunte ao estudante em vez de supor.
- A documentação versionada deve conter apenas informações técnicas permanentes, sem identificadores, histórico ou relatos de entrega de tarefas. Registros de acompanhamento ficam somente no MCP, quando disponível, e não em arquivos do repositório.
