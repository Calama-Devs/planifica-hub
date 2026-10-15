---
name: revisar-padroes
description: Revisa as alterações da branch atual (ou os arquivos indicados) contra os padrões do Calama Devs e aponta o que está fora do manual, com severidade e correção sugerida. Use quando o estudante pedir revisão, "ver se está no padrão" ou antes de solicitar a integração no DevCore.
argument-hint: "[arquivos ou branch de destino, opcional]"
---

Revise a alteração seguindo `docs/padroes/revisao-de-codigo.md`.

1. Escopo: se houver argumentos ($ARGUMENTS), revise esses arquivos ou compare com essa branch. Sem argumentos, use `git diff` contra a branch de homologação do projeto (veja "Específico deste projeto" no `AGENTS.md`; se não estiver preenchido, pergunte ao estudante) e inclua alterações não commitadas (`git status`).
2. Leia os arquivos alterados inteiros e os padrões de `docs/padroes/` das áreas envolvidas.
3. Rode, se disponíveis: `vendor/bin/phpcs`, `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` e `vendor/bin/phpunit`. No Windows, use os equivalentes em `.\vendor\bin\`.
4. Responda no formato de `docs/padroes/revisao-de-codigo.md`: resumo, aderência, achados numerados com severidade (Crítico, Alto, Médio, Baixo, Sugestão), arquivo e linha, por que importa (com item do manual) e como corrigir, pontos positivos e o que falta do checklist 16.2.
5. Não altere arquivos durante a revisão. Ao final, pergunte se o estudante quer ajuda para corrigir algum achado.
