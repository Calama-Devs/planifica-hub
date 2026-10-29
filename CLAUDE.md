@AGENTS.md

## Claude Code

- As instruções do time estão no `AGENTS.md`, importado acima; os padrões detalhados ficam em `docs/padroes/`. Leia o arquivo de padrão da área antes de editar.
- Consulte `docs/estrutura-inicial.md` para o mapa de diretórios, namespaces e política do template; para telas e componentes, siga `docs/padroes/layout-e-dashboard.md`.
- Para revisar uma alteração, use a skill `/revisar-padroes`; para fechar a tarefa, `/preparar-entrega` (ambas em `.claude/skills/`).
- Não leia nem exiba o conteúdo de `.env`; consulte `.env.example`.
- Faça commit e `git push` só quando o estudante pedir, e o push só para a branch da issue. `composer install` e as verificações locais podem ser executados normalmente. A proibição de comandos contra bancos ou servidores de homologação e produção em `AGENTS.md` permanece integralmente aplicável; uma confirmação do estudante não autoriza exceção.
