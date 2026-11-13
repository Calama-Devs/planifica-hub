# Permissões Diretas: Conceder, Revogar e Acesso Herdado

> **Base normativa:** Este guia segue o **Manual Calama Devs**: item 11.3 (permissões e menor privilégio), item 8.4 (auditoria), capítulo 6 (camadas e Filters) e item 16.1 (testes de permissão). Em caso de dúvida, o manual prevalece.

## 1. Visão Geral e Objetivo

A tela **Administração > Acessos** permite conceder e revogar **permissões diretas** de uma conta pelo CodeIgniter Shield, com **caixas de seleção**, mostrando o que a conta recebe dos grupos e o que vale de fato. O catálogo de permissões e a convenção de nomes ficam em `docs/padroes/permissoes.md`; a proteção das rotas, em `docs/autorizacao/rotas-protegidas.md`. Nenhum dos dois se repete aqui.

**Prefira o grupo.** A permissão direta é exceção justificada (`docs/padroes/permissoes.md`, seção "Como checar", item 4): ela some do radar de quem revisa os grupos e continua valendo mesmo que a conta mude de papel.

### 1.1 Herdada, Direta e Efetiva

| Conceito | De onde vem | Onde fica gravada | Como muda |
|---|---|---|---|
| **Herdada** | Os grupos da conta (`$matrix` de `app/Config/AuthGroups.php`) | `auth_groups_users` (grupos da conta) e o arquivo de configuração (matriz) | Mudando o grupo da conta ou a matriz. **Não muda nesta tela.** |
| **Direta** | Vínculo gravado só para a conta | `auth_permissions_users` | Marcando ou desmarcando a caixa e salvando nesta tela |
| **Efetiva** | A soma das duas: o que `User::can()` e o filtro `permission:` avaliam | Calculada a cada leitura | Consequência das outras duas |

A operação só escreve em `auth_permissions_users` e na trilha `auditoria_permissoes`. Grupos da conta e matriz global de permissões nunca são alterados por ela.

### 1.2 Como É a Tela

A página de uma conta tem um cabeçalho e dois cartões, sob um só formulário.

- **Cabeçalho:** o caminho "Acessos / conta", com o link de volta à lista, e o título "Conta: nome".
- **Cartão da conta:** conta, e-mail e grupos, o botão **Salvar permissões**, o link **Voltar**, a nota de que as alterações valem na hora e ficam na auditoria, e o quadro "Como ler esta tela".
- **Cartão das permissões:** as permissões do catálogo em **caixas de seleção**, em duas colunas e agrupadas por categoria, que é o trecho do identificador antes do ponto (`contas.consultar` fica em "Contas"). As categorias seguem a ordem alfabética do contexto, e as permissões de cada uma seguem a ordem do catálogo, alfabética pelo identificador. Cada linha é compacta: a caixa, a descrição da permissão e um ícone de informação, cujo tooltip mostra o identificador (`Identificador: contas.consultar`). Quem usa leitor de tela ouve o identificador junto da caixa.
- **Caixa marcada:** é o vínculo **direto** da conta. A caixa nunca representa o acesso herdado.
- **Herdada de:** linha de apoio, abaixo da descrição, com o grupo que concede a permissão (uma linha por grupo).
- **Acesso efetivo:** ícone verde ao lado da descrição, quando a conta pode usar a permissão, venha de onde vier. Uma permissão só herdada aparece com a caixa desmarcada, a linha "Herdada de" e o ícone verde.
- **Permissão crítica:** tem um ícone de alerta vermelho antes da descrição, porque quem a tem pode administrar o acesso de outras contas. Hoje só `acessos.administrar` é crítica; para destacar outra, acrescente o identificador em `PERMISSOES_CRITICAS` no `PermissaoDiretaService`.
- **marcar todas** e **desmarcar:** ficam no topo da lista e agem só nas permissões do catálogo. "Marcar todas" não marca as críticas, que precisam ser escolhidas uma a uma. Nada é gravado até clicar em **Salvar permissões**.
- **Fora do catálogo:** vínculos diretos que o catálogo não lista mais aparecem marcados numa categoria própria, e os atalhos acima não mexem neles: cada um é desmarcado à mão. Desmarque e salve para revogá-los.

## 2. Onde Fica e Quem Acessa

| Método | URL | Nome da rota | O que faz |
|---|---|---|---|
| GET | `/admin/acessos` | `acessos.index` | Lista as contas para escolher qual administrar |
| GET | `/admin/acessos/{id}/permissoes` | `acessos.permissoes` | Mostra as permissões do catálogo em caixas de seleção, com a origem herdada e o acesso efetivo |
| POST | `/admin/acessos/{id}/permissoes` | `acessos.permissoes.salvar` | Salva as caixas marcadas: concede as permissões diretas que faltam e revoga as desmarcadas |

- **Filtros de todas as rotas:** `csrf`, `session` e `permission:acessos.administrar` (conferível em `php spark routes`). Por padrão, só o grupo `superadmin` tem essa permissão.
- **Segunda barreira:** o `PermissaoDiretaService` repete a checagem de `acessos.administrar` do operador em cada salvamento (retorno `ACESSOS_SEM_PERMISSAO`).
- **Menu:** o item "Acessos", na seção "Administração", só aparece para quem tem a permissão. É conveniência: quem digitar a URL sem a permissão é barrado pelo filtro.
- **`id` da conta:** de 1 a 999.999.999. Qualquer outro valor (zero, texto, número gigante) devolve 404.

## 3. Como Conceder uma Permissão Direta

1. Entre com uma conta que tenha `acessos.administrar` e abra **Administração > Acessos**.
2. Na linha da conta, clique em **Permissões**.
3. Marque a caixa da permissão desejada (pode marcar várias) e clique em **Salvar permissões**.
4. A página recarrega com uma mensagem, por exemplo "Permissões diretas salvas: 1 concedida.". Confira que a caixa ficou marcada e que o ícone verde de **acesso efetivo** aparece ao lado da descrição.

**Regras da concessão:**

- **Só permissão cadastrada:** a permissão precisa estar no catálogo ativo (`AuthGroups::$permissions`), o mesmo que o Shield consulta em `addPermission()`. Qualquer outro identificador recusa o salvamento inteiro (`PERMISSAO_NAO_CADASTRADA`) e nada é gravado.
- **Sem duplicar:** o formulário envia todas as caixas marcadas, e o Service compara com o que a conta já tem como direta: só a diferença é gravada. Salvar sem mudar nada responde "Nenhuma alteração" e não grava linha de auditoria.
- **Permissão que a conta já herda:** a concessão é aceita. É um vínculo direto redundante; veja a seção 5.

## 4. Como Revogar uma Permissão Direta

1. Na página de permissões da conta, desmarque a caixa da permissão (pode desmarcar várias).
2. Clique em **Salvar permissões**.
3. A página recarrega com uma mensagem sobre o resultado:
   - **Verde**: o salvamento terminou e a conta não mantém acesso às permissões revogadas.
   - **Amarela**: só o vínculo direto saiu e a conta continua com acesso a alguma permissão revogada. A mensagem lista quais e de que grupo. Siga a seção 5.

**Regras da revogação:**

- **Só o vínculo direto:** revogar nunca retira grupo da conta nem mexe na matriz.
- **Só o que a conta tem:** quem recebe a permissão apenas de um grupo não tem vínculo direto para revogar; a caixa dela já vem desmarcada.
- **Vínculo fora do catálogo:** se o catálogo deixar de listar uma permissão que a conta ainda tem como direta, o vínculo continua valendo (o `can()` o reconhece). Ele aparece marcado na categoria "Fora do catálogo" e é revogado desmarcando e salvando.

## 5. Quando o Acesso Persiste por Herança

Revogar a permissão direta remove **somente o vínculo direto**. Se um grupo da conta concede a mesma permissão, o acesso efetivo continua, e a tela avisa com a mensagem amarela e com a linha **Herdada de**, que lista os grupos.

**Exemplo (dados fictícios):** a conta `conta.teste` está no grupo `admin` e recebeu `contas.consultar` como direta. Ao desmarcar a caixa e salvar, a mensagem amarela diz que a conta continua com acesso a `contas.consultar (Administrador de contas)`, e a permissão passa a mostrar:

| Caixa | O que aparece na linha |
|---|---|
| Desmarcada | A linha "Herdada de Administrador de contas" e o ícone verde de acesso efetivo |

Para realmente retirar o acesso, escolha o caminho que corresponde ao que se quer. Do menor para o maior impacto:

1. **Tirar a conta do grupo que concede.** Afeta só essa conta, mas retira dela **tudo** o que o grupo concede, não apenas a permissão em questão. Esta tela não altera grupos. No ambiente local, o comando do Shield faz a operação:

   ```bash
   php spark shield:user removegroup -n nome.usuario -g admin
   ```

   Em homologação e produção, a remoção do grupo é feita por responsável autorizado no servidor do ambiente, nunca a partir da máquina local (`docs/autenticacao/primeiro-administrador.md`, seção "Restrições").
2. **Alterar a matriz do grupo.** Afeta **todas** as contas do grupo. É mudança de código em `$matrix` (`app/Config/AuthGroups.php`): exige issue própria, revisão de segurança (manual 11.10) e a marcação "Altera permissões ou regras de acesso" na descrição da entrega (`docs/padroes/permissoes.md`, passo 8). Não use este caminho para resolver o caso de uma conta só.
3. **Manter o acesso.** Se a conta exerce o papel do grupo, o acesso herdado é legítimo, e a revogação apenas limpou um vínculo direto redundante.

Depois de qualquer um dos caminhos, reabra a página de permissões da conta e confira o ícone de **acesso efetivo**: ele é calculado com o `can()` do Shield, a mesma regra que o filtro de rota aplica.

## 6. Garantias da Operação

- **Persiste só vínculos diretos:** usa `User::addPermission()` e `User::removePermission()` do Shield. Não usa `syncGroups()`, `syncPermissions()` nem grava `AuthGroups.matrix` pelo `setting()`.
- **Salvar é tudo ou nada:** `PermissaoDiretaService::sincronizar()` compara as caixas marcadas com o que a conta tem e aplica as diferenças, concedendo as que faltam e revogando as que sobraram. Cada mudança grava o vínculo e a auditoria como em `conceder()` e `revogar()`, mas tudo acontece numa única transação aberta por `sincronizar()`, sem transações internas: se qualquer mudança falhar, nenhuma é gravada.
- **Seleção validada:** o campo `permissoes[]` precisa ser uma lista de textos de até 100 caracteres; qualquer outro formato é recusado ("A seleção de permissões é inválida.") sem gravar nada. Cada identificador é conferido contra o catálogo no Service.
- **Autor sempre da sessão:** o operador vem de `auth()->id()` no Controller. O formulário não tem campo de autor, e campos extras enviados à mão são ignorados.
- **Vínculo e auditoria na mesma transação:** se a auditoria falhar, o vínculo também não é gravado, e o operador vê "Não foi possível concluir a operação" sem detalhe técnico. O detalhe vai para o log, sem dados pessoais.
- **Dentro de uma transação maior:** se o chamador já abriu uma transação, a operação participa dela. O CodeIgniter só efetiva a transação mais externa, então o `commit` e o `rollback` de verdade ficam com quem a abriu, que decide pelo `sucesso` do retorno.
- **Efeito real:** depois de gravar, o Service **recarrega a conta do banco** antes de calcular o resultado, porque o Shield guarda grupos e permissões em cache na própria entidade. A mensagem e a tela mostram o que o `can()` decide.
- **Herança calculada como o Shield calcula:** os grupos que concedem a permissão são achados com o mesmo `PermissionMatcher` do `can()`, então curingas na matriz (como `contas.*`) também aparecem.

## 7. Auditoria

Toda concessão e revogação grava uma linha em `auditoria_permissoes` (migration `CriarTabelaAuditoriaPermissoes`; aplique com `php spark migrate --all`). Um salvamento com várias mudanças grava uma linha para cada uma:

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
- Operações recusadas (permissão inválida, operador sem permissão, seleção inválida) e salvamentos sem mudança não geram linha: nada foi alterado.

## 8. Como Testar

### 8.1 Teste Automatizado

- `tests/database/PermissaoDiretaServiceTest.php`: regras do Service (concessão, revogação, salvamento em lote tudo ou nada, herança preservada, permissão fora do catálogo, operador sem permissão, duplicidade, auditoria e rollback quando ela falha, agrupamento por categoria e a igualdade entre o acesso "efetivo" e o `can()` do Shield).
- `tests/feature/AcessoPermissoesTest.php`: teste de URL direta (`rotas-protegidas.md`, seção 8), a tela com as caixas e o efeito real na rota. O teste `testContaGanhaEPerdeAcessoARotaConformeAsAlteracoes` mostra uma conta sem acesso a `/admin/acessos`, ganhando acesso depois que a caixa de `acessos.administrar` é marcada e salva, e perdendo de novo depois de desmarcada. O teste `testDesmarcarNaoTiraOAcessoQueVemDoGrupo` prova que o acesso continua quando vem do grupo.

Ao testar uma rota depois de uma alteração, **recarregue a conta do banco** antes do `actingAs()` (`Tests\Support\Traits\CriaContasTrait::recarregar()`): reaproveitar a entidade antiga usa o cache de permissões de antes da mudança e esconde o efeito.

### 8.2 Roteiro Manual no Navegador

1. Suba o MySQL do XAMPP, rode `php spark migrate --all` e `php spark serve`. Abra `http://localhost:8080/index.php/login`.
2. Prepare duas contas com dados fictícios (manual 11.9), pelo CLI do Shield: uma no grupo `superadmin` e outra no grupo `user` (por exemplo, `conta.teste`). A senha é pedida no terminal.
3. Entre como `superadmin`, abra **Administração > Acessos > conta.teste > Permissões**, marque `acessos.administrar` e clique em **Salvar permissões**. Confira a caixa marcada e o ícone verde de acesso efetivo.
4. Em **janela anônima**, entre como `conta.teste`: o item "Acessos" aparece no menu e `/admin/acessos` abre.
5. Como `superadmin`, desmarque a permissão e salve. Na janela anônima, atualize: `/admin/acessos` redireciona para `/` com a mensagem de erro.
6. Caso de herança: coloque `conta.teste` no grupo `admin` (`php spark shield:user addgroup -n conta.teste -g admin`), marque `contas.consultar` e salve; depois desmarque e salve. A mensagem é amarela, e a permissão mostra a caixa desmarcada, a linha "Herdada de Administrador de contas" e o ícone verde de acesso efetivo.
7. Teste os atalhos: "marcar todas" marca as permissões do catálogo, menos a crítica; "desmarcar" limpa as do catálogo. Nenhum dos dois mexe nas caixas de "Fora do catálogo". Saia da página sem salvar e confira que nada mudou.
8. Marque duas permissões e desmarque uma, e salve de uma vez: a mensagem resume o que mudou (por exemplo, "2 concedidas e 1 revogada") e a auditoria ganha uma linha para cada mudança.
9. Confira a trilha de auditoria com a consulta da seção 7.
10. Descreva na entrega quais contas e quais URLs foram testadas.

## 9. Referências

- `docs/padroes/permissoes.md`: catálogo de permissões, convenção de nomes e como acrescentar uma permissão.
- `docs/autorizacao/rotas-protegidas.md`: filtros de rota e teste de URL direta.
- `docs/padroes/seguranca.md` (regra 4; manual 11.3 e 11.9) e `docs/padroes/banco-de-dados.md` (auditoria, manual 8.4).
- [CodeIgniter Shield, autorização](https://shield.codeigniter.com/latest/quick_start_guide/using_authorization/)
