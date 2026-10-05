# ONGManager - Estado Atual do Projeto e Guia de Restauração (Restore Point)

> Este documento funciona como a "verdade absoluta" do projeto ONGManager Backend. Ele deve ser mantido atualizado a cada evolução técnica. Caso o contexto de desenvolvimento ou a sessão de IA sejam perdidos, a leitura deste arquivo é suficiente para restaurar o estado exato da aplicação e continuar a engenharia de software de onde paramos.

---

## 1. Visão Geral do Produto e Mercado

**Produto:** ONGManager B2B SaaS (Produção/Enterprise-ready, pronto para o mercado).

**Foco:** Gestão, governança e eficiência operacional para o Terceiro Setor.

**Compliance:** MROSC (Brasil) e SII (Chile).

**Diferenciais:** Engine de rateio multimoeda com consistência matemática absoluta, rastreabilidade de doações "carimbadas" (fundo restrito), auditoria em tempo real, controle de voluntariado e mensuração de impacto social convertido em unidades financeiras e operacionais.

---

## 2. Decisões Arquiteturais Consolidadas (ADRs)

### ADR 0001: Infraestrutura e Topologia

- **Monólito Modular:** Backend em Laravel 13 (PHP 8.3) com separação estrita de contextos de negócio (Namespaces dedicados) em vez de microsserviços físicos prematuros.
- **Polyrepo:** Divisão rígida no GitHub Pro:
  - `ongmanager-backend` (Este repositório — Core contábil + IaC)
  - `ongmanager-web` (React SaaS Frontend)
  - `ongmanager-mobile` (React Native app offline-first)
- **Infraestrutura como Código (IaC):** Terraform para provisionamento automatizado AWS (ECS Fargate, RDS PostgreSQL, VPC, ElastiCache Redis, Amazon MQ).
- **Observabilidade:** OpenTelemetry (OTel) + Loki/Prometheus/Tempo integrados ao Grafana Cloud.
- **Gestão de Segredos:** Mozilla SOPS integrado com AWS KMS (segredos cifrados diretamente no repositório, garantindo GitOps puro).
- **Análise Estática:** PHPStan nível 8 com Larastan — zero erros obrigatório no pipeline.

### ADR 0002: Arquitetura de Software e DDD

- **Fronteiras de Domínio:** Organização física sob `app/Contexts/{Contexto}/`.
- **Módulos Iniciais Mapeados:**
  - `SpendManagement` (Gestão de Gastos e Reembolsos)
  - `BudgetAllocation` (Execução Orçamentária e Bloqueios)
  - `FiscalCompliance` (Auditoria e Localizações Fiscais)
  - `Fundraising` (Captação e Doações)
  - `ProjectDelivery` (Operações e Beneficiários)
- **Independência de Camadas:** Domínio puro (`Domain/`) sem heranças de frameworks. Inversão de dependência usando interfaces de repositórios. Persistência de infraestrutura isolada com Eloquent. Comunicação intermódulos assíncrona por Eventos de Domínio no Redis/RabbitMQ.

### ADR 0003: Estratégia de Internacionalização (i18n) e Localização (L10n)

- **Localização de Respostas da API:** Middleware global lê o cabeçalho `Accept-Language` e configura o locale via Laravel localization.
- **Isolamento de Regras Fiscais:** Strategy Pattern resolvido em tempo de execução com base no país do Tenant (`BrazilianFiscalStrategy`, `ChileanFiscalStrategy`).
- **Persistência de Textos Multilíngues:** Colunas com traduções dinâmicas usam `JSONB` no PostgreSQL. Formato: `{"pt_BR": "...", "es_CL": "...", "en": "..."}`.
- **Fuso Horário Global:** Toda data/hora persistida em UTC no banco. Conversão para horário local resolvida na camada de exibição (client-side).

### ADR 0004: Modelagem Tática do SpendManagement

- **`Expense`** — Aggregate Root com ciclo de vida: `DRAFT → SUBMITTED → APPROVED → PAID / REJECTED`
- **`ExpenseLine`** — Entidade interna do aggregate, adicionável apenas em `DRAFT`
- **`Receipt`** — Value Object imutável obrigatório na criação da `Expense`
- **`CostDistribution`** — Value Object de resultado do rateio com Penny Rounding Rule
- **`ProjectId`** — Value Object no Shared Kernel; referência entre `SpendManagement` e `ProjectDelivery` por ID
- **`UserId`** — Value Object no Shared Kernel; identifica submissor e aprovador da `Expense`
- **Domain Events:** `ExpenseSubmitted`, `ExpenseApproved`, `ExpenseRejected`, `ExpensePaid`

---

## 3. Configuração do Ambiente de Desenvolvimento Local (Docker)

Os arquivos `.devcontainer/Dockerfile`, `.devcontainer/devcontainer.json` e `docker-compose.yml` estão configurados para GitHub Codespaces e máquinas físicas locais. O ambiente contém:

- PHP 8.3 CLI com extensões `pdo_pgsql`, `bcmath`, `zip`, `sockets` e drivers PECL `redis` e `amqp`
- PostgreSQL 16 Alpine
- Redis 7 Alpine
- RabbitMQ 3 Management
- Terraform CLI e Mozilla SOPS CLI pré-instalados
- PHPStan nível 8 + Larastan instalados e configurados

---

## 4. Status de Implementação e Código do Core

### Infraestrutura

| Item | Status |
|---|---|
| Instalação do Laravel 13 | ✅ Concluído |
| Banco de Dados PostgreSQL | ✅ Concluído — migrações executadas e conexão validada |
| PHPStan nível 8 | ✅ Concluído — zero erros |
| Migration `expenses` | ✅ Concluído |
| Migration `expense_receipts` | ✅ Concluído |

---

### Shared Kernel — Value Objects

#### ✅ `Money` — `app/Contexts/Shared/Domain/ValueObjects/Money.php`
Classe `final readonly` imutável. Utiliza `bcmath` e algoritmo de Fowler (`allocate()`).
**Operações:** `add`, `subtract`, `multiply`, `allocate`, `isGreaterThan`, `isLessThan`, `equals`.
**Testes:** ✅ 100% de cobertura.

#### ✅ `ExchangeRate` — `app/Contexts/Shared/Domain/ValueObjects/ExchangeRate.php`
Classe `final readonly` imutável. Taxa armazenada como `string` via `bcmath`. Data obrigatoriamente UTC.
**Operações:** `convert(Money): Money`, `equals(ExchangeRate): bool`.
**Testes:** ✅ 100% de cobertura.

#### ✅ `ProjectId` — `app/Contexts/Shared/Domain/ValueObjects/ProjectId.php`
Wrapper de UUID para referência entre Bounded Contexts.
**Operações:** `generate(): self`, `toString(): string`, `equals(ProjectId): bool`.
**Testes:** ✅ 100% de cobertura.

#### ✅ `UserId` — `app/Contexts/Shared/Domain/ValueObjects/UserId.php`
Wrapper de UUID para identificar usuários (submissor/aprovador). Genérico — papel é contextual.
**Operações:** `generate(): self`, `toString(): string`, `equals(UserId): bool`.
**Testes:** ✅ 100% de cobertura.

#### ✅ `ExpenseStatus` — `app/Contexts/Shared/Domain/ValueObjects/ExpenseStatus.php`
Backed Enum (`string`): `DRAFT`, `SUBMITTED`, `APPROVED`, `REJECTED`, `PAID`.
**Testes:** ✅ 100% de cobertura.

#### ✅ `DistributionType` — `app/Contexts/Shared/Domain/ValueObjects/DistributionType.php`
Backed Enum (`string`): `FIXED_AMOUNT`, `PERCENTAGE`.
**Testes:** ✅ 100% de cobertura.

#### ✅ `Receipt` — `app/Contexts/Shared/Domain/ValueObjects/Receipt.php`
Comprovante fiscal imutável. `fileReference` e `documentValue` (Money > 0) obrigatórios.
**Testes:** ✅ 100% de cobertura.

---

### SpendManagement — Domain Layer

#### ✅ `ExpenseLine` — `app/Contexts/SpendManagement/Domain/Entities/ExpenseLine.php`
Entidade de rateio. Getters + `changeAmount()`, `changeProject()`, `changeExchangeRate()`. `equals()` por ID.
**Testes:** ✅ 100% de cobertura.

#### ✅ `Expense` — `app/Contexts/SpendManagement/Domain/Entities/Expense.php`
Aggregate Root. State machine `DRAFT → SUBMITTED → APPROVED`. Invariantes de moeda, segregação de papéis e consistência de linhas. Emite Domain Events via padrão collect-and-publish (`recordEvent` / `pullDomainEvents`).
**Operações:** `addLine()`, `submit()`, `approve(UserId)`, `pullDomainEvents()`.
**Testes:** ✅ 100% de cobertura.

#### ✅ `CostDistribution` — `app/Contexts/SpendManagement/Domain/ValueObjects/CostDistribution.php`
VO do resultado do rateio. `proportionInBasisPoints` (10000 = 100%). `getProportionAsPercentage()` para exibição.
**Testes:** ✅ 100% de cobertura.

#### ✅ `IExpenseRepository` — `app/Contexts/SpendManagement/Domain/Repositories/IExpenseRepository.php`
Interface de repositório. Métodos: `save()`, `findById()`, `delete()`, `findByStatus()`, `findBySubmitter()`.

#### ✅ Domain Events — `app/Contexts/SpendManagement/Domain/Events/`
- `ExpenseSubmitted` — carrega `expenseId`, `submitterId`, `occurredAt`
- `ExpenseApproved` — carrega `expenseId`, `approverId`, `occurredAt`
- `ExpenseRejected` — carrega `expenseId`, `approverId`, `reason`, `occurredAt` ⚠️ método `reject()` pendente no `Expense`
- `ExpensePaid` — carrega `expenseId`, `occurredAt` ⚠️ método `pay()` pendente no `Expense`

---

### SpendManagement — Application Layer

#### ✅ `SubmitExpense` — `app/Contexts/SpendManagement/Application/UseCases/SubmitExpense/`
- `SubmitExpenseCommand` — DTO com `expenseId` e `submitterId`
- `SubmitExpenseHandler` — busca `Expense`, chama `submit()`, salva, coleta eventos

#### ✅ `ApproveExpense` — `app/Contexts/SpendManagement/Application/UseCases/ApproveExpense/`
- `ApproveExpenseCommand` — DTO com `expenseId` e `approverId`
- `ApproveExpenseHandler` — busca `Expense`, chama `approve(UserId)`, salva, coleta eventos

---

### SpendManagement — Infrastructure Layer

#### ✅ `ExpenseModel` — `app/Contexts/SpendManagement/Infrastructure/Models/ExpenseModel.php`
Eloquent Model para tabela `expenses`. UUID como primary key. Relacionamento `hasOne(ReceiptModel)`.

#### ✅ `ReceiptModel` — `app/Contexts/SpendManagement/Infrastructure/Models/ReceiptModel.php`
Eloquent Model para tabela `expense_receipts`. UUID como primary key. Foreign key UUID para `expenses`.

#### ✅ `EloquentExpenseRepository` — `app/Contexts/SpendManagement/Infrastructure/Repositories/EloquentExpenseRepository.php`
Implementação concreta do `IExpenseRepository`. `toDomain()` reconstrói `Expense` + `Receipt` do banco.

---

| Componente | Camada | Status |
|---|---|---|
| `Money`, `ExchangeRate`, `ProjectId`, `UserId` | Shared Kernel | ✅ Concluído |
| `ExpenseStatus`, `DistributionType` | Shared Kernel | ✅ Concluído |
| `Receipt` | Shared Kernel | ✅ Concluído |
| `ExpenseLine`, `Expense` | Domain | ✅ Concluído |
| `CostDistribution` | Domain | ✅ Concluído |
| `IExpenseRepository` | Domain | ✅ Concluído |
| Domain Events (4) | Domain | ✅ Concluído |
| `SubmitExpense`, `ApproveExpense` | Application | ✅ Concluído |
| `ExpenseModel`, `ReceiptModel` | Infrastructure | ✅ Concluído |
| `EloquentExpenseRepository` | Infrastructure | ✅ Concluído |
| `SpendManagementServiceProvider` | Infrastructure | 🔜 Próximo |
| `CostDistributionCalculator` | Domain Service | ⏳ A seguir |
| `reject()` e `pay()` no `Expense` | Domain | ⏳ A seguir |

---

## 5. Convenções e Padrões Estabelecidos no Código

- **TDD obrigatório** — testes escritos antes da implementação (Red → Green → Refactor)
- **Testes escritos em inglês** (nomes de métodos e asserções)
- **Um único motivo de falha por teste**
- **`setUp()` do PHPUnit** — elimina repetição; propriedades de suporte também extraídas
- **Mensagens de exceção em português**
- **Commits atômicos e semânticos:** `feat`, `fix`, `test`, `refactor`, `docs`, `chore`, `style`
- **Self-imports desnecessários removidos** — classes do mesmo namespace não precisam de `use`
- **Getters com prefixo `get`**
- **Parâmetros opcionais sempre no final** com `?Type $param = null`
- **DRY em validações** — extraídas para métodos privados
- **Comparação de VOs sempre via `equals()`** — nunca `===` entre objetos
- **PHPStan nível 8** — zero erros obrigatório; arrays tipados com `@return array<Type>`
- **`migrate:fresh`** em desenvolvimento quando há mudanças de schema
- **`foreignUuid()`** para foreign keys UUID no Laravel + PostgreSQL

---

## 6. Próximos Passos de Engenharia

1. **`SpendManagementServiceProvider`** — registra o binding `IExpenseRepository → EloquentExpenseRepository` no IoC do Laravel
2. **`reject()` e `pay()`** — completar a state machine do `Expense`
3. **`CostDistributionCalculator`** — Domain Service com algoritmo de rateio e Penny Rounding Rule
4. **HTTP Layer** — Controllers, Requests e Routes para expor os Use Cases via API REST
5. **README** — ✅ Adicionado
6. **LICENSE** — ✅ CC BY-NC 4.0
