# Infraestrutura de auditoria

`eventos_auditoria` registra ações administrativas relevantes sem armazenar os
dados alterados. Cada evento contém o módulo, a ação, o tipo e o identificador
opcional do alvo, o resultado, o ator autenticado quando existir e a data de
criação.

## Uso pelos Services

Services administrativos devem registrar somente metadados técnicos da ação:

```php
$auditoria->registrar([
    'modulo'     => 'planifica-hub',
    'acao'       => 'conceder_permissao',
    'alvo_tipo'  => 'usuario',
    'alvo_id'    => (string) $usuarioId,
    'resultado'  => 'concluida',
]);
```

Para várias ações independentes, use o lote:

```php
$auditoria->registrarVarios([
    [
        'modulo' => 'acessos',
        'acao' => 'revogar_permissao',
        'alvo_tipo' => 'usuario',
        'alvo_id' => (string) $usuarioId,
        'resultado' => 'concluida',
    ],
    [
        'modulo' => 'acessos',
        'acao' => 'conceder_permissao',
        'alvo_tipo' => 'usuario',
        'alvo_id' => (string) $outroUsuarioId,
        'resultado' => 'recusada',
    ],
]);
```

Os únicos resultados aceitos são `concluida`, `recusada` e `erro`. O
`AuditoriaService` substitui qualquer `created_by` recebido pelo identificador
da sessão Shield e não aceita data informada pelo chamador: o Model define
`created_at` automaticamente.

Os campos fora da lista permitida são descartados pelo Model. Não registre
senhas, tokens, e-mails, documentos ou o conteúdo de dados alterados.

## Falhas de auditoria

`registrar()` e `registrarVarios()` não lançam falhas de validação ou
persistência para o Service chamador. A falha fica somente no log técnico, sem
o conteúdo do evento. A conexão do Model é própria para que uma falha de
auditoria não invalide a transação administrativa principal.

A auditoria pode ser persistida mesmo se uma transação administrativa posterior
for revertida. Por isso, o chamador deve registrar o resultado final conhecido
da operação.