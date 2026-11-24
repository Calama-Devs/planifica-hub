# Revisão de código (procedimento para agentes e revisores)

Base: Manual Calama Devs, capítulos 15 e 17. Use este roteiro quando pedirem para revisar uma alteração, uma branch ou "ver se está no padrão".

## Como revisar

1. Identificar o escopo: `git diff origin/<branch-de-homologacao>...HEAD` (ou os arquivos indicados). Ler também a issue e os critérios de aceite, se o estudante informar.
2. Ler os arquivos alterados inteiros, não só o diff, quando o contexto importar (autorização, fluxo entre camadas).
3. Conferir contra os padrões do repositório (`docs/padroes/*.md`), na ordem: segurança, dados, arquitetura, testes, escrita, front-end, Git.
   Em CRUDs novos ou operações existentes alteradas, usar também o roteiro obrigatório abaixo.
4. Rodar, se possível: `vendor/bin/phpcs`, `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`, `vendor/bin/phpunit`.
5. Responder no formato abaixo, em português, de forma objetiva e respeitosa. O autor é estudante: explicar o porquê e citar o item do manual ou o padrão local aplicável.

### Roteiro obrigatório para CRUDs e operações alteradas

Acompanhe o fluxo efetivo da rota até a persistência no Hub ou no módulo integrado. O roteiro complementa os critérios de aceite e as verificações já existentes.

- **Service:** o Controller valida a entrada e delega; o Service concentra as regras de negócio, a escrita pelas Models e a auditoria. Confira se foi reaproveitado um Service adequado, quando disponível.
- **Autenticação e autorização:** confira os filtros por rota e grupo, a permissão efetiva no Shield (catálogo ativo, matriz e concessões individuais) e as checagens de permissão e escopo no Service, quando aplicáveis. Considere requisições diretas, sem passar pela interface, e verifique se há acesso indevido a registros ou elevação de privilégios. Use o [padrão de permissões](permissoes.md).
- **Auditoria:** toda criação, atualização e exclusão no escopo usa o `AuditoriaService` na mesma conexão e transação da escrita. Confira os [campos do evento](../auditoria.md#o-que-registrar), o antes/depois quando aplicável, a ausência de dados sensíveis e o rollback em falhas. Log técnico e colunas de autoria não contam como evento.
- **Testes:** confira a cobertura de sucesso, negativas de autorização e de escopo, auditoria e falhas com rollback, conforme o [padrão de testes](validacao-e-testes.md). Identifique verificações que não puderam ser executadas; não presuma que passaram.

## Severidades (mesma escala do DevCore)

| Severidade | Quando | Efeito no DevCore |
|---|---|---|
| **Crítico** | Falha de segurança explorável, perda ou exposição de dados, credencial no código; quebra de autorização que permite acesso ou ação indevida, elevação de privilégios ou escrita crítica que pode ser confirmada sem a auditoria obrigatória | Bloqueia o merge |
| **Alto** | Bug provável, critério de aceite não atendido, alteração de banco sem migration, quebra de funcionalidade existente, validação ausente no back-end, falta de teste obrigatório; ausência do Service obrigatório, falta da verificação de autorização exigida sem via demonstrada de acesso indevido, ou evento obrigatório ausente/incompleto sem impacto crítico | Bloqueia o merge |
| **Médio** | Problema real que não bloqueia: desvio de camada que não elimina o Service obrigatório nem compromete autorização ou auditoria, consulta dentro de laço, erro técnico exibido ao usuário | Deve ser corrigido |
| **Baixo** | Padrão de nomes, código duplicado pequeno, commit fora do padrão | Recomendado corrigir |
| **Sugestão** | Melhoria de estilo ou opinião | Opcional |

Classifique pelo impacto demonstrado. Por exemplo, confirmar uma concessão de privilégios sem trilha obrigatória é **Crítico**; faltar o teste de rollback, sem evidência de que a operação confirme sem auditoria, é **Alto**. Operações que comprometem a integridade dos dados ou expõem segredos não devem ser tratadas como problemas de estilo.

## Bloqueiam a aprovação (manual 15.4)

- Não resolver a issue ou não atender aos critérios de aceite.
- Quebrar funcionalidades existentes.
- Faltar informação para validar a solução.
- Alterar o banco sem migration ou justificativa.
- Incluir alterações não relacionadas sem justificativa.
- Ignorar validações necessárias à integridade dos dados.
- Introduzir falha de segurança ou expor dados, credenciais ou informação sensível.
- Incluir `.env`, credenciais, dumps ou arquivos sensíveis.
- Entregar operação abrangida pelo roteiro sem Service, sem as checagens obrigatórias de autorização ou sem auditoria obrigatória.

## Exigem ajuste (manual 15.5)

- Código duplicado.
- Regra de negócio na View ou no Controller. A ausência do Service obrigatório segue os critérios bloqueantes acima.
- Descumprimento dos padrões de escrita ou do padrão visual do template.
- Trechos que deveriam virar métodos, Services, Helpers ou componentes.
- Commits genéricos.
- Descrição da entrega incompleta.

## Formato da resposta

Para cada achado, indique o arquivo e a linha em que o problema pode ser verificado. Se faltar uma checagem ou um evento, aponte a rota ou o trecho da escrita que deveria garanti-lo. Explique o impacto e proponha a correção com base no manual ou no padrão local aplicável.

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
