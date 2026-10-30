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

O comando pedirá a senha interativamente; informe-a no terminal e não a passe como argumento. 
Ainda assim, siga as políticas locais de terminal e auditoria do ambiente. O grupo `superadmin` é o único com a permissão `acessos.administrar`, necessária para gerenciar outros acessos.

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

## 5. Contingência: criação direta via MySQL

Use este caminho **somente** quando o CLI do Shield não estiver disponível (ex.: ambiente sem PHP acessível no servidor). O procedimento via CLI (seção 2) é sempre preferível.

### Pré-requisito

Gere o hash da senha **antes** de abrir o MySQL, usando PHP:

```bash
php -r "echo password_hash('sua-senha', PASSWORD_DEFAULT) . PHP_EOL;"
```

Copie o resultado — ele começa com `$2y$`. Nunca armazene a senha em texto aberto.

### Passos no MySQL

Antes de qualquer `INSERT`, verifique se a conta já existe:

```sql
SELECT id FROM auth_identities WHERE type = 'email_password' AND secret = 'email@exemplo.com';
```

Se retornar resultado, a conta já existe — **não execute os passos abaixo**. Siga direto para a [verificação de grupo](#3-verificar-o-grupo-administrativo).

Se não retornar resultado, execute na ordem:

```sql
-- 1. Criar o usuário
INSERT INTO users (username, active, created_at, updated_at)
VALUES ('nome-de-usuario', 1, NOW(), NOW());

-- 2. Registrar a identidade (e-mail + hash gerado pelo PHP)
INSERT INTO auth_identities (user_id, type, secret, secret2, created_at, updated_at)
VALUES (LAST_INSERT_ID(), 'email_password', 'email@exemplo.com', '<hash-gerado-acima>', NOW(), NOW());
```

Antes de atribuir o grupo, verifique se ele já está vinculado:

```sql
SELECT user_id FROM auth_groups_users
WHERE user_id = (SELECT id FROM users WHERE username = 'nome-de-usuario')
  AND `group` = 'superadmin';
```

Se não retornar resultado:

```sql
-- 3. Atribuir o grupo administrativo
INSERT INTO auth_groups_users (user_id, `group`, created_at)
VALUES ((SELECT id FROM users WHERE username = 'nome-de-usuario'), 'superadmin', NOW());
```

Substitua `nome-de-usuario`, `email@exemplo.com` e `<hash-gerado-acima>` pelos valores reais.

Após inserir, siga a [verificação de grupo](#3-verificar-o-grupo-administrativo) normalmente.

---

## 6. Situação por ambiente

| Ambiente | Status | Responsável | Observação |
|---|---|---|---|
| Desenvolvimento | Executado — 2026-10-29 | Responsável autorizado | Cada desenvolvedor executa em seu banco local |
| Homologação | A executar | Responsável autorizado | Executar após próxima subida para homolog |
| Produção | A executar | Responsável autorizado | Executar somente no go-live |


---

## Restrições

- Nunca registrar senhas, tokens ou dados pessoais neste documento, em commits ou em seeds.
- Nunca executar este procedimento nos bancos de homologação ou produção a partir do ambiente local: acesse o servidor do ambiente correspondente.
- O cadastro público de usuários permanece desativado; este é o único caminho para criar a primeira conta.
