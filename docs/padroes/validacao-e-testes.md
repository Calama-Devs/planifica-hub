# Validação e testes

Base: Manual Calama Devs, itens 6.9, 11.4 e capítulos 16 e 17. Em caso de dúvida, o manual prevalece.

## Validação de dados

- Toda entrada é validada no back-end antes de qualquer processamento, mesmo que já exista validação no formulário.
- Usar a validação nativa do CI4: grupos de regras em `app/Config/Validation.php`, `$this->validate()` no Controller ou `$validationRules` na Model.
- Regras de negócio (ex.: "turma com alunos não pode ser excluída") ficam no Service e retornam erro do tipo `regra_negocio`.
- Mensagens de validação em português, claras e próximas ao campo.

```php
// app/Config/Validation.php
public array $aluno = [
    'id'    => 'permit_empty|is_natural_no_zero',
    'nome'  => 'required|max_length[100]',
    'email' => 'required|valid_email|is_unique[alunos.email,id,{id}]',
    'cpf'   => 'required|exact_length[14]',
];
```

## Testes automatizados (manual 16.1.7)

**Obrigatório:** toda regra nova ou alterada em **Services** e **Helpers** vem com teste automatizado na mesma entrega.

**Recomendado, sempre que viável:** Models com consultas relevantes, migrations, transações e endpoints.

**Mínimo para Controllers e telas:** teste manual (fluxo principal, erros, permissões), descrito na entrega.

- Ferramenta: PHPUnit, já configurado no CodeIgniter 4 (`phpunit.dist.xml` ou `phpunit.xml.dist` na raiz).
- Pasta `tests/`: `tests/unit/` para Helpers e Services isolados, `tests/database/` para testes com banco (`DatabaseTestTrait`).
- Arquivo termina em `Test.php`; classe `final` estendendo `CIUnitTestCase`; métodos começam com `test` e descrevem o comportamento (`testRecusaCpfComDigitoInvalido`).
- Cobrir o caminho feliz **e** pelo menos um caso de erro ou limite.
- Testes não dependem de dados reais nem de serviços externos; usar dados fictícios e, quando preciso, dublês (mocks).
- O Quality Gate do DevCore roda os testes a cada commit do PR; teste falhando bloqueia a integração.

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DadosHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('dados');
    }

    public function testPadronizaCpf(): void
    {
        $this->assertSame('123.456.789-00', padronizar_cpf('12345678900'));
    }
}
```

Executar: `vendor/bin/phpunit` (Linux) ou `.\vendor\bin\phpunit` (Windows).

## Testes manuais esperados (manual 16.1)

| Teste | O que verificar |
|---|---|
| Manual local | A funcionalidade faz o que a issue pede, mensagens e comportamento da interface |
| Permissões | Usuário autorizado acessa; não autorizado é bloqueado, inclusive acessando a URL direto |
| Formulários | Campos obrigatórios, formatos, mensagens, validação também no back-end |
| Fluxo principal | O cenário mais importante funciona do início ao fim |
| Regressão básica | Funcionalidades vizinhas continuam funcionando (listar, editar, visualizar, login/logout) |

## Critérios de aceite (manual 16.4 e 19.4)

- A entrega precisa atender a **todos** os critérios de aceite da issue.
- Critérios verificáveis, observáveis pelo usuário, incluindo cenários de restrição de acesso.
- Não ampliar o escopo depois que o PR foi aberto: criar outra issue.

## Checklist antes da integração (manual 16.2)

- [ ] Resolve a issue e todos os critérios de aceite
- [ ] Funciona localmente; fluxo principal testado
- [ ] Cenários de erro e dados inválidos testados, com validação no back-end
- [ ] Testado com diferentes perfis, quando aplicável
- [ ] Interface conferida em resoluções comuns
- [ ] Entradas protegidas contra XSS, SQL Injection e CSRF
- [ ] Sem regressão em funcionalidades relacionadas
- [ ] Sem erros na interface nem nos logs
- [ ] Alterações de banco por migrations, criadas e testadas
- [ ] Regras novas ou alteradas em Services e Helpers com testes automatizados, todos passando
- [ ] Código no padrão do manual, pronto para outro membro manter
- [ ] Sem código de depuração, comentado ou sem uso
- [ ] PSR-12 e PHPStan verificados localmente
- [ ] Sem credenciais, senhas, tokens ou dados sensíveis no código e nos commits
- [ ] Commits no padrão do capítulo 12
- [ ] Trabalho feito na branch criada pelo DevCore
- [ ] Evidências (prints, vídeos) quando houver mudança de interface
- [ ] Descrição da entrega publicada na issue
