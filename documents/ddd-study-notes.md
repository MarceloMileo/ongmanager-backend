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
| Value Objects | ✅ Estudado e aplicado | `Money`, `ExchangeRate`, `ProjectId`, `Receipt` implementados |
| Backed Enums | ✅ Estudado e aplicado | `ExpenseStatus`, `DistributionType` implementados |
| Entities | ✅ Aplicado | `ExpenseLine` implementada — primeira Entidade do projeto |
| Aggregate Root | 🔄 Em estudo | `Expense` mapeada, implementação é o próximo grande passo |
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

### Quando usar
Use Value Object quando o conceito:
- Não precisa ser rastreado individualmente ao longo do tempo
- É definido pelos seus valores (ex: um endereço, uma quantia em dinheiro, uma taxa de câmbio)
- Deve ser substituído inteiro quando muda (não atualizado parcialmente)

### Exemplos aplicados no ONGManager
- `Money` — valor em centavos + moeda ISO 4217
- `ExchangeRate` — taxa de conversão entre duas moedas em uma data UTC
- `ProjectId` — wrapper de UUID para referência entre Bounded Contexts
- `Receipt` — comprovante fiscal imutável com arquivo obrigatório e valor positivo

### Padrões aprendidos na prática
- Usar `final readonly` no PHP 8.2+ para garantir imutabilidade pelo runtime
- Armazenar valores monetários como `int` (centavos) — nunca `float`
- Armazenar taxas de câmbio como `string` para usar `bcmath` sem perda de precisão
- Datas em Value Objects devem ser `DateTimeImmutable` — e validadas como UTC (ADR-003)
- Implementar `equals()` em todo Value Object para comparação semântica
- Self-imports desnecessários: classes no mesmo namespace não precisam de `use`
- Atributos opcionais: `?Type $param = null` — sempre no final do construtor

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
> Enum puro é para lógica interna que nunca sai do PHP.

### Exemplos aplicados no ONGManager
- `ExpenseStatus: string` — estados da despesa persistidos no banco e serializados em eventos
- `DistributionType: string` — tipo de rateio (`fixed_amount`, `percentage`), reutilizável entre contextos

### Padrões aprendidos na prática
- `Enum::from('valor')` — reconstrói o caso a partir de string; lança `\ValueError` se inválido
- `Enum::tryFrom('valor')` — reconstrói ou retorna `null` se inválido (não lança exceção)
- Testes de Enum verificam: valor correto por caso, reconstrução via `from()`, rejeição via `\ValueError`
- Enums reutilizáveis entre contextos pertencem ao **Shared Kernel**

---

## 5. Entities

### O que é
Uma Entidade é um objeto que tem **identidade própria** — ela é rastreada ao longo do tempo mesmo que seus atributos mudem. Dois objetos com os mesmos atributos mas IDs diferentes são entidades distintas.

### Características
- Tem um **ID único e imutável** (geralmente UUID)
- Pode **mudar de estado** ao longo do tempo
- A igualdade é definida pelo ID, não pelos atributos
- Também deve ser PHP puro no domínio
- **Não usa `readonly`** — precisa poder alterar atributos

### Quando usar
Use Entidade quando o conceito:
- Precisa ser rastreado individualmente (tem histórico)
- Muda de estado ao longo do tempo
- Precisa ser referenciado por outros objetos via ID

### Diferença prática entre Entidade e Value Object

| | Value Object | Entidade |
|---|---|---|
| Identidade | Pelos valores | Pelo ID (UUID) |
| Imutabilidade | Total (`final readonly`) | Parcial — estado pode mudar |
| Igualdade | `equals()` compara atributos | `equals()` compara IDs |
| Exemplo no projeto | `Money(100, 'BRL')` | `ExpenseLine(uuid, projectId, amount)` |

### Exemplos aplicados no ONGManager
- `ExpenseLine` — fatia de rateio de uma despesa; tem identidade própria (UUID), pode ser adicionada/removida enquanto `Expense` estiver em `DRAFT`, e seus atributos podem ser alterados via métodos específicos

### Padrões aprendidos na prática
- ID sempre validado no construtor via `Uuid::isValid()` + normalizado para lowercase
- `equals()` compara `$this->id === $other->getId()` — nunca os atributos
- Validações que dependem de contexto externo (ex: moeda base da ONG) ficam no **Aggregate Root**, não na Entidade
- Value Objects ricos se auto-validam — a Entidade não precisa revalidar o que o VO já garante

---

## 6. Aggregate Root

### O que é
Um Aggregate é um **cluster de entidades e value objects** tratado como uma única unidade para fins de consistência. O **Aggregate Root** é a entidade principal que controla o acesso a todo o cluster.

### Regras fundamentais
- Objetos externos só podem referenciar o Aggregate Root — nunca entidades internas diretamente
- Toda modificação dentro do aggregate passa pelo Root
- O Root garante as **invariantes** (regras que nunca podem ser violadas)
- Um aggregate é a **fronteira de transação** — tudo dentro dele é salvo atomicamente

### Exemplos mapeados no ONGManager
- `Expense` é o Aggregate Root do `SpendManagement`
  - Controla o ciclo de vida: `DRAFT → SUBMITTED → APPROVED → PAID / REJECTED`
  - Garante que a soma das `ExpenseLine` iguala o total da despesa
  - Garante que nenhuma linha seja adicionada após o status `DRAFT`
  - Garante que aprovador ≠ submissor (segregação de papéis)
  - Valida compatibilidade de moedas entre `ExpenseLine.amount` e `ExpenseLine.exchangeRate`
  - Emite Domain Events quando muda de estado

---

## 7. Domain Events

### O que é
Um Domain Event é um fato que aconteceu no domínio e que outras partes do sistema podem precisar saber. É **imutável** e nomeado sempre no **passado**.

### Características
- Nome sempre no passado: `ExpenseSubmitted`, `ExpenseApproved`, `UserRegistered`
- Carrega os dados relevantes do momento em que ocorreu
- Publicado pelo Aggregate Root após uma mudança de estado
- Consumido assincronamente por outros Bounded Contexts (via RabbitMQ no ONGManager)

### Eventos mapeados no ONGManager — `SpendManagement`
- `ExpenseSubmitted` — disparado quando um colaborador submete uma despesa para aprovação
- `ExpenseApproved` — disparado quando o gestor aprova
- `ExpenseRejected` — disparado quando o gestor rejeita
- `ExpensePaid` — disparado quando o pagamento é confirmado

---

## 8. Bounded Contexts

### O que é
Um Bounded Context é uma **fronteira explícita** dentro da qual um modelo de domínio específico é definido e aplicado. O mesmo termo pode ter significados diferentes em contextos distintos.

### Regras de comunicação entre contextos (ONGManager — ADR-002)
- Contextos **nunca** acessam tabelas ou classes internas de outros contextos diretamente
- A comunicação é feita via **Domain Events assíncronos** (RabbitMQ/Redis)
- Referências entre contextos usam apenas **IDs** — nunca objetos completos

### Contextos mapeados no ONGManager
- `SpendManagement` — Gestão de Gastos e Reembolsos
- `BudgetAllocation` — Execução Orçamentária e Bloqueios
- `FiscalCompliance` — Auditoria e Localizações Fiscais
- `Fundraising` — Captação e Doações
- `ProjectDelivery` — Operações e Beneficiários

### Resolução aplicada
`ExpenseLine` referencia projetos do `ProjectDelivery` via `ProjectId` (UUID wrapper no Shared Kernel) — sem importar classes do outro contexto, conforme ADR-002.

---

## 9. Padrões de Design Aprendidos

### Static Factory Method vs Factory Pattern

| | Static Factory Method | Factory Pattern |
|---|---|---|
| O que é | Método estático que cria instâncias | Classe dedicada à criação |
| Quando usar | Criação simples, sem dependências externas | Criação complexa, com dependências ou variações |
| Exemplo | `ProjectId::generate()` | `ExpenseFactory` com regras complexas |

> `ProjectId::generate()` é um **static factory method** — não confundir com o Factory Pattern (GoF).

### Responsabilidade de validação por camada

| Validação | Responsável |
|---|---|
| Formato de UUID | Entidade / VO no construtor |
| Valor monetário positivo | Entidade / VO no construtor |
| Arquivo existe no disco | Infrastructure |
| Moeda base compatível com ExchangeRate | Aggregate Root (`Expense`) |
| Projeto existe e está ativo | Application Layer via `IProjectValidator` |

---

## 10. Dúvidas e Descobertas

| # | Dúvida / Descoberta | Status |
|---|---|---|
| 1 | Valores negativos em `Money` são válidos? | ✅ Sim — representam estornos contábeis |
| 2 | Por que armazenar taxa de câmbio como `string`? | ✅ Para usar `bcmath` sem perda de precisão de ponto flutuante |
| 3 | Por que `DateTimeImmutable` e não `DateTime`? | ✅ `DateTime` é mutável — viola imutabilidade do VO |
| 4 | `ExpenseLine` é entidade ou VO? | ✅ Entidade — tem identidade e pode ser adicionada/removida individualmente |
| 5 | Como contextos se comunicam sem acoplamento? | ✅ Via Domain Events assíncronos + referência por ID (ProjectId) |
| 6 | `ProjectId::generate()` é Factory Pattern? | ✅ Não — é static factory method. Factory Pattern é uma classe dedicada para criação complexa |
| 7 | Enum puro ou Backed Enum para estados? | ✅ Backed Enum quando o valor cruza fronteiras (banco, API, eventos) |
| 8 | Validar se arquivo existe no domínio? | ✅ Não — filesystem é infraestrutura. Domínio só valida que a referência não é vazia |
| 9 | `ExpenseLine` valida compatibilidade de moedas? | ✅ Não — é responsabilidade da `Expense` (Aggregate Root) que conhece a moeda base da ONG |
| 10 | `?Type` sem `= null` torna o parâmetro opcional? | ✅ Não — `?Type` aceita null mas ainda exige que seja passado. `= null` torna verdadeiramente opcional |

---

## 11. Referências e Leituras

- **Livro base:** *Domain-Driven Design* — Eric Evans (Livro Azul)
- **Livro prático:** *Implementing Domain-Driven Design* — Vaughn Vernon (Livro Vermelho)
- **Artigo:** Padrão de Alocação Proporcional (Penny Rounding) — Martin Fowler
- **Projeto prático de referência:** `github.com/MarceloMileo/ongmanager-backend`
