# Permissões Diretas: Conceder, Revogar e Acesso Herdado

> **Base normativa:** Este guia segue o **Manual Calama Devs**: item 11.3 (permissões e menor privilégio), item 8.4 (auditoria), capítulo 6 (camadas e Filters) e item 16.1 (testes de permissão). Em caso de dúvida, o manual prevalece.

## 1. Visão Geral e Objetivo

A tela **Administração > Acessos** permite conceder e revogar **permissões diretas** de uma conta pelo CodeIgniter Shield, mostrando o que a conta recebe dos grupos e o que vale de fato. O catálogo de permissões e a convenção de nomes ficam em `docs/padroes/permissoes.md`; a proteção das rotas, em `docs/autorizacao/rotas-protegidas.md`. Nenhum dos dois se repete aqui.

**Prefira o grupo.** A permissão direta é exceção justificada (`docs/padroes/permissoes.md`, seção "Como checar", item 4): ela some do radar de quem revisa os grupos e continua valendo mesmo que a conta mude de papel.

### 1.1 Herdada, Direta e Efetiva

| Conceito | De onde vem | Onde fica gravada | Como muda |
|---|---|---|---|
| **Herdada** | Os grupos da conta (`$matrix` de `app/Config/AuthGroups.php`) | `auth_groups_users` (grupos da conta) e o arquivo de configuração (matriz) | Mudando o grupo da conta ou a matriz. **Não muda nesta tela.** |
| **Direta** | Vínculo gravado só para a conta | `auth_permissions_users` | Concedendo e revogando nesta tela |
| **Efetiva** | A soma das duas: o que `User::can()` e o filtro `permission:` avaliam | Calculada a cada leitura | Consequência das outras duas |

A operação só escreve em `auth_permissions_users` e na trilha `auditoria_permissoes`. Grupos da conta e matriz global de permissões nunca são alterados por ela.

## 2. Onde Fica e Quem Acessa

| Método | URL | Nome da rota | O que faz |
|---|---|---|---|
| GET | `/admin/acessos` | `acessos.index` | Lista as contas para escolher qual administrar |
| GET | `/admin/acessos/{id}/permissoes` | `acessos.permissoes` | Mostra as permissões do catálogo com herdada, direta e efetiva |
| POST | `/admin/acessos/{id}/permissoes/conceder` | `acessos.permissoes.conceder` | Concede uma permissão direta |
| POST | `/admin/acessos/{id}/permissoes/revogar` | `acessos.permissoes.revogar` | Revoga uma permissão direta |

- **Filtros de todas as rotas:** `csrf`, `session` e `permission:acessos.administrar` (conferível em `php spark routes`). Por padrão, só o grupo `superadmin` tem essa permissão.
- **Segunda barreira:** o `PermissaoDiretaService` repete a checagem de `acessos.administrar` do operador em cada concessão e revogação (retorno `ACESSOS_SEM_PERMISSAO`).
- **Menu:** o item "Acessos", na seção "Administração", só aparece para quem tem a permissão. É conveniência: quem digitar a URL sem a permissão é barrado pelo filtro.
- **`id` da conta:** de 1 a 999.999.999. Qualquer outro valor (zero, texto, número gigante) devolve 404.

## 3. Como Conceder uma Permissão Direta

1. Entre com uma conta que tenha `acessos.administrar` e abra **Administração > Acessos**.
2. Na linha da conta, clique em **Permissões**.
3. Na linha da permissão desejada, clique em **Conceder**.
4. A página recarrega. Confira que a linha mostra **Direta: Sim** e **Acesso efetivo: Sim**.

**Regras da concessão:**

- **Só permissão cadastrada:** a permissão precisa estar no catálogo ativo (`AuthGroups::$permissions`), o mesmo que o Shield consulta em `addPermission()`. Qualquer outro identificador é recusado (`PERMISSAO_NAO_CADASTRADA`) e nada é gravado.
- **Sem duplicar:** conceder o que a conta já tem como direta é recusado (`PERMISSAO_DIRETA_JA_CONCEDIDA`).
- **Permissão que a conta já herda:** a concessão é aceita, e a mensagem avisa que o acesso efetivo não mudou. A linha passa a mostrar "também herdada". Esse vínculo é redundante; veja a seção 5.

## 4. Como Revogar uma Permissão Direta

1. Na página de permissões da conta, clique em **Revogar** na linha da permissão.
2. Confirme no modal.
3. A página recarrega com uma mensagem sobre o resultado:
   - **Verde**, "a conta não tem mais acesso": o acesso acabou, porque a conta não recebia a permissão de nenhum grupo.
   - **Amarela**, "a conta continua com acesso herdado": só o vínculo direto saiu. Siga a seção 5.

**Regras da revogação:**

- **Só o vínculo direto:** revogar nunca retira grupo da conta nem mexe na matriz.
- **Só o que a conta tem:** revogar o que a conta não possui como direta é recusado (`PERMISSAO_DIRETA_NAO_CONCEDIDA`), mesmo que ela tenha a permissão por um grupo.
- **Vínculo fora do catálogo:** se o catálogo deixar de listar uma permissão que a conta ainda tem como direta, o vínculo continua valendo (o `can()` o reconhece). Ele aparece na seção "Vínculos diretos fora do catálogo" da mesma página e pode ser revogado ali.

## 5. Quando o Acesso Persiste por Herança

Revogar a permissão direta remove **somente o vínculo direto**. Se um grupo da conta concede a mesma permissão, o acesso efetivo continua, e a tela avisa com a mensagem amarela e com a coluna **Herdada de**, que lista os grupos.

**Exemplo (dados fictícios):** a conta `conta.teste` está no grupo `admin` e recebeu `contas.consultar` como direta. Ao revogar, a linha passa a mostrar:

| Permissão | Herdada de | Direta | Acesso efetivo |
|---|---|---|---|
| `contas.consultar` | Administrador de contas | Não | Sim |

Para realmente retirar o acesso, escolha o caminho que corresponde ao que se quer. Do menor para o maior impacto:

1. **Tirar a conta do grupo que concede.** Afeta só essa conta, mas retira dela **tudo** o que o grupo concede, não apenas a permissão em questão. Esta tela não altera grupos. No ambiente local, o comando do Shield faz a operação:

   ```bash
   php spark shield:user removegroup -n nome.usuario -g admin
   ```

   Em homologação e produção, a remoção do grupo é feita por responsável autorizado no servidor do ambiente, nunca a partir da máquina local (`docs/autenticacao/primeiro-administrador.md`, seção "Restrições").
2. **Alterar a matriz do grupo.** Afeta **todas** as contas do grupo. É mudança de código em `$matrix` (`app/Config/AuthGroups.php`): exige issue própria, revisão de segurança (manual 11.10) e a marcação "Altera permissões ou regras de acesso" na descrição da entrega (`docs/padroes/permissoes.md`, passo 8). Não use este caminho para resolver o caso de uma conta só.
3. **Manter o acesso.** Se a conta exerce o papel do grupo, o acesso herdado é legítimo, e a revogação apenas limpou um vínculo direto redundante.

Depois de qualquer um dos caminhos, reabra a página de permissões da conta e confira a coluna **Acesso efetivo**: ela é calculada com o `can()` do Shield, a mesma regra que o filtro de rota aplica.

## 6. Garantias da Operação

- **Persiste só vínculos diretos:** usa `User::addPermission()` e `User::removePermission()` do Shield. Não usa `syncGroups()`, `syncPermissions()` nem grava `AuthGroups.matrix` pelo `setting()`.
- **Vínculo e auditoria na mesma transação:** se a auditoria falhar, o vínculo também não é gravado, e o operador vê "Não foi possível concluir a operação" sem detalhe técnico. O detalhe vai para o log, sem dados pessoais.
- **Dentro de uma transação maior:** se o chamador já abriu uma transação, a operação participa dela. O CodeIgniter só efetiva a transação mais externa, então o `commit` e o `rollback` de verdade ficam com quem a abriu, que decide pelo `sucesso` do retorno.
- **Efeito real:** depois de gravar, o Service **recarrega a conta do banco** antes de calcular o resultado, porque o Shield guarda grupos e permissões em cache na própria entidade. A mensagem e a tela mostram o que o `can()` decide.
- **Herança calculada como o Shield calcula:** os grupos que concedem a permissão são achados com o mesmo `PermissionMatcher` do `can()`, então curingas na matriz (como `contas.*`) também aparecem.

## 7. Auditoria

Toda concessão e revogação grava uma linha em `auditoria_permissoes` (migration `CriarTabelaAuditoriaPermissoes`; aplique com `php spark migrate --all`):

| Coluna | Conteúdo |
|---|---|
| `usuario_id` | Conta que teve a permissão alterada |
| `permissao` | Identificador da permissão |
| `acao` | `concedida` ou `revogada` |
| `created_by` | Operador que fez a alteração |
| `created_at` | Data e hora |

Consulta de exemplo (somente leitura):

```sql
SELECT a.created_at, a.acao, a.permissao, u.username AS conta, o.username AS operador
FROM auditoria_permissoes a
JOIN users u ON u.id = a.usuario_id
JOIN users o ON o.id = a.created_by
ORDER BY a.id DESC
LIMIT 20;
```

- **Tabela só de inclusão:** não tem `updated_at` nem `deleted_at`, e as chaves estrangeiras usam `RESTRICT`. Não edite nem apague linhas.
- **Sem dados pessoais além dos ids** (manual 11.9): não há IP, e-mail nem senha na trilha.
- Operações recusadas (permissão inválida, operador sem permissão, vínculo duplicado) não geram linha: nada foi alterado.

## 8. Como Testar

### 8.1 Teste Automatizado

- `tests/database/PermissaoDiretaServiceTest.php`: regras do Service (concessão, revogação, herança preservada, permissão fora do catálogo, operador sem permissão, duplicidade, auditoria e rollback quando ela falha, e a igualdade entre a coluna "efetiva" e o `can()` do Shield).
- `tests/feature/AcessoPermissoesTest.php`: teste de URL direta (`rotas-protegidas.md`, seção 8) e o efeito real na rota. O teste `testContaGanhaEPerdeAcessoARotaConformeAsAlteracoes` mostra uma conta sem acesso a `/admin/acessos`, ganhando acesso depois da concessão de `acessos.administrar` e perdendo de novo depois da revogação. O teste `testRevogarNaoTiraOAcessoQueVemDoGrupo` prova que o acesso continua quando vem do grupo.

Ao testar uma rota depois de uma alteração, **recarregue a conta do banco** antes do `actingAs()` (`Tests\Support\Traits\CriaContasTrait::recarregar()`): reaproveitar a entidade antiga usa o cache de permissões de antes da mudança e esconde o efeito.

### 8.2 Roteiro Manual no Navegador

1. Suba o MySQL do XAMPP, rode `php spark migrate --all` e `php spark serve`. Abra `http://localhost:8080/index.php/login`.
2. Prepare duas contas com dados fictícios (manual 11.9), pelo CLI do Shield: uma no grupo `superadmin` e outra no grupo `user` (por exemplo, `conta.teste`). A senha é pedida no terminal.
3. Entre como `superadmin`, abra **Administração > Acessos > conta.teste > Permissões** e conceda `acessos.administrar`. Confira "Direta: Sim" e "Acesso efetivo: Sim".
4. Em **janela anônima**, entre como `conta.teste`: o item "Acessos" aparece no menu e `/admin/acessos` abre.
5. Como `superadmin`, revogue a permissão. Na janela anônima, atualize: `/admin/acessos` redireciona para `/` com a mensagem de erro.
6. Caso de herança: coloque `conta.teste` no grupo `admin` (`php spark shield:user addgroup -n conta.teste -g admin`), conceda `contas.consultar` e revogue. A mensagem é amarela, e a linha mostra "Herdada de: Administrador de contas", "Direta: Não" e "Acesso efetivo: Sim".
7. Confira a trilha de auditoria com a consulta da seção 7.
8. Descreva na entrega quais contas e quais URLs foram testadas.

## 9. Referências

- `docs/padroes/permissoes.md`: catálogo de permissões, convenção de nomes e como acrescentar uma permissão.
- `docs/autorizacao/rotas-protegidas.md`: filtros de rota e teste de URL direta.
- `docs/padroes/seguranca.md` (regra 4; manual 11.3 e 11.9) e `docs/padroes/banco-de-dados.md` (auditoria, manual 8.4).
- [CodeIgniter Shield, autorização](https://shield.codeigniter.com/latest/quick_start_guide/using_authorization/)
