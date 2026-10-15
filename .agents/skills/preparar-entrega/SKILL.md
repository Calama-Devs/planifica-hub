---
name: preparar-entrega
description: Prepara a entrega de uma issue para o DevCore. Confere o checklist do manual (item 16.2), roda as verificações, sugere mensagens de commit e redige a descrição da entrega para publicar na issue. Use quando o estudante disser que terminou a tarefa ou que vai solicitar a integração.
argument-hint: "[código da issue, ex.: DC-142]"
---

Prepare a entrega da issue informada pelo estudante (código no DevCore, ex.: DC-142) seguindo `docs/padroes/validacao-e-testes.md` e `docs/padroes/git-e-entrega.md`.

1. Confirme que o trabalho está na branch da issue criada pelo DevCore (`git branch --show-current`, padrão `prefixo/CÓDIGO-titulo`). Se não estiver, avise antes de qualquer outra coisa. Verifique também se a branch está atualizada em relação à branch de homologação (`git fetch origin` e `git log HEAD..origin/<homologacao>`); se houver commits novos, oriente o estudante a atualizar e resolver conflitos antes de solicitar a integração (manual 12.8.1).
2. Liste o que mudou (`git status` e `git diff` contra a branch de homologação) e verifique se há algo que não deve ir para o repositório: `.env`, dumps, logs, `vendor/`, código de depuração, credenciais.
3. Rode `vendor/bin/phpcs`, `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` e `vendor/bin/phpunit` (equivalentes em `.\vendor\bin\` no Windows) e relate o resultado.
4. Percorra o checklist do item 16.2 marcando o que foi possível confirmar pelo código e o que depende de teste manual do estudante (deixe esses itens explícitos).
5. Se houver alterações sem commit, sugira commits no padrão `tipo: descricao em minusculas sem acentos`, separados por responsabilidade. Faça commit e push só se o estudante pedir, e o push só para a branch da issue. Nunca use `--force`.
6. Redija a descrição da entrega no modelo de `docs/padroes/git-e-entrega.md`, com Resumo, Tipo de alteração, Principais alterações, Impacto, Checklist e, quando couber, Como testar e Observações para o revisor (detalhando migrations).
7. Lembre o estudante de publicar a descrição como comentário na issue e então clicar em **Solicitar integração** no DevCore. Nada é feito diretamente no GitHub.
