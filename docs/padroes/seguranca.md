# Segurança

Base: Manual Calama Devs, capítulo 11 e item 8.7. Em caso de dúvida, o manual prevalece. Segurança é requisito desde o início da funcionalidade, não ajuste final.

## Regras inegociáveis (falha aqui bloqueia o PR)

1. **SQL Injection:** nunca concatenar dado externo em SQL. Query Builder ou consulta parametrizada.
2. **XSS:** todo dado dinâmico exibido em HTML passa por `esc()`. No JavaScript, `.text()` em vez de `.html()` para dados do servidor.
3. **CSRF:** filtro `csrf` ativo globalmente em `app/Config/Filters.php`; todo formulário com `<?= csrf_field() ?>`; todo AJAX que altera dados envia o token.
4. **Autorização no back-end:** toda rota e operação sensível verifica permissão no servidor (Filter e/ou Service). Esconder menu ou botão não é proteção. Testar acesso direto pela URL.
5. **Senhas:** só `password_hash($senha, PASSWORD_DEFAULT)` e `password_verify()`. Nunca armazenar, registrar em log ou enviar senha legível.
6. **Segredos:** credenciais, tokens e chaves só no `.env`. Nunca no código, em commits, em prints, em issues ou em conversas com agentes de IA.
7. **Dados reais:** proibido versionar dumps ou exportações com dados pessoais; desenvolvimento e testes usam dados fictícios ou anonimizados.

## Validação de entrada (manual 11.4)

- Validar tudo o que vem de fora: formulários, parâmetros de rota e de URL, GET e POST, uploads, APIs, webhooks, planilhas importadas, integrações.
- Verificar: obrigatoriedade, tamanho, formato, tipo, faixa de valores, existência de registros relacionados e regras de negócio.
- Preferir lista de valores permitidos (whitelist) a lista de bloqueio.
- Usar a validação nativa do CI4 (`$this->validate()`, `Config\Validation`, regras na Model).
- Identificador numérico de rota é validado como inteiro e o registro é conferido como pertencente ao usuário ou ao escopo dele.

## Autenticação e sessão (manual 11.2)

- Mensagem de login genérica (`Usuário ou senha inválidos.`), sem revelar se o usuário existe.
- Recuperação de senha por token temporário e de uso único; nunca "recuperar" a senha antiga.
- Sessão com expiração compatível com a criticidade e logout que invalida a sessão.

```php
if (! password_verify($senhaInformada, $usuario['senha_hash'])) {
    return redirect()->back()->with('error', 'Usuário ou senha inválidos.');
}
```

## Permissões (manual 11.3)

- Modelo de perfis e permissões, com permissões independentes dos perfis (`usuarios.visualizar`, `usuarios.editar`, `relatorios.visualizar`).
- Princípio do menor privilégio para usuários, contas de serviço, banco de dados e integrações.
- Registrar em auditoria as operações administrativas e sensíveis.

## Upload de arquivos (manual 11.7)

- Lista explícita de tipos permitidos por funcionalidade, validando extensão **e** MIME (`ext_in`, `mime_in`, `max_size`, `is_image`).
- Tamanho máximo definido.
- Renomear sempre (`$arquivo->getRandomName()`), nunca usar o nome original para armazenar.
- Armazenar fora de `public/` (ex.: `writable/uploads/`) e servir por rota que confere permissão.
- Impedir execução de arquivos enviados.

```php
$regras = ['documento' => 'uploaded[documento]|max_size[documento,2048]|ext_in[documento,pdf]|mime_in[documento,application/pdf]'];
```

## Ambientes e credenciais (manual 11.8)

- Cada ambiente (desenvolvimento, homologação, produção) tem credenciais próprias.
- Estudantes não têm acesso direto à produção sem autorização formal.
- `.env` real nunca é compartilhado em grupos, e-mail ou repositório; `.env.example` sem valores reais.
- Credencial exposta é trocada imediatamente.

## LGPD e dados pessoais (manual 11.9)

- Coletar só o estritamente necessário.
- Exibir CPF, e-mail, telefone, endereço e matrícula apenas quando a funcionalidade exige.
- Cuidado com telas administrativas, relatórios, exportações, logs, prints e bases de teste.
- Dados pessoais fora dos logs; senhas e tokens nunca.

## Erros

- Em produção, `CI_ENVIRONMENT = production` e nenhum detalhe técnico na tela.
- Exceções vão para o log com contexto; o usuário vê mensagem genérica.

## Revisão de segurança obrigatória (manual 11.10)

Funcionalidades com autenticação, permissões, dados pessoais, upload, integrações externas, APIs, processos administrativos ou regras institucionais passam por revisão de segurança antes da produção, feita por alguém além do autor. Verificar todos os itens desta página.
