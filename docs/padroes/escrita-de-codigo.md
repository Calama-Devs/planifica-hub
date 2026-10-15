# Escrita de código

Base: Manual Calama Devs, capítulo 10. Em caso de dúvida, o manual prevalece.

## Princípios (manual 10.1)

- Código simples e legível antes de solução "esperta".
- Nomes que expressam a intenção; cada função com uma única responsabilidade.
- Evitar duplicação e arquivos muito grandes.
- Escrever pensando em quem vai manter o código depois (o time troca de membros todo semestre).
- Formatação segue **PSR-12**. Não usar formatação automática da IDE que fuja do PSR-12.

## Idioma

Em **português**: classes, métodos, variáveis, arquivos, views, tabelas, campos, chaves de arrays e migrations.

Ficam em **inglês** apenas os nomes impostos pelo framework, pelas ferramentas ou pelas convenções de sufixo do time (manual 7.3):

- sufixos `Controller`, `Model`, `Filter`, `Service`, `Test`;
- métodos e propriedades do CodeIgniter (`index()`, `$allowedFields`, `$returnType`, `up()`, `down()`);
- campos `created_at`, `updated_at`, `deleted_at`;
- chaves de mensagem flash `success` e `error`;
- prefixo `test` dos métodos de teste.

## Nomenclatura (manual 10.3)

| Elemento | Padrão | Exemplo |
|---|---|---|
| Classe | PascalCase + sufixo do tipo | `UsuarioController`, `CriarSolicitacaoService` |
| Método | camelCase, começa com verbo | `listarUsuarios()`, `buscarPorEmail()` |
| Variável e propriedade | camelCase, descritiva | `$solicitacaoId`, `$totalAlunos` |
| Constante | MAIÚSCULAS com underscore | `LIMITE_TENTATIVAS` |
| Função de Helper | snake_case | `padronizar_cpf()` |
| Arquivo de View | snake_case, nome do método | `buscar_por_email.php` |
| Tabela, campo, chave de array | snake_case | `usuario_permissoes`, `data_nascimento` |

Evitar abreviações obscuras (`$qtd2`, `$aux`, `$tmp`) e nomes genéricos (`Manager`, `Handler`, `Util`, `dados`).

## Tipagem e estrutura

- Declarar tipos de parâmetros e de retorno sempre que possível (`public function calcularTotal(array $produtos): float`).
- Métodos curtos; passou de ~40 linhas, provavelmente precisa ser dividido.
- Retornar cedo (guard clauses) em vez de `if` aninhado em vários níveis.
- Nada de código morto, comentado ou de depuração na entrega: `dd()`, `var_dump()`, `print_r()`, `die()`, `console.log`.

## Comentários (manual 10.4)

- Comentar decisões e regras de negócio que não são óbvias pelo código.
- Não comentar o óbvio nem repetir o que o código já diz.
- Comentário desatualizado é pior que nenhum: ao mudar o código, atualize o comentário.

## Configurações e segredos (manual 10.5)

- Senhas, tokens, chaves de API e credenciais ficam no `.env`, nunca no código.
- O `.env` real nunca vai para o repositório; o `.env.example` traz as variáveis sem valores reais.
- Ler configurações com `env('NOME')` ou pelas classes de `app/Config`.

## Logs (manual 10.6)

- Registrar erros relevantes com contexto (causa, recurso, usuário) via `log_message()`.
- Nunca registrar senhas, tokens ou dados sensíveis.
- Diferenciar log técnico de log de ação do usuário.

## Verificação local antes da entrega (manual 10.2)

```bash
composer install                       # sem --no-dev
vendor/bin/phpcs                       # PSR-12 (usa phpcs.xml.dist do projeto)
vendor/bin/phpcbf                      # corrige automaticamente o que for possível
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
vendor/bin/phpunit
```

No Windows: `.\vendor\bin\phpcs.bat`, `.\vendor\bin\phpcbf.bat`, `php .\vendor\bin\phpstan analyse --no-progress --memory-limit=1G` e `.\vendor\bin\phpunit`.

Sem `phpcs.xml.dist` e `phpstan.neon.dist` no projeto, use `--standard=PSR12 app` no phpcs e `analyse app` no phpstan, e avise a liderança: o Quality Gate do DevCore pula essas verificações quando os arquivos não existem.

## Sinais de alerta para o revisor

- Nomes em inglês fora das exceções, ou abreviações sem sentido.
- Código de depuração esquecido.
- Credencial escrita no código (bloqueante).
- Método longo fazendo várias coisas; duplicação evidente.
