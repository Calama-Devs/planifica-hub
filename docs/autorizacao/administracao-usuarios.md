# Administração de usuários

O módulo administrativo consulta e cria contas pelo CodeIgniter Shield. O
cadastro público continua desativado em `Config\Auth::$allowRegistration`.

## Rotas e permissões

| Método | Rota | Permissão |
|---|---|---|
| GET | `/admin/usuarios` | `contas.consultar` |
| GET | `/admin/usuarios/novo` | `contas.criar` |
| POST | `/admin/usuarios/criar` | `contas.criar` |

Todas as rotas também exigem sessão. O filtro `auditoria-contas` registra na
tabela `auditoria_usuarios` as tentativas sem a permissão antes de o filtro
`permission` do Shield barrar a requisição. O Service repete a autorização nas
operações.

## Grupos concedíveis

O cadastro recebe um único grupo. O Service compara cada permissão do grupo
com as permissões efetivas do operador, usando o catálogo e a matriz ativos do
Shield. A validação é repetida no servidor; valores enviados manualmente que
não estejam entre os grupos concedíveis são recusados e auditados.

O grupo `user` é o padrão, mas o formulário envia explicitamente o grupo
selecionado. Nenhuma permissão direta é atribuída durante o cadastro.

## Persistência e auditoria

A verificação de unicidade de nome de usuário e e-mail é realizada por métodos
semânticos de `UsuarioModel` (`nomeJaExiste()` e `emailJaExiste()`), garantindo
normalização (`trim()` e `strtolower()`) e validação inclusive contra contas
apagadas logicamente (`withDeleted()`), sem consultas diretas de banco no Service.

O usuário é persistido pelo `UserModel`; e-mail e senha são criados pela API
`User::createEmailIdentity()`, que usa os serviços de identidade e senha do
Shield. Conta, identidade e vínculo de grupo são gravados na mesma transação.
Antes do commit, uma verificação defensiva assegura que a identidade de e-mail e
o grupo foram efetivamente gravados na base; qualquer falha técnica aciona
`transRollback()` e grava evento de falha.

Eventos de sucesso, falha de persistência, recusa por autorização ou grupo
inválido são registrados em `auditoria_usuarios`, com operador, usuário criado
quando houver, operação, resultado, grupo e `created_at`. O evento de sucesso
é gravado na mesma transação da conta, somente após todas as etapas obrigatórias
serem confirmadas. A tabela não armazena nome, e-mail, senha, hash ou token. As tentativas barradas nos filtros de rota são registradas por `AuditoriaContasFilter`.
O `UsuarioService` registra recusas por regras de negócio (tentativa de conceder
grupo superior não permitido) e recusas em chamadas diretas. O `UsuarioController`
registra a recusa quando o campo `grupo` é adulterado, ausente ou inválido no formulário,
mantendo falhas comuns de preenchimento de dados cadastrais (nome, e-mail, senha)
sem auditoria e sem gerar registros duplicados.

## Roteiro de teste manual

1. Entre com uma conta `admin` e abra `/admin/usuarios`; confirme a listagem e
   o link de cadastro.
2. Pesquise por um trecho do nome e depois por um trecho do e-mail. Navegue para
   outra página e confirme que o filtro continua na URL e no campo.
3. Pesquise um valor sem correspondência e confirme a mensagem de estado vazio.
4. Cadastre uma conta com grupo `user`; tente repetir o e-mail e confirme o
   erro próximo ao campo, sem criar outra conta.
5. Envie manualmente `grupo=superadmin` como `admin`; confirme que o cadastro é
   recusado e que não há novo vínculo em `auth_groups_users`.
6. Entre como `user` e tente abrir diretamente `/admin/usuarios` e enviar um
   POST para `/admin/usuarios/criar`; confirme o bloqueio pelo Shield e confira
   o evento de recusa em `auditoria_usuarios`.
7. Entre como `superadmin`; confirme que grupos compatíveis com suas
   permissões aparecem e que a conta criada possui apenas o grupo escolhido.
8. Confirme que `/register` continua indisponível e que formulários inválidos
   preservam os campos seguros, mas nunca repopulam a senha.

Os testes de formulário devem usar dados fictícios. Consulte também
`docs/autorizacao/rotas-protegidas.md` para os cenários gerais de acesso direto.
