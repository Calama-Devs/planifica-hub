# Infraestrutura de auditoria

## Quando registrar

No PlanIFica Hub e nos módulos integrados, toda criação, atualização ou exclusão de dados implementada ou alterada deve usar a auditoria centralizada (`AuditoriaService` e tabela `eventos_auditoria`). Isso inclui exclusão lógica, alterações administrativas de grupos e permissões e escritas em lote ou executadas em segundo plano. Reaproveite o mecanismo do Hub; não crie tabela ou serviço paralelo para a mesma finalidade.

## O que registrar

| Informação | Campo | Orientação |
|---|---|---|
| Responsável | `usuario_id` | O `AuditoriaService` obtém o identificador da autenticação do Shield; não o aceite do formulário ou da requisição. Pode ser `NULL` em processos automáticos sem usuário autenticado. Quando preenchido, referencia `users.id` com `RESTRICT` para preservar o vínculo histórico. |
| Módulo e ação | `modulo`, `acao` | Use identificadores estáveis e descritivos da operação, como `turmas` e `alterar_situacao`. |
| Entidade e registro | `entidade_tipo`, `entidade_id` | Identifique o tipo e o registro afetado. Informe o ID quando conhecido; ele pode ser nulo se ainda não houver registro identificado, como em uma criação que falhou antes de gerar o ID. |
| Data e horário | `created_at` | Preenchido automaticamente pela `EventoAuditoriaModel`. |
| Resultado | `resultado` | `concluida`, `recusada` ou `erro`. Use `concluida` apenas para escrita efetivada após o commit; os critérios dos demais valores estão em [Falhas de auditoria](#falhas-de-auditoria). |
| Detalhe da alteração | `descricao` | Texto legível, sem JSON. Quando aplicável, descreva o estado anterior e o posterior dos campos relevantes para a auditoria. |

Para atualização, compare valores persistidos antes da mudança com o novo estado, não com o que o cliente afirma ser o estado anterior. Em uma criação, descreva o novo estado relevante; na exclusão, identifique o registro e descreva seu estado anterior ou a mudança para removido, se isso for relevante. Exemplo: `Situação alterada de planejada para publicada.` Se o dado alterado for sensível, registre apenas o fato da alteração, como `Senha alterada.`, nunca o valor anterior ou posterior.

Não inclua senhas, hashes de senha, tokens, chaves, credenciais, dados pessoais desnecessários nem cópias completas de formulários, registros ou cabeçalhos. Selecione somente os campos seguros necessários para explicar a ação.

Eventos são somente de inclusão: não têm `updated_at`, `deleted_at`, `updated_by` ou `deleted_by`.

## Uso pelos Services

O Service de negócio verifica a [autorização](padroes/permissoes.md), coordena a persistência pelas Models e registra o evento **na mesma conexão e transação** da alteração, antes do commit. O `AuditoriaService` grava os eventos, mas não abre nem encerra transações. Neste exemplo, o Service já verificou o acesso à turma e `$banco` é a conexão usada também pela Model de turma:

```php
$auditoria = new AuditoriaService(new EventoAuditoriaModel($banco));

if (! $banco->transBegin()) {
    throw new RuntimeException('Não foi possível iniciar a transação.');
}

try {
    if (
        ! $turmas->where('situacao', 'planejada')->update($turmaId, ['situacao' => 'publicada'])
        || $banco->affectedRows() !== 1
    ) {
        throw new RuntimeException('Não foi possível atualizar a turma.');
    }

    $auditoria->registrar([
        'modulo'        => 'turmas',
        'acao'          => 'alterar_situacao',
        'entidade_tipo' => 'turma',
        'entidade_id'   => (string) $turmaId,
        'descricao'     => 'Situação alterada de planejada para publicada.',
        'resultado'     => 'concluida',
    ]);

    if ($banco->transStatus() === false || ! $banco->transCommit()) {
        throw new RuntimeException('Falha na transação.');
    }
} catch (Throwable $erro) {
    $banco->transRollback();
    throw $erro;
}
```

O evento do exemplo descreve a transição efetivamente realizada: se a situação anterior não for `planejada`, a escrita não afeta o registro e não gera evento de sucesso. Para registrar múltiplos eventos, utilize `registrarVarios()` na mesma transação. Se um módulo usar outra conexão ou persistência externa, valide a estratégia de integração com o responsável técnico antes de implementar: duas conexões não compartilham a mesma transação, e gravar o evento depois do commit não garante a integridade exigida.

## Falhas de auditoria

Se a escrita de negócio ou o registro da auditoria falhar, o Service responsável deve reverter a transação. Não confirme a operação sem o evento obrigatório nem reporte sucesso antes do commit. Trate o erro conforme o [padrão de back-end](padroes/arquitetura-backend.md), sem expor detalhes técnicos ao usuário.

Toda escrita efetivada gera um evento `concluida`. Tentativa que não altera dados não é auditada por padrão; quando a regra da operação exigir preservá-la, registre um evento separado, após o rollback da transação principal se ela chegou a ser iniciada. Use o valor conforme o motivo:

| Valor | Quando usar | Exemplo de `descricao` |
|---|---|---|
| `recusada` | A operação foi negada **antes** de qualquer escrita: falta de permissão, registro fora do escopo do usuário ou regra de negócio impediu a execução. | `Exclusão recusada: a turma possui alunos vinculados.` |
| `erro` | A operação começou a ser processada e falhou por exceção ou falha técnica, inclusive na gravação da auditoria obrigatória. | `Falha técnica ao excluir a turma.` |

Uma negativa barrada pelo filtro de rota não chega ao Service e, por isso, não gera evento automaticamente. Se precisar preservá-la, registre-a no ponto em que o motivo é confiável, sem confiar em dados enviados pelo cliente. Em qualquer caso, operação revertida ou não executada nunca é `concluida`, o evento `erro` não recebe detalhes técnicos nem dados sensíveis, e o registro da tentativa não deve executar a escrita recusada nem mudar o resultado devolvido ao usuário.

## Auditoria não é log técnico

A auditoria responde **quem fez o quê, em qual módulo e registro, quando e com qual resultado**. O log técnico (`log_message()`) serve ao diagnóstico de falhas e não substitui o evento obrigatório. Registre apenas contexto técnico seguro, como o código do erro e o nome da operação; não grave dados pessoais, segredos ou conteúdo bruto de requisições.
