# Primeiro acesso administrativo

Procedimento para criar a conta administrativa inicial em cada ambiente do Planifica Hub. Deve ser executado apenas por um responsável autorizado do time.

---

## Pré-requisitos

- PHP e Composer instalados e funcionando.
- Banco de dados do ambiente configurado no `.env` local.
- Migrations aplicadas (`php spark migrate --all`).
- Nenhuma credencial real deve ser registrada neste documento nem em commits.

---

## 1. Verificar se já existe conta administrativa

Antes de criar uma conta, liste os usuários existentes:

```bash
php spark shield:user list
```

Se o e-mail que será usado já aparecer na lista, **não execute o passo 2**. Siga diretamente para a [verificação de grupo](#3-verificar-o-grupo-administrativo) e, se necessário, para a [recuperação de acesso](#4-recuperação-de-acesso).

---

## 2. Criar a conta administrativa

Execute o comando abaixo, substituindo `nome-de-usuario` e `email@exemplo.com` pelos dados reais (não os registre aqui):

```bash
php spark shield:user create -n nome-de-usuario -e email@exemplo.com -g superadmin
```

O comando pedirá a senha interativamente — ela **não fica registrada** no histórico de comandos nem no código. O grupo `superadmin` é o único com a permissão `acessos.administrar`, necessária para gerenciar outros acessos.

Se preferir criar a conta sem grupo e atribuí-lo depois:

```bash
php spark shield:user create -n nome-de-usuario -e email@exemplo.com
php spark shield:user addgroup -e email@exemplo.com -g superadmin
```

---

## 3. Verificar o grupo administrativo

Após criar a conta, confirme que ela existe e possui o grupo correto:

```bash
php spark shield:user list
```

Localize o usuário na lista e confirme que o grupo `superadmin` aparece associado a ele.

Em seguida, acesse o sistema pelo navegador com as credenciais criadas e verifique que o login funciona normalmente.

---

## 4. Recuperação de acesso

Se a senha for perdida, redefina-a pelo comando abaixo (solicitará a nova senha interativamente):

```bash
php spark shield:user password -e email@exemplo.com
```

Se o grupo administrativo tiver sido removido por engano:

```bash
php spark shield:user addgroup -e email@exemplo.com -g superadmin
```

---

## 5. Situação por ambiente

| Ambiente | Status | Responsável | Observação |
|---|---|---|---|
| Desenvolvimento | A executar | Desenvolvedor local | Cada desenvolvedor executa em seu banco local |
| Homologação | A executar | Responsável autorizado | Executar após próxima subida para homolog |
| Produção | A executar | Responsável autorizado | Executar somente no go-live |

Após a execução em cada ambiente, atualize a coluna **Status** para `Executado` e registre a data, sem incluir credenciais.

---

## Restrições

- Nunca registrar senhas, tokens ou dados pessoais neste documento, em commits ou em seeds.
- Nunca executar este procedimento nos bancos de homologação ou produção a partir do ambiente local: acesse o servidor do ambiente correspondente.
- O cadastro público de usuários permanece desativado; este é o único caminho para criar a primeira conta.
