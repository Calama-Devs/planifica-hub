# Infraestrutura de auditoria

A tabela `eventos_auditoria` registra ações administrativas, armazenando o módulo, usuário responsável, ação, entidade afetada, descrição, resultado e data.

O campo `usuario_id` é preenchido automaticamente pelo Shield e pode ser `NULL` em processos automáticos. A `descricao` deve ser legível e informar as alterações realizadas, quando aplicável, sem JSON ou dados sensíveis.

## Uso pelos Services

A auditoria é obrigatória e deve compartilhar a **mesma conexão e transação** da operação administrativa.

```php
$banco->transBegin();

try {
    $auditoria->registrar([
        'modulo'        => 'acessos',
        'acao'          => 'conceder_permissao',
        'entidade_tipo' => 'usuario',
        'entidade_id'   => (string) $usuarioId,
        'descricao'     => 'Permissão X concedida.',
        'resultado'     => 'concluida',
    ]);

    if ($banco->transStatus() === false) {
        throw new RuntimeException('Falha na transação.');
    }

    $banco->transCommit();
} catch (Throwable $erro) {
    $banco->transRollback();
    throw $erro;
}
```

Para registrar múltiplos eventos, utilize `registrarVarios()`.

Os resultados permitidos são `concluida`, `recusada` e `erro`. O campo `created_at` é preenchido automaticamente pela Model.

## Falhas de auditoria

Falhas de validação ou persistência geram exceções. O Service responsável deve reverter a transação, impedindo que alterações administrativas sejam confirmadas sem auditoria.

O `AuditoriaService` não gerencia transações.

Eventos de operações recusadas ou com erro, quando precisarem ser preservados, devem ser registrados separadamente após o rollback da operação principal.