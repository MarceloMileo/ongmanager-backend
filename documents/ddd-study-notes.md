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
| Value Objects | ✅ Estudado e aplicado | `Money`, `ExchangeRate`, `ProjectId`, `UserId`, `Receipt` implementados |
| Backed Enums | ✅ Estudado e aplicado | `ExpenseStatus`, `DistributionType` implementados |
| Entities | ✅ Concluído | `ExpenseLine` implementada com getters, equals e métodos de alteração |
| Aggregate Root | ✅ Concluído | `Expense` implementada com state machine e invariantes de domínio |
| Domain Events | 🔜 Próximo | `ExpenseSubmitted`, `ExpenseApproved`, etc. |
| Repositories | 🔜 Próximo | Interface no Domain, implementação no Infrastructure |
| Bounded Contexts | 🔄 Em andamento | Separação `SpendManagement` → `ProjectDelivery` via `ProjectId` aplicada |
| Domain Services | ⏳ Não iniciado | |
| Application Services / Use Cases | ⏳ Não iniciado | |
| CQRS | ⏳ Não iniciado | |
| Saga / Process Manager | ⏳ Não iniciado | |

---

## 3. Value Objects

### O que é
Um Value Object é um objeto que representa um conceito do domínio definido apenas pelos seus **atributos** — não tem identidade própria. Dois Value Objects com os mesmos atributos são considerados iguais.

### Características obrigatórias
- **Imutável:** nunca muda após a criação. Operações retornam novas instâncias.
- **Sem identidade:** não tem ID. A igualdade é definida pelos valores.
- **Auto-validante:** o construtor garante que um VO inválido nunca existe.
- **PHP puro no domínio:** sem dependências de framework.

### Exemplos aplicados no ONGManager
- `Money` — valor em centavos + moeda ISO 4217
- `ExchangeRate` — taxa de conversão entre duas moedas em uma data UTC
- `ProjectId` — wrapper de UUID para referência entre Bounded Contexts
- `UserId` — wrapper de UUID para identificar usuários (submissor/aprovador)
- `Receipt` — comprovante fiscal imutável com arquivo obrigatório e valor positivo

### Padrões aprendidos na prática
- Usar `final readonly` no PHP 8.2+ para garantir imutabilidade pelo runtime
- Armazenar valores monetários como `int` (centavos) — nunca `float`
- Armazenar taxas de câmbio como `string` para usar `bcmath` sem perda de precisão
- Datas em Value Objects devem ser `DateTimeImmutable` — e validadas como UTC (ADR-003)
- Implementar `equals()` em todo Value Object para comparação semântica
- Self-imports desnecessários: classes no mesmo namespace não precisam de `use`
- Atributos opcionais: `?Type $param = null` — sempre no final do construtor

### Decisão de design: VO genérico vs específico
Quando um conceito pode ter múltiplos papéis dependendo do contexto, um VO genérico é preferível:
```
// ❌ Desnecessariamente específico
SubmitterId, ApproverId — mas ambos são apenas UUIDs de usuários

// ✅ Genérico e reutilizável
UserId — o papel (submissor/aprovador) é definido pelo contexto de uso
```

---

## 4. Backed Enums

### O que é
Um Backed Enum é um Enum nativo do PHP 8.1+ onde cada caso tem um valor escalar associado (`string` ou `int`).

### Enum puro vs Backed Enum

| | Enum Puro | Backed Enum |
|---|---|---|
| Valor associado | ❌ Não tem | ✅ String ou int |
| Persiste no banco | ❌ Precisa conversão manual | ✅ Direto via Eloquent cast |
| Serializa em JSON | ❌ Problemático | ✅ Via `->value` |
| Reconstrói do banco | ❌ Manual | ✅ `Enum::from('valor')` |

### Regra prática
> Se o valor precisa **cruzar uma fronteira** (banco, API, evento, fila) → use **Backed Enum**.

### Padrões aprendidos na prática
- `Enum::from('valor')` — lança `\ValueError` se inválido
- `Enum::tryFrom('valor')` — retorna `null` se inválido
- Enums reutilizáveis entre contextos pertencem ao **Shared Kernel**

---

## 5. Entities

### O que é
Uma Entidade é um objeto que tem **identidade própria** — ela é rastreada ao longo do tempo mesmo que seus atributos mudem.

### Características
- Tem um **ID único e imutável** (geralmente UUID)
- Pode **mudar de estado** ao longo do tempo
- A igualdade é definida pelo ID, não pelos atributos
- **Não usa `readonly`** — precisa poder alterar atributos

### Diferença prática entre Entidade e Value Object

| | Value Object | Entidade |
|---|---|---|
| Identidade | Pelos valores | Pelo ID (UUID) |
| Imutabilidade | Total (`final readonly`) | Parcial — estado pode mudar |
| Igualdade | `equals()` compara atributos | `equals()` compara IDs |
| Exemplo no projeto | `Money(100, 'BRL')` | `ExpenseLine(uuid, projectId, amount)` |

### Padrões aprendidos na prática
- ID sempre validado no construtor via `Uuid::isValid()` + normalizado para lowercase
- `equals()` compara `$this->id === $other->getId()` — nunca os atributos
- Validações que dependem de contexto externo ficam no **Aggregate Root**
- Value Objects ricos se auto-validam — a Entidade não precisa revalidar
- Validações repetidas extraídas para métodos privados (`assertValidAmount()`) — DRY
- Métodos de alteração aplicam as mesmas invariantes do construtor

---

## 6. Aggregate Root

### O que é
Um Aggregate é um **cluster de entidades e value objects** tratado como uma única unidade para fins de consistência. O **Aggregate Root** é a entidade principal que controla o acesso a todo o cluster.

### Regras fundamentais
- Objetos externos só podem referenciar o Aggregate Root — nunca entidades internas diretamente
- Toda modificação dentro do aggregate passa pelo Root
- O Root garante as **invariantes**
- Um aggregate é a **fronteira de transação** — tudo dentro dele é salvo atomicamente

### O Aggregate Root é responsável pela sua própria aprovação?
**Sim.** No DDD, o Aggregate Root é o guardião de todas as suas invariantes — incluindo aprovação. A `Expense` conhece o `submitterId` e pode validar que o aprovador é diferente. Objetos externos nunca deveriam mudar o estado diretamente:

```php
// ✅ A Expense controla sua própria aprovação
$expense->approve($approverId);

// ❌ Viola o encapsulamento — estado mudado de fora
$expense->setStatus(ExpenseStatus::APPROVED);
```

### Invariantes implementadas no `Expense`
- `id` deve ser UUID válido
- `totalAmount` deve ser maior que zero
- Moeda do `totalAmount` deve ser igual à moeda do `Receipt.documentValue` — rastreabilidade fiscal
- `addLine()` só permitido em `DRAFT`
- `submit()` exige ao menos uma `ExpenseLine`
- `approve()` — `approverId` ≠ `submitterId` (segregação de papéis para compliance)
- Comparação de `UserId` via `equals()` — nunca `===` entre objetos

### State Machine do `Expense`
```
DRAFT → SUBMITTED → APPROVED → PAID
                 ↘ REJECTED
```

### Decisões de design do `Expense`

| Decisão | Escolha | Justificativa |
|---|---|---|
| Construtor recebe `status`? | ❌ Não | Status inicial sempre `DRAFT` — definido internamente |
| `ExpenseLine` obrigatória na criação? | ❌ Não | Começa com array vazio, adicionada via `addLine()` |
| `approverId` na criação? | ❌ Não | Definido no momento da aprovação |
| Quem valida moeda da `ExpenseLine`? | ✅ `Expense` | Ela conhece a moeda base |
| Quem valida moeda do `Receipt` vs `totalAmount`? | ✅ `Expense` (construtor) | Ambos disponíveis na criação |

---

## 7. Domain Events

### O que é
Um Domain Event é um fato que aconteceu no domínio. É **imutável** e nomeado sempre no **passado**.

### Eventos mapeados no ONGManager — `SpendManagement`
- `ExpenseSubmitted`, `ExpenseApproved`, `ExpenseRejected`, `ExpensePaid`

---

## 8. Bounded Contexts

### Regras de comunicação (ADR-002)
- Contextos **nunca** acessam classes internas de outros contextos diretamente
- Comunicação via **Domain Events assíncronos** (RabbitMQ/Redis)
- Referências entre contextos usam apenas **IDs**

### Contextos mapeados no ONGManager
- `SpendManagement`, `BudgetAllocation`, `FiscalCompliance`, `Fundraising`, `ProjectDelivery`

---

## 9. Padrões de Design Aprendidos

### Static Factory Method vs Factory Pattern

| | Static Factory Method | Factory Pattern |
|---|---|---|
| O que é | Método estático que cria instâncias | Classe dedicada à criação |
| Quando usar | Criação simples, sem dependências | Criação complexa, com dependências |
| Exemplo | `ProjectId::generate()` | `ExpenseFactory` |

### Responsabilidade de validação por camada

| Validação | Responsável |
|---|---|
| Formato de UUID | Entidade / VO no construtor |
| Valor monetário positivo | Entidade / VO no construtor |
| Moeda Receipt == moeda totalAmount | Aggregate Root (`Expense` construtor) |
| Arquivo existe no disco | Infrastructure |
| Moeda base compatível com ExchangeRate | Aggregate Root (`Expense.addLine()`) |
| Projeto existe e está ativo | Application Layer via `IProjectValidator` |

### setUp() no PHPUnit

Executado **antes de cada teste** — instância sempre fresca, sem estado compartilhado. Propriedades de suporte (ex: `$projectId`, `$receipt`) também podem ser extraídas para a classe do teste.

---

## 10. Git — Boas Práticas

### Commits semânticos
| Prefixo | Quando usar |
|---|---|
| `feat` | Nova funcionalidade |
| `fix` | Correção de bug |
| `test` | Adição ou correção de testes |
| `refactor` | Reestruturação sem mudar comportamento |
| `style` | Formatação, imports, espaços |
| `docs` | Documentação |
| `chore` | Manutenção sem tocar código da aplicação |

### Corrigir commit antes do push
```bash
git commit --amend -m "mensagem corrigida"
```
Após o push, não usar `force push` em branches compartilhadas.

---

## 11. Dúvidas e Descobertas

| # | Dúvida / Descoberta | Status |
|---|---|---|
| 1 | Valores negativos em `Money` são válidos? | ✅ Sim — representam estornos contábeis |
| 2 | Por que armazenar taxa de câmbio como `string`? | ✅ `bcmath` sem perda de precisão |
| 3 | Por que `DateTimeImmutable` e não `DateTime`? | ✅ `DateTime` é mutável |
| 4 | `ExpenseLine` é entidade ou VO? | ✅ Entidade — tem identidade própria |
| 5 | Como contextos se comunicam sem acoplamento? | ✅ Domain Events + referência por ID |
| 6 | `ProjectId::generate()` é Factory Pattern? | ✅ Não — é static factory method |
| 7 | Enum puro ou Backed Enum para estados? | ✅ Backed Enum quando cruza fronteiras |
| 8 | Validar se arquivo existe no domínio? | ✅ Não — filesystem é infraestrutura |
| 9 | `ExpenseLine` valida compatibilidade de moedas? | ✅ Não — responsabilidade da `Expense` |
| 10 | `?Type` sem `= null` torna o parâmetro opcional? | ✅ Não — `= null` é obrigatório |
| 11 | Validação repetida no construtor e método de alteração? | ✅ Extrair para método privado (DRY) |
| 12 | `setUp()` compartilha estado entre testes? | ✅ Não — executado antes de cada teste |
| 13 | `SubmitterId` e `ApproverId` separados ou `UserId` genérico? | ✅ `UserId` genérico — papel é contextual |
| 14 | `Expense` deve receber `status` no construtor? | ✅ Não — sempre inicia em `DRAFT` internamente |
| 15 | `ExpenseLine` é obrigatória na criação da `Expense`? | ✅ Não — adicionada via `addLine()` após criação |
| 16 | A `Expense` é responsável pela sua própria aprovação? | ✅ Sim — Aggregate Root é guardião de todas as invariantes |
| 17 | Comparar dois `UserId` com `===`? | ✅ Não — `===` compara referência de objetos. Usar `equals()` |
| 18 | Quem valida moeda do `Receipt` vs `totalAmount`? | ✅ `Expense` no construtor — ambos disponíveis na criação |

---

## 12. Referências e Leituras

- **Livro base:** *Domain-Driven Design* — Eric Evans (Livro Azul)
- **Livro prático:** *Implementing Domain-Driven Design* — Vaughn Vernon (Livro Vermelho)
- **Artigo:** Padrão de Alocação Proporcional (Penny Rounding) — Martin Fowler
- **Projeto prático de referência:** `github.com/MarceloMileo/ongmanager-backend`
