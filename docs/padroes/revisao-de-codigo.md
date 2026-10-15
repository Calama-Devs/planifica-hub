# Revisão de código (procedimento para agentes e revisores)

Base: Manual Calama Devs, capítulos 15 e 17. Use este roteiro quando pedirem para revisar uma alteração, uma branch ou "ver se está no padrão".

## Como revisar

1. Identificar o escopo: `git diff origin/<branch-de-homologacao>...HEAD` (ou os arquivos indicados). Ler também a issue e os critérios de aceite, se o estudante informar.
2. Ler os arquivos alterados inteiros, não só o diff, quando o contexto importar (autorização, fluxo entre camadas).
3. Conferir contra os padrões do repositório (`docs/padroes/*.md`), na ordem: segurança, dados, arquitetura, testes, escrita, front-end, Git.
4. Rodar, se possível: `vendor/bin/phpcs`, `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`, `vendor/bin/phpunit`.
5. Responder no formato abaixo, em português, de forma objetiva e respeitosa. O autor é estudante: explicar o porquê e citar o item do manual.

## Severidades (mesma escala do DevCore)

| Severidade | Quando | Efeito no DevCore |
|---|---|---|
| **Crítico** | Falha de segurança explorável, perda ou exposição de dados, credencial no código | Bloqueia o merge |
| **Alto** | Bug provável, critério de aceite não atendido, alteração de banco sem migration, quebra de funcionalidade existente, validação ausente no back-end, falta de teste obrigatório em Service ou Helper | Bloqueia o merge |
| **Médio** | Problema real que não bloqueia: regra de negócio na camada errada, consulta dentro de laço, erro técnico exibido ao usuário | Deve ser corrigido |
| **Baixo** | Padrão de nomes, código duplicado pequeno, commit fora do padrão | Recomendado corrigir |
| **Sugestão** | Melhoria de estilo ou opinião | Opcional |

## Bloqueiam a aprovação (manual 15.4)

- Não resolver a issue ou não atender aos critérios de aceite.
- Quebrar funcionalidades existentes.
- Faltar informação para validar a solução.
- Alterar o banco sem migration ou justificativa.
- Incluir alterações não relacionadas sem justificativa.
- Ignorar validações necessárias à integridade dos dados.
- Introduzir falha de segurança ou expor dados, credenciais ou informação sensível.
- Incluir `.env`, credenciais, dumps ou arquivos sensíveis.

## Exigem ajuste (manual 15.5)

- Código duplicado.
- Regra de negócio na View (ou no Controller, quando deveria estar num Service).
- Descumprimento dos padrões de escrita ou do padrão visual do template.
- Trechos que deveriam virar métodos, Services, Helpers ou componentes.
- Commits genéricos.
- Descrição da entrega incompleta.

## Formato da resposta

```markdown
**Resumo:** (até 5 frases: o que a alteração faz e a avaliação geral)
**Aderência à issue:** ok | parcial | não

**Achados**
1. [Crítico] app/Controllers/AlunoController.php:42 · SQL concatenado
   Por que importa: permite SQL Injection (manual 11.5).
   Como corrigir: usar Query Builder: `->where('id', $id)`.
2. [Baixo] ...

**Pontos positivos:** (o que está bem feito, para reforçar o aprendizado)
**Antes de solicitar a integração:** (comandos ou itens do checklist 16.2 que faltam)
```

## Postura

- Comentar o código, nunca a pessoa (manual 15.3 e 21.2.3).
- Fundamentar em critério técnico e no manual, não em preferência pessoal.
- Exemplos de tom: "Esse campo precisa ser validado no back-end também, porque a validação em jQuery pode ser contornada." / "Sugiro mover essa regra para um Service, para reutilizar em outras telas."
- Reportar só problemas concretos, observáveis no código. Não inventar contexto.
- Problema antigo em arquivo não alterado só entra se a alteração o agrava.
