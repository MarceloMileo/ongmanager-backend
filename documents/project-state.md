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

- **Monólito Modular:** Backend em Laravel 13 (PHP 8.3) com separação estrita de contextos de negócio.
- **Polyrepo:** `ongmanager-backend` (Core + IaC), `ongmanager-web` (React), `ongmanager-mobile` (React Native).
- **IaC:** Terraform para AWS (ECS Fargate, RDS PostgreSQL, VPC, ElastiCache Redis, Amazon MQ).
- **Observabilidade:** OpenTelemetry → Grafana Cloud (Loki, Prometheus, Tempo).
- **Gestão de Segredos:** Mozilla SOPS + AWS KMS (GitOps puro).
- **Análise Estática:** PHPStan nível 8 com Larastan — zero erros obrigatório no pipeline.

### ADR 0002: Arquitetura de Software e DDD

- **Fronteiras de Domínio:** `app/Contexts/{Contexto}/`
- **Módulos:** `SpendManagement`, `BudgetAllocation`, `FiscalCompliance`, `Fundraising`, `ProjectDelivery`
- **Independência de Camadas:** Domain puro, repositórios via interface, Eloquent isolado na Infrastructure, comunicação inter-módulos via Domain Events assíncronos.

### ADR 0003: Estratégia de i18n e L10n

- Middleware global com `Accept-Language`
- Strategy Pattern para regras fiscais por país
- JSONB no PostgreSQL para textos multilíngues
- Todas as datas em UTC no banco

### ADR 0004: Modelagem Tática do SpendManagement

- **`Expense`** — Aggregate Root: `DRAFT → SUBMITTED → APPROVED → PAID / REJECTED`
- **`ExpenseLine`** — Entidade interna, adicionável apenas em `DRAFT`
- **`Receipt`** — VO imutável obrigatório na criação
- **`CostDistribution`** — VO do resultado do rateio com Penny Rounding Rule
- **`ProjectId`**, **`UserId`** — VOs no Shared Kernel
- **Domain Events:** `ExpenseSubmitted`, `ExpenseApproved`, `ExpenseRejected`, `ExpensePaid`

---

## 3. Ambiente de Desenvolvimento

- PHP 8.3, PostgreSQL 16, Redis 7, RabbitMQ 3, Terraform, SOPS, PHPStan 8 + Larastan
- DevContainers para paridade Codespaces/local
- `php artisan serve --host=0.0.0.0 --port=8000` para subir o servidor no Codespaces
- Xdebug presente no ambiente — usar `php -d xdebug.mode=off` para suprimir avisos no tinker

---

## 4. Status de Implementação

### Infraestrutura

| Item | Status |
|---|---|
| Laravel 13 instalado | ✅ |
| PostgreSQL configurado e migrations executadas | ✅ |
| PHPStan nível 8 — zero erros | ✅ |
| Migration `expenses` | ✅ |
| Migration `expense_receipts` | ✅ |
| Migration `personal_access_tokens` (Sanctum via `install:api`) | ✅ |

---

### Shared Kernel

| Componente | Tipo | Status |
|---|---|---|
| `Money` | Value Object | ✅ |
| `ExchangeRate` | Value Object | ✅ |
| `ProjectId` | Value Object | ✅ |
| `UserId` | Value Object | ✅ |
| `Receipt` | Value Object | ✅ |
| `ExpenseStatus` | Backed Enum | ✅ |
| `DistributionType` | Backed Enum | ✅ |

---

### SpendManagement — Domain Layer

| Componente | Tipo | Status |
|---|---|---|
| `ExpenseLine` | Entidade | ✅ |
| `Expense` | Aggregate Root | ✅ — state machine completa: `reject()` e `pay()` implementados |
| `CostDistribution` | Value Object | ✅ |
| `IExpenseRepository` | Interface | ✅ |
| `ExpenseSubmitted` | Domain Event | ✅ |
| `ExpenseApproved` | Domain Event | ✅ |
| `ExpenseRejected` | Domain Event | ✅ — carrega `reason` |
| `ExpensePaid` | Domain Event | ✅ |

**Invariantes do `Expense`:**
- UUID válido, `totalAmount > 0`, moeda `totalAmount` == moeda `Receipt`
- `addLine()` só em `DRAFT`
- `submit()` exige ao menos uma `ExpenseLine`
- `approve()` / `reject()` — `approverId` ≠ `submitterId`
- `reject()` exige `reason` não vazio
- `pay()` — `payerId` ≠ `submitterId`

**State Machine:**
```
DRAFT → SUBMITTED → APPROVED → PAID
                 ↘ REJECTED
```

---

### SpendManagement — Application Layer

| Use Case | Status |
|---|---|
| `CreateExpense` (Command + Handler) | ✅ |
| `SubmitExpense` (Command + Handler) | ✅ |
| `ApproveExpense` (Command + Handler) | ✅ |
| `RejectExpense` (Command + Handler) | ✅ |
| `PayExpense` (Command + Handler) | ✅ |
| `AddExpenseLine` (Command + Handler) | 🔜 Próximo |

---

### SpendManagement — Infrastructure Layer

| Componente | Status |
|---|---|
| `ExpenseModel` (Eloquent) | ✅ |
| `ReceiptModel` (Eloquent) | ✅ |
| `EloquentExpenseRepository` | ✅ — salva `Expense` + `Receipt`, reconstrói via `toDomain()` |
| `SpendManagementServiceProvider` | ✅ — binding `IExpenseRepository → EloquentExpenseRepository` |

---

### SpendManagement — HTTP Layer

| Componente | Status |
|---|---|
| `ExpenseController` | ✅ — `store`, `submit`, `approve`, `reject`, `pay` |
| `routes/api.php` | ✅ — 5 endpoints registrados via `php artisan install:api` |
| Endpoint `addLine` | 🔜 Próximo |

**Endpoints disponíveis:**
```
POST /api/expenses
POST /api/expenses/{id}/submit
POST /api/expenses/{id}/approve
POST /api/expenses/{id}/reject
POST /api/expenses/{id}/pay
```

**Validado com Postman:**
- `POST /api/expenses` → 201 `{"message": "Despesa criada com sucesso."}` ✅
- `Expense` + `Receipt` persistidos no PostgreSQL confirmado ✅
- Domínio protegendo invariantes (submit sem linhas lança exceção corretamente) ✅

---

### Pendências do SpendManagement

| Item | Status |
|---|---|
| `AddExpenseLine` Use Case (Command + Handler) | 🔜 Próximo |
| Migration `expense_lines` | 🔜 Próximo |
| `ExpenseLineModel` (Eloquent) | 🔜 Próximo |
| Endpoint `POST /api/expenses/{id}/lines` | 🔜 Próximo |
| Form Requests (validação HTTP) | ⏳ A seguir |
| `CostDistributionCalculator` (Domain Service) | ⏳ A seguir |

---

## 5. Convenções e Padrões

- **TDD obrigatório** — Red → Green → Refactor
- **Testes em inglês**, um motivo de falha por teste, `setUp()` para repetição
- **Mensagens de exceção em português**
- **Commits semânticos:** `feat`, `fix`, `test`, `refactor`, `docs`, `chore`, `style`
- **PHPStan nível 8** — zero erros, arrays tipados com `@return array<Type>`
- **Comparação de VOs via `equals()`** — nunca `===`
- **Commands são DTOs** — recebem primitivos, nunca objetos de domínio
- **`foreignUuid()`** para FK UUID no Laravel + PostgreSQL
- **`migrate:fresh`** em dev, **`migrate`** em produção
- **IoC via ServiceProvider** — `IExpenseRepository → EloquentExpenseRepository`

---

## 6. Próximos Passos

1. **`AddExpenseLine`** — Command, Handler, migration `expense_lines`, `ExpenseLineModel`, endpoint `POST /api/expenses/{id}/lines`
2. **Form Requests** — validação HTTP para todos os endpoints
3. **Frontend** — iniciar `ongmanager-web` em React após fechar o `SpendManagement`
4. **`CostDistributionCalculator`** — Domain Service com Penny Rounding Rule
5. **README** ✅ | **LICENSE** ✅ (CC BY-NC 4.0)
