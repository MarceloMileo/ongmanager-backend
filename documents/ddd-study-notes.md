# DDD Study Notes — Domain-Driven Design

> **Objetivo:** Consolidar o aprendizado teórico e prático de Domain-Driven Design, registrando conceitos, dúvidas, descobertas e referências à medida que o estudo avança. Este arquivo serve como restore point para sessões de estudo com IA.

---

## 1. Contexto do Estudante

- **Experiência atual:** ~7 anos de PHP, ambiente sem boas práticas (sem CI/CD, sem gitflow estruturado)
- **Objetivo:** Tornar-se engenheiro de software sênior, com domínio real de arquitetura e DDD
- **Projeto de referência prática:** ONGManager (`ongmanager-backend`) — SaaS B2B para gestão de ONGs em Laravel 13 (PHP 8.3)
- **Stack do projeto prático:** Laravel 13, PHP 8.3, PostgreSQL 16, Redis, RabbitMQ, Docker/DevContainers, Terraform

---

## 2. Conceitos Fundamentais — Mapa de Progresso

| Conceito | Status | Notas |
|---|---|---|
| Value Objects | ✅ Concluído | `Money`, `ExchangeRate`, `ProjectId`, `UserId`, `Receipt`, `CostDistribution` |
| Backed Enums | ✅ Concluído | `ExpenseStatus`, `DistributionType` |
| Entities | ✅ Concluído | `ExpenseLine` com getters, equals e métodos de alteração |
| Aggregate Root | ✅ Concluído | `Expense` com state machine, invariantes e Domain Events |
| Domain Events | ✅ Concluído | `ExpenseSubmitted`, `ExpenseApproved`, `ExpenseRejected`, `ExpensePaid` |
| Repositories | ✅ Concluído | `IExpenseRepository` (interface) + `EloquentExpenseRepository` (infra) |
| Application Services / Use Cases | ✅ Concluído | `SubmitExpense`, `ApproveExpense` com Command + Handler |
| Domain Services | 🔜 Próximo | `CostDistributionCalculator` — algoritmo de rateio com Penny Rounding Rule |
| Bounded Contexts | 🔄 Em andamento | Separação `SpendManagement` → `ProjectDelivery` via `ProjectId` aplicada |
| CQRS | ⏳ Não iniciado | |
| Saga / Process Manager | ⏳ Não iniciado | |

---

## 3. Value Objects

### O que é
Um Value Object representa um conceito do domínio definido apenas pelos seus **atributos** — não tem identidade própria.

### Características obrigatórias
- **Imutável:** `final readonly` no PHP 8.2+
- **Auto-validante:** construtor garante que VO inválido nunca existe
- **PHP puro:** sem dependências de framework
- **`equals()`:** comparação semântica por atributos

### Padrões aprendidos na prática
- Valores monetários como `int` (centavos) — nunca `float`
- Taxas de câmbio como `string` para `bcmath`
- Datas como `DateTimeImmutable` validadas como UTC
- Proporções como `int` em basis points (10000 = 100%)
- Separar formato de exibição (`getProportionAsPercentage()`) do formato de cálculo (`getProportionInBasisPoints()`)
- VO genérico vs específico: `UserId` em vez de `SubmitterId`/`ApproverId` — papel é contextual

---

## 4. Backed Enums

### Regra prática
> Se o valor cruza uma fronteira (banco, API, evento, fila) → **Backed Enum**.

### Padrões
- `Enum::from('valor')` — lança `\ValueError` se inválido
- `Enum::tryFrom('valor')` — retorna `null` se inválido
- Enums reutilizáveis pertencem ao **Shared Kernel**

---

## 5. Entities

### Diferença prática vs Value Object

| | Value Object | Entidade |
|---|---|---|
| Identidade | Pelos valores | Pelo ID (UUID) |
| Imutabilidade | Total (`final readonly`) | Parcial |
| Igualdade | `equals()` compara atributos | `equals()` compara IDs |

### Padrões
- ID validado via `Uuid::isValid()` + normalizado para lowercase
- Validações repetidas extraídas para métodos privados (DRY)
- Métodos de alteração aplicam as mesmas invariantes do construtor

---

## 6. Aggregate Root

### Regras fundamentais
- Objetos externos só referenciam o Aggregate Root
- Toda modificação passa pelo Root
- O Root garante as **invariantes**
- Fronteira de transação — salvo atomicamente

### Padrão collect-and-publish para Domain Events
```php
// Dentro do Aggregate Root
private array $domainEvents = [];

private function recordEvent(object $event): void
{
    $this->domainEvents[] = $event;
}

/** @return array<object> */
public function pullDomainEvents(): array
{
    $events = $this->domainEvents;
    $this->domainEvents = [];
    return $events;
}
```
O aggregate acumula eventos internamente. O repositório ou Use Case os coleta via `pullDomainEvents()` e publica no broker.

### Invariantes do `Expense`
- UUID válido, `totalAmount > 0`, moeda do `totalAmount` == moeda do `Receipt`
- `addLine()` só em `DRAFT`
- `submit()` exige ao menos uma `ExpenseLine`
- `approve()` — `approverId` ≠ `submitterId`

### State Machine
```
DRAFT → SUBMITTED → APPROVED → PAID
                 ↘ REJECTED
```

---

## 7. Domain Events

### O que é
Fato imutável que aconteceu no domínio. Nomeado sempre no **passado**. Carrega `occurredAt` em UTC.

### Estrutura
```php
final readonly class ExpenseSubmitted
{
    public function __construct(
        public string $expenseId,
        public string $submitterId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
```
Atributos `public` — evento é só um container de dados, sem lógica.

---

## 8. Repositories

### Interface no Domain (contrato)
```php
interface IExpenseRepository
{
    public function save(Expense $expense): void;
    public function findById(string $id): ?Expense;
    public function delete(Expense $expense): void;
    /** @return array<Expense> */
    public function findByStatus(ExpenseStatus $status): array;
    /** @return array<Expense> */
    public function findBySubmitter(UserId $id): array;
}
```

### Implementação na Infrastructure (Eloquent)
- `EloquentExpenseRepository` implementa `IExpenseRepository`
- `toDomain()` reconstrói o aggregate a partir dos Models Eloquent
- Relacionamentos UUID: usar `foreignUuid()` no Laravel + PostgreSQL

---

## 9. Application Layer — Use Cases

### Estrutura Command + Handler
```
UseCases/
└── SubmitExpense/
    ├── SubmitExpenseCommand.php   ← DTO com dados da requisição
    └── SubmitExpenseHandler.php   ← orquestração
```

### Fluxo do Handler
```
HTTP Request → Controller → Command → Handler
                                        ↓
                                  Repository.findById()
                                        ↓
                                  Expense.submit() ← regras no domínio
                                        ↓
                                  Repository.save()
                                        ↓
                                  pullDomainEvents() → TODO: broker
```

### Responsabilidade do Handler
- **Não tem regras de negócio** — isso fica no domínio
- Orquestra: busca → chama domínio → salva → publica eventos
- Lança `RuntimeException` quando entidade não encontrada (não `InvalidArgumentException`)

---

## 10. Domain Services

### O que é
Executa operações de domínio complexas que não pertencem naturalmente a nenhuma entidade ou VO.

### Relação VO ↔ Domain Service
> **Domain Services produzem VOs** — o VO representa o *resultado*, o Service executa o *processo*.

### Próximo: `CostDistributionCalculator`
Recebe `Expense` + `ExchangeRate[]`, aplica Penny Rounding Rule, retorna `CostDistribution[]`.

---

## 11. Infrastructure Layer

### Eloquent Models
- Vivem em `Infrastructure/Models/` — nunca no Domain
- UUID como primary key: `$keyType = 'string'` + `$incrementing = false`
- Relacionamentos UUID: `foreignUuid('expense_id')->constrained('expenses')->cascadeOnDelete()`

### Migrations
- `timestampTz` para datas com timezone (ADR-003)
- `migrate:fresh` em desenvolvimento quando há mudanças de schema
- Em produção: apenas `migrate` — nunca `fresh`

---

## 12. PHPStan

### Configuração
- Nível 8 com Larastan — zero erros obrigatório
- Arrays tipados via PHPDoc: `/** @return array<Expense> */`
- Proporções com basis points: `/** @param 1|2|3|4 $roundingMode */`
- Erros de generics do Eloquent: suprimir via `ignoreErrors` com `identifier` + `path`

---

## 13. Git — Boas Práticas

| Prefixo | Quando usar |
|---|---|
| `feat` | Nova funcionalidade |
| `fix` | Correção de bug |
| `test` | Testes |
| `refactor` | Reestruturação sem mudar comportamento |
| `style` | Formatação, imports |
| `docs` | Documentação |
| `chore` | Manutenção |

---

## 14. Dúvidas e Descobertas

| # | Dúvida / Descoberta | Status |
|---|---|---|
| 1 | Valores negativos em `Money` são válidos? | ✅ Sim — estornos contábeis |
| 2 | Por que taxa de câmbio como `string`? | ✅ `bcmath` sem perda de precisão |
| 3 | Por que `DateTimeImmutable`? | ✅ `DateTime` é mutável |
| 4 | `ExpenseLine` é entidade ou VO? | ✅ Entidade — tem identidade própria |
| 5 | Como contextos se comunicam sem acoplamento? | ✅ Domain Events + referência por ID |
| 6 | `ProjectId::generate()` é Factory Pattern? | ✅ Não — static factory method |
| 7 | Enum puro ou Backed Enum? | ✅ Backed quando cruza fronteiras |
| 8 | Validar arquivo no domínio? | ✅ Não — filesystem é infraestrutura |
| 9 | `ExpenseLine` valida moedas? | ✅ Não — responsabilidade da `Expense` |
| 10 | `?Type` sem `= null` é opcional? | ✅ Não — `= null` é obrigatório |
| 11 | Validação repetida no construtor? | ✅ Extrair para método privado (DRY) |
| 12 | `setUp()` compartilha estado? | ✅ Não — executado antes de cada teste |
| 13 | `SubmitterId` e `ApproverId` separados? | ✅ `UserId` genérico — papel é contextual |
| 14 | `Expense` recebe `status` no construtor? | ✅ Não — sempre `DRAFT` internamente |
| 15 | `ExpenseLine` obrigatória na criação? | ✅ Não — adicionada via `addLine()` |
| 16 | `Expense` aprova a si mesma? | ✅ Sim — Aggregate Root é guardião de invariantes |
| 17 | Comparar `UserId` com `===`? | ✅ Não — usar `equals()` |
| 18 | Quem valida moeda do `Receipt` vs `totalAmount`? | ✅ `Expense` no construtor |
| 19 | `CostDistribution` é o VO mais complexo? | ✅ O VO é simples — complexidade está no Domain Service |
| 20 | Quem enriquece os VOs? | ✅ Domain Services — produzem VOs como resultado |
| 21 | `allFindAll()` no repositório? | ✅ Evitar — usar métodos específicos (`findByStatus`, `findBySubmitter`) por performance |
| 22 | `delete` no repositório de auditoria? | ✅ Só despesas em `DRAFT` — validação no domínio, não no repositório |
| 23 | `RuntimeException` vs `InvalidArgumentException` no Handler? | ✅ `RuntimeException` para entidade não encontrada — argumento é válido, entidade é que não existe |
| 24 | `foreignUuid()` vs `foreign()` no Laravel? | ✅ `foreignUuid()` cria coluna + constraint UUID compatível com PostgreSQL |
| 25 | `migrate:fresh` vs `migrate`? | ✅ `fresh` em dev (recria tudo), `migrate` em produção (só novas) |

---

## 15. Referências e Leituras

- **Livro base:** *Domain-Driven Design* — Eric Evans (Livro Azul)
- **Livro prático:** *Implementing Domain-Driven Design* — Vaughn Vernon (Livro Vermelho)
- **Artigo:** Padrão de Alocação Proporcional (Penny Rounding) — Martin Fowler
- **Projeto prático de referência:** `github.com/MarceloMileo/ongmanager-backend`
