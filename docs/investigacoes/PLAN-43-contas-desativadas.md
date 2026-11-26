# PLAN-43 — Investigação de contas desativadas

## Limitação do relato original

A issue informa que o caso foi relatado em reunião, mas não identifica o sistema, ambiente, versão/commit, estado usado para desativar a conta ou mecanismo de autenticação. Portanto, não foi possível confirmar se o episódio original ocorreu no PlanIFica Hub. Nenhum dado ou acesso de produção foi usado.

## Reprodução local

- Ambiente: testes locais do PlanIFica Hub.
- Base examinada antes da correção: commit `b480162`.
- PHP: 8.2.12.
- CodeIgniter: 4.7.4.
- Shield: 1.4.1.
- Conta: fictícia, criada no teste e desativada pelo método nativo `User::deactivate()` do Shield.

## Comportamento encontrado

No Shield 1.4.1, `active` representa a ativação após cadastro. `User::isActivated()` considera o usuário ativado quando não há um ativador de cadastro configurado. Como `Config\Auth::$actions['register']` está `null`, a verificação padrão do filtro `session` não bloqueava `active = 0`. O autenticador de sessão também não conferia esse campo ao validar a senha. Assim, a conta fictícia desativada conseguia iniciar sessão e uma sessão existente continuava alcançando rotas protegidas.

O login por link mágico também usa `Session::loginById()`, que não rejeitava uma conta com `active = 0`. A opção de lembrar sessão está habilitada; esse caminho restaura a sessão pelo autenticador do Shield e agora passa pela checagem de conta ativa antes de acessar rotas da aplicação. Autenticadores de token e HMAC estão declarados na configuração, mas não há rotas da aplicação usando seus filtros.

## Correção aplicada

- O autenticador de sessão configurado no Shield rejeita contas inativas tanto na validação de credenciais quanto no login por identificador, usado pelo link mágico.
- O filtro das rotas protegidas mantém o filtro de sessão do Shield e consulta novamente o estado da conta no banco em cada requisição. Se a conta estiver desativada, encerra a sessão e bloqueia o acesso direto.
- A rota AJAX de alteração de senha mantém seu tratamento de erro `403`; ela já verifica a situação da conta no Controller antes de executar a operação.
- Os testes de contas fictícias que representam usuários ativos agora definem explicitamente `active = true`.

## Evidências de regressão

`tests/feature/AutenticacaoTest.php` cobre login por senha de conta desativada, bloqueio de sessão criada antes da desativação e recusa do link mágico. A suíte completa verifica também que contas ativas e rotas protegidas existentes continuam funcionando.

O relato da reunião permanece sem confirmação quanto ao sistema e ambiente originais. A conclusão desta entrega se limita ao comportamento reproduzido localmente no PlanIFica Hub.
