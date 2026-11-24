# Confirmação de Senha em Operações Administrativas

> **Base normativa:** Manual Calama Devs, itens 11.2 (autenticação), 11.3 (permissões) e 11.10 (revisão de segurança). Complementa `docs/padroes/seguranca.md` e `docs/padroes/permissoes.md`. Em caso de dúvida, o manual prevalece.

## 1. Visão Geral e Objetivo

Operações que afetam a conta de **outra pessoa** (editar dados, desativar ou reativar, alterar grupos e permissões, pedir redefinição de senha) são sensíveis: estar logado e ter a permissão não basta. Antes de a mudança acontecer, o operador confirma a **senha da própria conta**, e não a da conta afetada.

A conferência fica em um único lugar, o `ConfirmacaoAdministrativaService` (`app/Services/`). Nenhuma operação compara senha por conta própria: ela chama o serviço, que usa o serviço de senhas do Shield e aplica o limite de tentativas. Este guia descreve o contrato do serviço, o contrato HTTP com a modal de confirmação e o passo a passo para integrar uma nova operação.

### 1.1 Regras Fundamentais

- **O operador vem só da sessão do Shield.** Nenhum dado da requisição (`operador_id`, `user_id`) o informa ou o substitui.
- **A ação e a conta afetada vêm do código do servidor**, que as declara ao chamar o serviço. O navegador nunca informa a ação; a conta vem da rota, já validada.
- **A confirmação vale só para a requisição em curso.** O serviço não guarda nada entre chamadas: uma confirmação anterior não autoriza outra operação, outra conta nem outra requisição.
- **Senha correta não concede permissão.** A permissão da ação é conferida antes de a senha ser verificada.
- **O limite de tentativas é do operador**, igual para todas as ações, contas e rotas.
- **A senha nunca é guardada.** Não vai para sessão, banco, cache, auditoria nem log, não trafega por URL e não volta na resposta.

## 2. Como o Serviço Decide

O serviço faz as verificações abaixo, nesta ordem. A primeira que falhar devolve a recusa e **nada pode ser alterado**.

| # | Verificação | Código da recusa | Status HTTP |
|---|---|---|---|
| 1 | A ação está cadastrada em `Config\ConfirmacaoAdministrativa::$acoes` | `CONFIRMACAO_OPERACAO_INDISPONIVEL` | 501 |
| 2 | A conta afetada é um identificador válido (maior que zero) | `CONFIRMACAO_CONTEXTO_INVALIDO` | 422 |
| 3 | Há sessão autenticada no Shield | `AUTENTICACAO_NECESSARIA` | 401 |
| 4 | O operador tem a permissão da ação (grupos ou permissão direta) | `CONFIRMACAO_SEM_PERMISSAO` | 403 |
| 5 | A senha foi informada (ausente não consome tentativa) | `CONFIRMACAO_INVALIDA` | 422 |
| 6 | O operador não excedeu o limite de tentativas | `CONFIRMACAO_LIMITE_EXCEDIDO` | 429 |
| 7 | A senha confere com a do próprio operador | `CONFIRMACAO_INVALIDA` | 422 |
| – | Falha inesperada na confirmação (vai para o log, sem detalhes ao usuário) | `CONFIRMACAO_ERRO` | 500 |

A permissão é conferida antes da senha para que quem não tem acesso à ação não consiga usar o endpoint como oráculo de senha.

`CONFIRMACAO_OPERACAO_INDISPONIVEL` (501) só ocorre quando o código do servidor cita uma ação que não está no catálogo. Como o navegador nunca informa a ação, isso é falha de configuração do servidor e não erro de quem chamou, por isso o status não é 4xx.

## 3. Contrato do Serviço

```php
$operador = service('confirmacaoAdministrativaService')->confirmar('desativar_conta', $contaId, $senhaAdmin);

if (! $operador instanceof User) {
    return $operador; // recusa estruturada: nada foi alterado
}

// A partir daqui: sessão válida, permissão da ação, senha do operador conferida e limite respeitado.
```

- **Retorno em caso de sucesso:** a entidade `User` do operador da sessão. Use-a na operação (regras de hierarquia, auditoria), em vez de buscar o operador por um identificador vindo de fora.
- **Retorno em caso de recusa:** o array padronizado (manual 6.10.2) com `sucesso => false`, `tipo`, `codigo` e `mensagem`; no limite de tentativas, também `espera_segundos`.
- **Chame na mesma requisição que executa a operação**, antes de qualquer escrita, e não guarde o resultado para usar depois.
- **Conferido o operador, as regras próprias continuam valendo.** A confirmação não substitui a validação dos dados, a autorização no Service e no Filter, a proteção CSRF, as regras da operação (como a do último administrador) nem a [auditoria centralizada](../auditoria.md).

## 4. Contrato HTTP com a Modal

A modal de confirmação da área Gerenciamento de Usuários envia a senha **junto com a operação**, em um único POST. Não existe endpoint que apenas "valida a senha": ele criaria uma autorização reutilizável.

| Item | Regra |
|---|---|
| Campo da senha | `senha_admin`, **no corpo** do POST. A URL (query string) nunca é lida |
| CSRF | Obrigatório. O jQuery envia o cabeçalho `X-CSRF-TOKEN` a partir da meta tag; o token é regenerado a cada envio |
| Resposta | JSON com `sucesso`, `tipo`, `codigo`, `mensagem`, o novo token em `csrf` (`nome` e `hash`) e o mesmo `hash` em `csrf_hash` |
| Cache | `Cache-Control: no-store` em todas as respostas |
| Status | Sucesso é 200. Toda recusa sai com 4xx ou 5xx, nunca com 2xx ou 3xx: a modal não confia só no status HTTP |
| Limite excedido | HTTP 429 com `espera_segundos` no JSON e o cabeçalho `Retry-After` |
| A senha | Nunca volta na resposta |

```json
{
  "sucesso": false,
  "tipo": "permissao",
  "codigo": "CONFIRMACAO_INVALIDA",
  "mensagem": "Senha incorreta. Confirme a operação com a senha da sua conta.",
  "csrf": { "nome": "csrf_test_name", "hash": "…" },
  "csrf_hash": "…"
}
```

**Comportamento da modal** (`public/assets/js/acesso/index.js`):

- A mensagem do servidor aparece no alerta da própria modal, sempre com `.text()`. Sucesso usa `alert-success`; recusa usa `alert-danger`. Só `sucesso === true` no JSON conclui a operação: uma resposta 2xx sem isso é tratada como recusa.
- O campo de senha é limpo assim que a tentativa é enviada e quando a modal fecha.
- **Cancelar ou fechar a modal não envia nada ao servidor**, então nenhuma alteração é persistida. Durante o envio, a modal não fecha.
- Sessão revogada (JSON com `redirecionamento`), HTTP 401 ou resposta que não é JSON levam ao login. Quando o token CSRF some junto com a sessão, o servidor responde 403 sem JSON e a modal pede para recarregar a página.
- Cada tipo de ação da modal aponta para o endpoint da operação em `montarRequisicao()`. Tipos sem endpoint no servidor mostram "Esta operação ainda não está disponível" e **não enviam a senha**.

| Tipo na modal | Endpoint | Ação cadastrada |
|---|---|---|
| `atualizar` | `POST /admin/contas/{id}` | `editar_conta` |
| `desativar` | `POST /admin/contas/{id}/desativar` | `desativar_conta` |
| `reativar` | `POST /admin/contas/{id}/reativar` | `reativar_conta` |

## 5. Limite de Tentativas

O limite usa o `Throttler` do CodeIgniter (cache), com uma chave por operador (`confirmacao_administrativa_<id do operador>`). Como a chave não inclui a ação, a conta nem a rota, trocar de rota, de conta ou de campo no formulário não reinicia a contagem.

- **Parâmetros** em `Config\ConfirmacaoAdministrativa`: `$maxTentativas` (5) e `$janelaSegundos` (900). Com os valores padrão, o operador tem 5 senhas incorretas; depois, uma nova tentativa é liberada a cada 3 minutos.
- A tentativa é contada **antes** de a senha ser conferida (a conferência é a etapa lenta), o que reduz a margem para rajadas de requisições simultâneas. Com o limite excedido, nem a senha correta libera a operação.
- Uma confirmação bem-sucedida **zera** o contador do operador.
- O contador guarda apenas números e o instante da última tentativa, nunca a senha. Cada recusa por senha incorreta ou por excesso de tentativas gera um registro técnico (`warning`) com o identificador do operador, a ação e a conta, sem dados pessoais.
- **Limites da proteção:** o `Throttler` lê e grava o cache sem atomicidade, então o limite é uma barreira contra tentativas repetidas, não uma garantia exata contra rajadas simultâneas. Ele também depende de o cache funcionar: se o CodeIgniter cair no cache reserva (`dummy`, definido em `Config\Cache::$backupHandler`), o contador deixa de existir. Confira o cache nos ambientes de homologação e produção.

## 6. Dados Sensíveis

- **Rastros de exceção:** todo método que recebe a senha como **parâmetro** leva `#[\SensitiveParameter]`, inclusive os privados. O rastro de uma exceção registra os argumentos de cada chamada da pilha; sem o atributo, uma exceção lançada mais abaixo pode gravar a senha em claro no log do CodeIgniter. Isso vale para Controllers, Services e qualquer método da cadeia.
- **Variável local não entra no rastro:** a senha lida no Controller com `getPost()` e guardada em uma variável local não aparece na pilha, porque só os argumentos das chamadas aparecem. O atributo passa a ser obrigatório no instante em que a senha vira parâmetro de um método. O teste `testSenhaNaoApareceNoRastroDeExcecaoLancadaDentroDaOperacao` percorre a cadeia Controller, Service e confirmação.
- **Origem da senha no Controller:** só `$this->request->getPost('senha_admin')`, nunca `getPostGet()` nem `getGet()`.
- **Auditoria:** o evento da operação não inclui a senha. A confirmação em si não grava dados de negócio; cada operação audita a própria escrita.

## 7. Como Integrar uma Nova Operação

1. **Permissão:** declare a permissão da operação em `Config\AuthGroups` e conceda-a aos grupos, conforme `docs/padroes/permissoes.md`.
2. **Ação:** cadastre a ação em `Config\ConfirmacaoAdministrativa::$acoes`, com o identificador em `snake_case` e a permissão exigida.
3. **Rota:** use o POST, com o filtro `permission:` da operação.
4. **Controller:** leia `senha_admin` do corpo (senha ausente ou fora do formato vira `''`, e a confirmação a recusa), chame o Service e devolva com `responderOperacaoAdministrativa()` (trait `App\Controllers\Traits\RespostaAdministrativaTrait`), passando o status das falhas próprias da operação.
5. **Service:** chame `confirmar()` antes de qualquer escrita, use o `User` devolvido e mantenha os controles próprios da operação (validação, hierarquia, transação e auditoria centralizada).
6. **Modal:** acrescente o tipo em `montarRequisicao()` com o endpoint e os campos da operação.
7. **Testes:** cubra, no mínimo, senha correta e incorreta, sem sessão, permissão insuficiente, limite de tentativas e ausência de alteração em cada falha. Isole o cache com `Services::injectMock('cache', new MockCache())` para o contador não vazar entre os testes.

## 8. O Que Não Fazer

- Comparar senha com `password_verify()` ou com `service('passwords')` fora do serviço compartilhado.
- Aceitar o operador, a ação ou a conta afetada do corpo, da URL ou de um campo oculto para decidir quem confirma.
- Criar endpoint que só valida a senha, ou guardar a confirmação (sessão, cookie, cache, banco) para usar depois.
- Declarar a senha como parâmetro sem `#[\SensitiveParameter]`.
- Registrar a senha em log, auditoria, sessão, cache ou resposta.
- Deixar de chamar `confirmar()` porque "a interface já pediu a senha".

## 9. Referências

- `app/Services/ConfirmacaoAdministrativaService.php`, `app/Config/ConfirmacaoAdministrativa.php` e `app/Controllers/Traits/RespostaAdministrativaTrait.php`.
- Testes: `tests/database/ConfirmacaoAdministrativaServiceTest.php` (regras do serviço) e `tests/feature/ConfirmacaoAdministrativaTest.php` (contrato HTTP).
- `docs/padroes/seguranca.md`, `docs/padroes/permissoes.md`, `docs/padroes/arquitetura-backend.md` e [auditoria centralizada](../auditoria.md).
