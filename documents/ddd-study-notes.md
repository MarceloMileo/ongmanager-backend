# DDD Study Notes — ONGManager

> Registro prático de conceitos DDD aprendidos e aplicados durante o desenvolvimento do ONGManager.
> Cada seção parte do conceito teórico e mapeia diretamente para o código do projeto.

---

## 1. Aggregate Root

**Teoria:**
O Aggregate Root é a porta de entrada para um conjunto de objetos relacionados (o Aggregate). Nenhum objeto externo pode referenciar ou modificar entidades internas do aggregate diretamente — tudo passa pelo Aggregate Root. Ele garante a consistência transacional do grupo como um todo.

**No ONGManager:**
`Expense` é o Aggregate Root do contexto `SpendManagement`. Ele controla `ExpenseLine` (entidade interna) e `Receipt` (Value Object). Nenhuma camada externa acessa `ExpenseLine` diretamente — apenas via métodos públicos do `Expense`.

```php
// ✅ Correto — via Aggregate Root
$expense->addLine($line);

// ❌ Errado — acesso direto à entidade interna
$expense->lines[] = $line;
```

---

## 2. Value Object (VO)

**Teoria:**
Value Objects não têm identidade própria — são definidos pelo valor dos seus atributos. São imutáveis: qualquer "modificação" cria um novo VO. Devem ser comparados via `equals()`, nunca via referência (`===`).

**No ONGManager:**
`Money`, `Receipt`, `ExchangeRate`, `ProjectId`, `UserId`, `CostDistribution` são todos VOs.

```php
// ✅ Comparação correta de VOs
$money1->equals($money2);

// ❌ Nunca use ===
$money1 === $money2; // false mesmo com mesmos valores
```

**Regras de validação ficam no construtor:**
```php
final readonly class Money {
    public function __construct(int $amountInCents, string $currency) {
        if ($amountInCents < 0) {
            throw new InvalidArgumentException('Money não pode ser negativo.');
        }
        // ...
    }
}
```

---

## 3. Domain Events

**Teoria:**
Domain Events registram que algo significativo aconteceu no domínio. São fatos imutáveis no passado. Permitem comunicação entre Bounded Contexts sem acoplamento direto.

**Padrão Collect-and-Publish (usado no ONGManager):**
O Aggregate Root acumula eventos em uma lista privada (`recordEvent`). A Application Layer coleta (`pullDomainEvents`) após salvar e publica. Isso garante que os eventos só são disparados após a persistência bem-sucedida.

```php
// Domain Layer — Expense.php
private array $domainEvents = [];

private function recordEvent(object $event): void {
    $this->domainEvents[] = $event;
}

public function pullDomainEvents(): array {
    $events = $this->domainEvents;
    $this->domainEvents = [];
    return $events;
}

// Domain Layer — dentro de approve()
$this->recordEvent(new ExpenseApproved(
    $this->id,
    $approverId->toString(),
    new DateTimeImmutable('now', new DateTimeZone('UTC')),
));

// Application Layer — ApproveExpenseHandler.php
$expense->approve(new UserId($command->approverId));
$this->repository->save($expense);
$domainEvents = $expense->pullDomainEvents();
// TODO: publicar no message broker (RabbitMQ)
```

**Domain Events criados:**
- `ExpenseSubmitted` — `expenseId`, `submitterId`, `occurredAt`
- `ExpenseApproved` — `expenseId`, `approverId`, `occurredAt`
- `ExpenseRejected` — `expenseId`, `approverId`, `reason`, `occurredAt`
- `ExpensePaid` — `expenseId`, `occurredAt`

---

## 4. Repository Pattern

**Teoria:**
O Repository abstrai o mecanismo de persistência. O Domain conhece apenas a interface (`IExpenseRepository`). A Infrastructure fornece a implementação concreta (`EloquentExpenseRepository`). Isso garante que o Domain seja independente de frameworks e bancos de dados.

**No ONGManager:**
```php
// Domain Layer — só conhece a interface
interface IExpenseRepository {
    public function save(Expense $expense): void;
    public function findById(string $id): ?Expense;
}

// Infrastructure Layer — implementa com Eloquent
class EloquentExpenseRepository implements IExpenseRepository {
    public function save(Expense $expense): void {
        ExpenseModel::updateOrCreate(['id' => $expense->getId()], [...]);
        ReceiptModel::updateOrCreate(['expense_id' => $expense->getId()], [...]);
    }

    public function findById(string $id): ?Expense {
        $model = ExpenseModel::with('receipt')->find($id);
        if (!$model) return null;
        return $this->toDomain($model); // reconstrói o Aggregate
    }
}
```

---

## 5. Use Cases — Command + Handler Pattern

**Teoria:**
Cada Use Case é representado por:
- **Command** — DTO imutável com primitivos (`string`, `int`, `bool`). Nunca contém objetos de domínio.
- **Handler** — recebe o Command, constrói os VOs/Entidades, orquestra a lógica.

**Por que Commands usam apenas primitivos?**
Commands chegam da HTTP Layer (controller, queue, CLI). A conversão de primitivos → VOs é responsabilidade do Handler, que está na Application Layer e pode importar o Domain. Se o Command importasse VOs, violaria a separação de camadas.

```php
// ✅ Correto — Command com primitivos
final readonly class CreateExpenseCommand {
    public function __construct(
        public string $fileReference,
        public int $documentValueCents,
        public string $documentValueCurrency,
        public string $submitterId,
        // ...
    ) {}
}

// ❌ Errado — Command com objetos de domínio
final readonly class CreateExpenseCommand {
    public function __construct(
        public Receipt $receipt,    // ❌ VO de domínio no DTO
        public UserId $submitterId, // ❌ VO de domínio no DTO
    ) {}
}
```

**Handler constrói os VOs:**
```php
class CreateExpenseHandler {
    public function handle(CreateExpenseCommand $command): void {
        $receipt = new Receipt(
            $command->fileReference,
            new Money($command->documentValueCents, $command->documentValueCurrency),
        );
        $expense = new Expense(
            Uuid::uuid4()->toString(),
            $receipt,
            new Money($command->totalAmountCents, $command->totalAmountCurrency),
            new UserId($command->submitterId),
        );
        $this->repository->save($expense);
    }
}
```

---

## 6. State Machine no Aggregate Root

**Teoria:**
O Aggregate Root é o guardião das transições de estado. Cada transição valida pré-condições, aplica a mudança e registra o evento de domínio correspondente.

**State Machine do `Expense`:**
```
DRAFT → SUBMITTED → APPROVED → PAID
                 ↘ REJECTED
```

**Invariantes implementadas:**
- `addLine()` — só em `DRAFT`
- `submit()` — só em `DRAFT`, exige ao menos uma `ExpenseLine`
- `approve()` / `reject()` — só em `SUBMITTED`, `approverId` ≠ `submitterId`
- `reject()` — exige `reason` não vazio
- `pay()` — só em `APPROVED`, `payerId` ≠ `submitterId`

```php
public function approve(UserId $approverId): void {
    if ($this->status !== ExpenseStatus::SUBMITTED) {
        throw new InvalidArgumentException('Apenas despesas submetidas podem ser aprovadas.');
    }
    if ($this->submitterId->equals($approverId)) {
        throw new InvalidArgumentException('O aprovador não pode ser o mesmo usuário que submeteu a despesa.');
    }
    $this->status = ExpenseStatus::APPROVED;
    $this->recordEvent(new ExpenseApproved(...));
}
```

---

## 7. IoC — Inversão de Controle

**Teoria:**
IoC é o princípio pelo qual o controle da criação de dependências é invertido: em vez de uma classe criar suas dependências, alguém externo as fornece (geralmente um container). No Laravel, o Service Container é o IoC container.

**Binding no ONGManager:**
```php
// SpendManagementServiceProvider.php
public function register(): void {
    $this->app->bind(
        IExpenseRepository::class,       // Interface (contrato)
        EloquentExpenseRepository::class, // Implementação concreta
    );
}
```

**Resultado:** quando o Laravel precisa resolver `IExpenseRepository` (no Handler, por exemplo), ele entrega `EloquentExpenseRepository` automaticamente. O Domain nunca sabe qual implementação está sendo usada.

**Validação via tinker:**
```php
app(IExpenseRepository::class)::class
// => "App\Contexts\SpendManagement\Infrastructure\Repositories\EloquentExpenseRepository"
```

---

## 8. Bounded Context

**Teoria:**
Bounded Context é uma fronteira explícita dentro da qual um modelo de domínio é aplicável. O mesmo conceito pode ter significados diferentes em contextos diferentes. Contextos se comunicam via eventos ou Anti-Corruption Layers.

**No ONGManager:**
Cada contexto tem sua própria pasta em `app/Contexts/`:
- `SpendManagement` — gestão de despesas
- `BudgetAllocation` — execução orçamentária
- `FiscalCompliance` — auditoria fiscal
- `Fundraising` — captação e doações
- `ProjectDelivery` — operações e beneficiários

**Shared Kernel:**
`app/Contexts/Shared/` contém VOs e conceitos compartilhados entre contextos: `Money`, `UserId`, `ProjectId`, `ExchangeRate`.

---

## 9. Separação de Papéis (Segregation of Duties)

**Teoria:**
Nenhuma pessoa deve ter controle completo sobre uma transação financeira. O submissor não pode aprovar/rejeitar a própria despesa, e o pagador não pode ser quem submeteu.

**Invariantes no Expense:**
```php
// approve() e reject()
if ($this->submitterId->equals($approverId)) {
    throw new InvalidArgumentException('O aprovador não pode ser o mesmo que submeteu.');
}

// pay()
if ($this->submitterId->equals($payerId)) {
    throw new InvalidArgumentException('O pagador não pode ser o mesmo que submeteu.');
}
```

---

## 10. Penny Rounding Rule (Próxima implementação)

**Teoria:**
Ao distribuir um valor entre N projetos com proporções percentuais, os centavos residuais da divisão inteira devem ser distribuídos um a um até que a soma das partes seja exatamente igual ao total. O algoritmo de Fowler (usado em `Money.allocate()`) garante isso.

**Exemplo:**
- Total: R$ 10,00 (1000 centavos)
- 3 projetos com 33.33% cada
- 1000 / 3 = 333 com resto 1
- Distribuição: 334 + 333 + 333 = 1000 ✅

**No ONGManager:** `CostDistributionCalculator` (próximo Domain Service a implementar) usará `Money.allocate()` para garantir que a soma das distribuições seja sempre igual ao `totalAmount` da `Expense`.

---

## 11. HTTP Layer — Controller sem lógica

**Teoria:**
Controllers na HTTP Layer são responsáveis apenas por:
1. Extrair dados do Request
2. Construir o Command
3. Chamar o Handler
4. Retornar a Response

Toda lógica de negócio fica no Domain. Toda orquestração fica no Handler.

```php
public function store(Request $request, CreateExpenseHandler $handler): JsonResponse {
    $handler->handle(new CreateExpenseCommand(
        fileReference: $request->input('file_reference'),
        documentValueCents: $request->integer('document_value_cents'),
        // ...
    ));
    return response()->json(['message' => 'Despesa criada com sucesso.'], 201);
}
```

---

## Glossário Rápido

| Termo | Definição |
|---|---|
| **Aggregate** | Cluster de objetos tratados como unidade transacional |
| **Aggregate Root** | Única entrada do Aggregate; garante consistência |
| **Value Object** | Imutável, sem identidade, comparado por valor |
| **Entity** | Tem identidade própria (ID), pode mudar de estado |
| **Domain Event** | Fato imutável que aconteceu no domínio |
| **Repository** | Abstração de persistência; o Domain conhece apenas a interface |
| **Command** | DTO imutável com primitivos; entrada de um Use Case |
| **Handler** | Orquestra o Use Case; constrói VOs e chama métodos do AR |
| **Bounded Context** | Fronteira explícita de um modelo de domínio |
| **Shared Kernel** | Conceitos compartilhados entre Bounded Contexts |
| **IoC** | Inversão de Controle; container gerencia criação de dependências |
| **PHPStan nível 8** | Análise estática máxima — zero erros obrigatório |
| **Basis Points** | 1 bps = 0.01%; 10000 bps = 100% — evita floats em proporções |
