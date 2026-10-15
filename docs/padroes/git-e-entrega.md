# Git, commits e entrega pelo DevCore

Base: Manual Calama Devs, capítulos 12, 14, 19 e 26. Em caso de dúvida, o manual prevalece.

## Regra principal

Todo o fluxo acontece no **DevCore**. No Git, o desenvolvedor só baixa a branch criada pelo DevCore, faz commits e envia com `git push` para **essa** branch. Nada é feito diretamente no GitHub: não se cria branch, não se abre, comenta, integra ou fecha Pull Request, não se edita issue técnica.

## Branches (manual 12.4 e 12.5)

- A branch é criada pelo DevCore em **Iniciar tarefa**, a partir da branch de homologação, no padrão `prefixo/CÓDIGO-titulo-da-issue` (ex.: `feature/DC-142-cadastro-de-servidores`).
- Prefixos por tipo de issue: `feature`, `fix`, `hotfix` (a partir da produção), `refactor`, `docs`, `test`, `chore`.
- Uma branch por issue, uma issue por branch. Nunca misturar demandas.
- Push permitido apenas na branch da própria issue. Homologação e produção são protegidas.
- Proibido `git push --force` em branch compartilhada e proibido criar branch manualmente no GitHub.

Comandos usuais (os exatos aparecem no painel Git da issue):

```bash
git fetch origin
git checkout feature/DC-142-cadastro-de-servidores
git pull                       # atualizar a branch
git add <arquivos>
git commit -m "feat: adiciona cadastro de servidores"
git push
```

## Commits (manual 12.6 e 12.7)

Formato: `tipo: descrição`, tudo em **minúsculas, sem acentos e sem caracteres especiais**, verbo no presente (3ª pessoa).

| Tipo | Uso | Exemplo |
|---|---|---|
| `feat` | nova funcionalidade | `feat: adiciona tela de login` |
| `fix` | correção | `fix: corrige validacao de cpf` |
| `docs` | documentação | `docs: atualiza padrao de branches` |
| `test` | testes | `test: adiciona teste de autenticacao` |
| `perf` | desempenho | `perf: otimiza consulta de usuarios` |
| `style` | formatação, sem mudar comportamento | `style: ajusta indentacao do controller` |
| `refactor` | refatoração sem mudar comportamento | `refactor: reorganiza camada de servicos` |
| `chore` | manutenção e configuração | `chore: adiciona arquivo gitignore` |
| `cleanup` | remoção de código morto ou duplicado | `cleanup: remove codigo comentado` |
| `remove` | remoção de arquivos ou funcionalidades | `remove: exclui componente legado` |

- Commits pequenos, frequentes e com uma responsabilidade cada.
- Separar funcionalidade, correção e refatoração em commits distintos.
- Errado: `ajustes`, `wip`, `teste`, `feat: adiciona validação de CPF` (acento e maiúscula), `fix: corrige erro no login!`.

## O que nunca vai para o repositório (manual 12.6.3)

`.env` real, senhas, tokens, chaves de API, dumps com dados reais, logs, cache, arquivos temporários, `vendor/`, `node_modules/`, configurações locais de IDE e dados pessoais. Conferir o `.gitignore` antes do primeiro commit.

## Entrega (manual 12.8 e 14)

1. Conferir o checklist do item 16.2 (ver `validacao-e-testes.md`).
2. Publicar na issue, como comentário, a **descrição da entrega**:

```markdown
# Resumo (OBRIGATÓRIO)
O que foi feito e por quê, em poucas linhas.

# Tipo de alteração (OBRIGATÓRIO)
- [ ] Correção de bug  - [ ] Nova funcionalidade  - [ ] Ajuste visual  - [ ] Refatoração
- [ ] Banco de dados   - [ ] Permissões/acesso    - [ ] Documentação   - [ ] Outro: ___

# Principais alterações (OBRIGATÓRIO)
- adiciona ...
- corrige ...

# Impacto da alteração (OBRIGATÓRIO)
- [ ] Altera banco de dados  - [ ] Exige execução de migration
- [ ] Altera permissões ou regras de acesso  - [ ] Altera arquivos de configuração
- [ ] Afeta telas já existentes  - [ ] Não há impacto relevante fora desta alteração

# Checklist (OBRIGATÓRIO)
- [ ] Testei localmente
- [ ] Não enviei arquivos desnecessários
- [ ] Não incluí senhas, credenciais ou dados sensíveis
- [ ] Criei migrations quando houve alteração no banco

# Evidências (OPCIONAL, esperado se mudou a interface)
# Como testar (OPCIONAL)
# Observações para o revisor (OPCIONAL; obrigatório detalhar migrations aqui)
```

3. Clicar em **Solicitar integração** no DevCore. Ele abre o PR, roda o Quality Gate (PSR-12, PHPStan, testes, dependências) e a revisão por IA (achados Crítico e Alto bloqueiam).
4. Usar **Solicitar revisão** no painel Code review. A gestão autoriza os revisores.
5. Ajustes pedidos: corrigir na mesma branch, novos commits, **Solicitar integração** de novo. Cada commit novo reinicia as verificações.
6. A integração em homologação é feita pela liderança ou gerência; a homologação é feita por outra pessoa; a produção vai por release. O autor nunca integra nem homologa a própria entrega.

## Issues (manual 19)

- Nenhuma implementação começa sem issue no DevCore.
- Título curto e específico, sem repetir tipo nem código (`Erro 500 ao salvar cadastro de usuário`).
- Critérios de aceite verificáveis, em checklist.
- Decisões, mudanças de escopo e impedimentos registrados em comentários na issue.
- Uma issue em desenvolvimento por vez; issue parada é pausada, bloqueada ou devolvida, com motivo.
