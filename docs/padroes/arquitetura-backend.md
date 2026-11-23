# Arquitetura e back-end (CodeIgniter 4)

Base: Manual Calama Devs, capítulos 6 e 7. Em caso de dúvida, o manual prevalece.

## Camadas e responsabilidades

| Camada | Faz | Não faz |
|---|---|---|
| Controller | Recebe a requisição, valida a entrada, chama o Service, devolve a View ou o redirecionamento | SQL direto, regra de negócio, gravação de auditoria, métodos gigantes |
| Service | Executa as regras de negócio e de escopo, coordena Models, transação e auditoria | Acessar dados sem passar pela Model, manipular resposta HTTP |
| Model | Acesso aos dados: Query Builder, configuração da tabela, validações de persistência | Decisões de negócio, redirecionamentos, orquestração de processos |
| View | Apresentação: HTML, `if`, `foreach` e `esc()` | Consultas ao banco, regra de negócio, controle de acesso ou gravação de auditoria |
| Filter | Autenticação, autorização e verificações gerais da rota | Regra de negócio, consultas pesadas, alterações no banco |
| Helper | Funções pequenas, genéricas e reutilizáveis (formatação, conversão, máscaras) | Regra de negócio, processos complexos |
| Library | Componente reutilizável com responsabilidade própria e independente do domínio (ex.: gerador de PDF) | Regra de negócio, validação de requisição, decisões de fluxo |

Fluxo correto: View envia, Controller recebe e valida, Service processa, Model persiste, View exibe (manual 6.1.3 e 6.9).

## Regras obrigatórias

- No Hub e nos módulos integrados, **novas funcionalidades usam a camada Service**. Reaproveite um Service do mesmo domínio quando ele já atender à responsabilidade; crie outro apenas se necessário. Mudanças em operações de CRUD existentes seguem a mesma regra para o fluxo alterado, sem exigir refatorações fora do escopo.
- Toda criação, atualização e exclusão de dados no escopo da alteração, inclusive exclusão lógica, usa a [auditoria centralizada](../auditoria.md). O Service da operação coordena a escrita e o evento na mesma transação; não grave a auditoria diretamente no Controller, na View ou na Model de negócio.
- Models usam os recursos nativos do CI4 e retornam **array** (`protected $returnType = 'array';`). Acesso em Views e Services: `$aluno['nome']`.
- Query Builder é o padrão. SQL manual só quando o Query Builder não atende, e sempre parametrizado (`?` ou bindings). Nunca concatenar dados externos em SQL.
- Uma Model por tabela, com `$table`, `$primaryKey`, `$allowedFields`, `$useTimestamps` e `$useSoftDeletes` configurados quando aplicável.
- Proteja rotas restritas com os Filters do Shield. Em operações sensíveis, o Service também verifica a permissão efetiva e o escopo do registro quando aplicável. Siga o [padrão de permissões](permissoes.md); esconder botão não protege nada.
- Toda entrada (formulário, rota, query string, API, upload) é validada antes do processamento, preferencialmente com o serviço de validação do CI4 (`$this->validate()` ou regras na Model).
- Erros técnicos nunca aparecem para o usuário em produção: registrar em log e exibir mensagem clara.

## Retorno padronizado (manual 6.10.2)

Quando um Service ou endpoint devolver resultado estruturado, use:

```php
[
    'sucesso'  => false,
    'tipo'     => 'permissao',          // validacao | permissao | regra_negocio | inesperado | sucesso
    'codigo'   => 'ALUNO_SEM_PERMISSAO',
    'mensagem' => 'Sem permissão para cadastrar alunos.',
];
```

Mensagens ao usuário: curtas, sem detalhe técnico. Mensagens de redirecionamento usam as chaves `success` e `error` (`redirect()->to('/matriculas')->with('success', '...')`).

## Classificação de erros (manual 6.10.1)

- **Validação:** campo obrigatório vazio, formato inválido.
- **Permissão:** usuário sem autorização para a ação ou o registro.
- **Regra de negócio:** operação viola uma regra (ex.: excluir turma com alunos).
- **Inesperado:** falha de banco, serviço externo, exceção não tratada. Sempre vai para o log.

## Logs (manual 6.10.4 e 10.6)

- Registrar falhas relevantes com contexto técnico suficiente, como código interno de erro e nome da operação, sem expor detalhes ao usuário.
- Nunca registrar senhas, tokens, credenciais ou dados pessoais em logs técnicos.
- Log técnico é para diagnóstico; não substitui o [evento de auditoria](../auditoria.md).

## Organização de pastas (manual 7.1 e 7.2)

```
app/
  Controllers/  Models/  Services/  Libraries/  Helpers/  Views/  Filters/  Config/
  Database/
    Migrations/
    Seeds/
public/assets/   writable/   tests/   docs/
```

Em sistemas maiores, agrupar por domínio dentro de cada pasta (`Controllers/Alunos/`, `Services/Alunos/`). Mudanças nessa estrutura precisam de justificativa no README.

## Nomes de arquivos e classes (manual 7.3 e 10.3)

| Tipo | Padrão | Exemplo |
|---|---|---|
| Controller | PascalCase + `Controller`, um por contexto | `MatriculaController.php` |
| Model | PascalCase + `Model`, uma por tabela | `AlunoModel.php` |
| Service | PascalCase + `Service`, nome da regra ou contexto | `ConflitoDisciplinaService.php` |
| Filter | PascalCase + `Filter` | `PermissaoFilter.php` |
| Helper | snake_case + `_helper` | `dados_helper.php` |
| View | pasta do controller em minúsculas, arquivo snake_case com o nome do método | `Views/aluno/cadastrar.php` |

Nomes em português, exceto o que o framework impõe (sufixos, `index()`, `created_at`, chaves `success`/`error`, prefixo `test`).

## Exemplo de referência

```php
// Controller: só coordena
public function salvar()
{
    if (! $this->validate('matricula')) {
        return redirect()->back()->withInput()->with('error', 'Verifique os dados informados.');
    }

    // Service registrado em app/Config/Services.php; devolve o retorno padronizado
    $resultado = service('MatriculaService')->realizarMatricula($this->validator->getValidated());

    if (! $resultado['sucesso']) {
        return redirect()->back()->withInput()->with('error', $resultado['mensagem']);
    }

    return redirect()->to('/matriculas')->with('success', $resultado['mensagem']);
}
```

```php
// Service: regra em etapas pequenas
class PedidoService
{
    public function registrarPedido(array $dadosPedido): array
    {
        $cliente  = $this->validarCliente($dadosPedido);
        $produtos = $this->validarProdutos($dadosPedido['produtos']);

        $cliente['valor_total'] = $this->calcularTotal($produtos);

        return $cliente;
    }
}
```

## Sinais de alerta para o revisor

- Controller com SQL, laço de regra de negócio ou mais de ~40 linhas por método.
- Escrita de dados que não passa por um Service ou que pode ser confirmada sem a auditoria obrigatória.
- `new AlgumaModel()` dentro de View.
- Service chamando `$this->db->query()` com variáveis concatenadas.
- Helper com nome de ação de negócio (`aprovarMatricula`).
- Library criada para uma transformação de uma linha (deveria ser Helper).
