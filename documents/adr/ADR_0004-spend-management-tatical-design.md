# ADR 0004: Modelagem Tática do Bounded Context SpendManagement

**Status:** ✅ Aprovado

---

## Contexto

Com o Shared Kernel finalizado (`Money` e `ExchangeRate`), o próximo passo é modelar taticamente o primeiro Bounded Context de negócio do ONGManager: o **SpendManagement** (Gestão de Gastos e Reembolsos).

Este contexto é responsável por capturar, validar, ratear e rastrear despesas operacionais das ONGs, garantindo rastreabilidade fiscal completa e consistência matemática absoluta nos rateios — inclusive em cenários multimoeda.

As decisões aqui registradas são resultado de sessão de modelagem colaborativa (Event Storming simplificado), considerando as regras de domínio definidas no documento de Regras de Domínio, Fiscais e Compliance Matemático, e as restrições arquiteturais das ADRs 0001, 0002 e 0003.

---

## Decisões

### 1. `Expense` como Aggregate Root

`Expense` é o **Aggregate Root** do `SpendManagement`. É a fronteira de consistência de todo o contexto — toda operação sobre despesas passa obrigatoriamente por ela.

**Justificativa:** Uma despesa tem ciclo de vida longo e rastreável (requisito de auditoria), carrega invariantes complexas que envolvem múltiplos objetos internos (`ExpenseLine`, `Receipt`), e é o ponto de emissão de todos os Domain Events do contexto.

#### Ciclo de vida (State Machine)

```
DRAFT → SUBMITTED → APPROVED → PAID
                 ↘ REJECTED
```

- **DRAFT:** Estado inicial. Despesa pode receber `ExpenseLine` e ter dados editados.
- **SUBMITTED:** Colaborador de campo submete para aprovação. Nenhuma edição permitida após este ponto.
- **APPROVED:** Gestor financeiro aprova. Dispara o processo de pagamento.
- **REJECTED:** Gestor rejeita com justificativa obrigatória. Pode retornar a DRAFT para correção.
- **PAID:** Pagamento confirmado. Estado terminal imutável.

#### Invariantes do Aggregate

- Uma `Expense` só pode ser submetida (`SUBMITTED`) se possuir ao menos uma `ExpenseLine`.
- A soma dos valores das `ExpenseLine` (normalizados para a moeda base da ONG via `ExchangeRate`) deve igualar exatamente o valor total da despesa.
- Nenhuma `ExpenseLine` pode ser adicionada ou removida após o estado `DRAFT`.
- O aprovador (`approverId`) deve ser diferente do submissor (`submitterId`) — segregação de papéis para compliance.
- Toda `Expense` deve ter obrigatoriamente um `Receipt` anexado no momento da criação.
- O valor da despesa é sempre armazenado na **moeda original do comprovante fiscal** (não convertido) para garantir rastreabilidade de auditoria.

---

### 2. `ExpenseLine` como Entidade interna do Aggregate

`ExpenseLine` é uma **Entidade** — tem identidade própria (UUID) e pode ser adicionada ou removida individualmente enquanto a `Expense` estiver em `DRAFT`.

**Justificativa:** Por ter identidade rastreável e ciclo de vida gerenciável individualmente (pode ser editada, removida), não se qualifica como Value Object. Porém, por ser parte interna do aggregate, objetos externos jamais a referenciam diretamente — apenas via `Expense`.

#### Atributos

- `id` — UUID único da linha
- `projectId` — `ProjectId` (referência por ID ao contexto `ProjectDelivery` — ver seção 5)
- `amount` — `Money` na moeda original da linha (pode ser diferente da moeda da despesa)
- `exchangeRate` — `ExchangeRate` opcional; obrigatória quando a moeda da linha difere da moeda base da ONG
- `distributionType` — enum: `FIXED_AMOUNT` ou `PERCENTAGE`

---

### 3. `Receipt` como Value Object obrigatório

`Receipt` é um **Value Object** imutável que representa o comprovante fiscal anexado à despesa. Uma vez criado, não pode ser alterado — um novo comprovante implica criação de nova instância.

**Justificativa:** O comprovante é um fato histórico imutável. Sua imutabilidade garante a integridade da trilha de auditoria.

#### Atributos obrigatórios

- `fileReference` — referência/path do arquivo físico anexado (PDF, imagem)
- `documentValue` — `Money` com o valor impresso no comprovante (moeda original)

#### Atributos complementares (opcionais — enriquecem a auditoria)

- `documentNumber` — número da nota fiscal ou recibo
- `issuerIdentifier` — identificador fiscal do fornecedor (CNPJ, RUT, etc.)
- `issuedAt` — `DateTimeImmutable` em UTC (data de emissão do documento)

---

### 4. `CostDistribution` como Value Object de resultado

`CostDistribution` é um **Value Object** imutável gerado após o cálculo do rateio. Representa a distribuição final de custos normalizada para a moeda base da ONG, após aplicação das `ExchangeRate` e da **Penny Rounding Rule**.

**Justificativa:** É o resultado de um cálculo — não tem identidade própria nem ciclo de vida. Uma vez calculado, é substituído inteiro se o rateio mudar.

#### Penny Rounding Rule (conforme documento de regras de domínio)

Quando a conversão cambial gerar fração residual de centavos ($D$):

1. Se $D \le 0.05$ na moeda base, a transação é válida.
2. O resíduo $D$ é creditado/debitado automaticamente na `ExpenseLine` de maior valor absoluto dentro do rateio.

---

### 5. Referência ao `ProjectDelivery` via `ProjectId`

`ExpenseLine` referencia projetos do Bounded Context `ProjectDelivery` **exclusivamente por ID**, usando o Value Object `ProjectId` (wrapper de UUID) que reside no **Shared Kernel**.

**Justificativa:** Conforme ADR-002, contextos não importam classes uns dos outros. O acoplamento direto entre `SpendManagement` e `ProjectDelivery` violaria as fronteiras de domínio e impediria a evolução independente dos módulos.

```text
SpendManagement
└── ExpenseLine
    └── projectId: ProjectId  ← Shared Kernel (UUID wrapper)

ProjectDelivery
└── Project  ← entidade com ciclo de vida próprio
    └── id: ProjectId  ← mesmo VO do Shared Kernel
```

Quando o `SpendManagement` precisar **validar** se um projeto existe e está ativo, utilizará uma interface de porta (`IProjectValidator`) definida no `Domain` do `SpendManagement` e implementada na camada `Infrastructure`, consumindo o `ProjectDelivery` via evento ou query interna — sem acoplamento direto de classes.

---

### 6. Domain Events emitidos pelo Aggregate

Todos os eventos são imutáveis, nomeados no passado e carregam os dados do momento em que ocorreram.

| Evento | Disparado quando |
|---|---|
| `ExpenseSubmitted` | Colaborador submete a despesa para aprovação |
| `ExpenseApproved` | Gestor aprova a despesa |
| `ExpenseRejected` | Gestor rejeita com justificativa |
| `ExpensePaid` | Pagamento é confirmado |

Os eventos são publicados via **Laravel Queue + RabbitMQ** (conforme ADR-001) para consumo assíncrono por outros contextos — por exemplo, `BudgetAllocation` que precisa atualizar saldos orçamentários ao receber `ExpenseApproved`.

---

### 7. Estrutura de diretórios do contexto

```text
app/Contexts/SpendManagement/
├── Domain/
│   ├── Entities/
│   │   └── Expense.php                  ← Aggregate Root
│   ├── ValueObjects/
│   │   ├── ExpenseLine.php
│   │   ├── Receipt.php
│   │   ├── CostDistribution.php
│   │   └── ExpenseStatus.php            ← Enum de estados
│   ├── Events/
│   │   ├── ExpenseSubmitted.php
│   │   ├── ExpenseApproved.php
│   │   ├── ExpenseRejected.php
│   │   └── ExpensePaid.php
│   └── Repositories/
│       └── IExpenseRepository.php       ← Interface (contrato)
├── Application/
│   └── UseCases/
│       ├── SubmitExpense/
│       │   ├── SubmitExpenseCommand.php
│       │   └── SubmitExpenseHandler.php
│       └── ApproveExpense/
│           ├── ApproveExpenseCommand.php
│           └── ApproveExpenseHandler.php
└── Infrastructure/
    ├── Http/
    │   ├── Controllers/
    │   └── Requests/
    ├── Models/
    │   └── ExpenseModel.php             ← Eloquent (apenas persistência)
    ├── Repositories/
    │   └── EloquentExpenseRepository.php
    └── Providers/
        └── SpendManagementServiceProvider.php
```

---

## Consequências

### ✅ Positivas

- Rastreabilidade fiscal completa: o valor original do comprovante é sempre preservado, independente de conversões cambiais posteriores.
- Segregação de papéis (submissor ≠ aprovador) imposta como invariante de domínio — não depende de regra de negócio na camada de aplicação.
- Fronteira clara com `ProjectDelivery` via `ProjectId` permite evolução independente dos dois contextos.
- Domain Events permitem que `BudgetAllocation` reaja a aprovações de despesas sem acoplamento direto.

### ⚠️ Negativas

- A validação de existência e status do projeto (`IProjectValidator`) adiciona uma dependência de infraestrutura que precisa ser mockada nos testes de domínio.
- O cálculo da `CostDistribution` com Penny Rounding Rule em cenários multimoeda é a operação mais complexa do contexto e exigirá testes exaustivos.
