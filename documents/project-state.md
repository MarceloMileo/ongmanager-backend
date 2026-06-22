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
- **Fuso Horário Global:** Toda data/hora persistida em UTC no banco. Conversão para horário local resolvida na camada de exibição (client-side). **Invariante de domínio:** Value Objects que recebem `DateTimeImmutable` devem validar e rejeitar timezones não-UTC.

### ADR 0004: Modelagem Tática do SpendManagement

- **`Expense`** — Aggregate Root com ciclo de vida: `DRAFT → SUBMITTED → APPROVED → PAID / REJECTED`
- **`ExpenseLine`** — Entidade interna do aggregate, adicionável apenas em `DRAFT`
- **`Receipt`** — Value Object imutável obrigatório na criação da `Expense`
- **`CostDistribution`** — Value Object de resultado do rateio com Penny Rounding Rule
- **`ProjectId`** — Value Object no Shared Kernel; referência entre `SpendManagement` e `ProjectDelivery` por ID (sem acoplamento direto de classes)
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

---

## 4. Status de Implementação e Código do Core

### Infraestrutura

| Item | Status |
|---|---|
| Instalação do Laravel 13 | ✅ Concluído |
| Banco de Dados PostgreSQL | ✅ Concluído — migrações iniciais executadas e conexão validada |

---

### Shared Kernel — Value Objects

#### ✅ `Money` — `app/Contexts/Shared/Domain/ValueObjects/Money.php`

Classe `final readonly` imutável que representa valores monetários em centavos (inteiros). Utiliza `bcmath` para precisão arbitrária e implementa o Algoritmo de Alocação Proporcional de Martin Fowler (`allocate()`).

**Operações implementadas:** `add`, `subtract`, `multiply`, `allocate`, `isGreaterThan`, `isLessThan`, `equals`.

**Invariantes do construtor:**
- Moeda deve ser código ISO 4217 de exatamente 3 caracteres
- Moeda normalizada para uppercase internamente
- Valores negativos são permitidos (representam estornos contábeis)

**Testes:** `tests/Unit/Shared/Domain/ValueObjects/MoneyTest.php` — ✅ 100% de cobertura.

---

#### ✅ `ExchangeRate` — `app/Contexts/Shared/Domain/ValueObjects/ExchangeRate.php`

Classe `final readonly` imutável que representa a taxa de conversão cambial entre duas moedas em uma data específica. Taxa armazenada como `string` para preservar precisão decimal arbitrária via `bcmath`.

**Operações implementadas:** `convert(Money): Money`, `equals(ExchangeRate): bool`.

**Invariantes do construtor:**
- Moedas de origem e destino: código ISO 4217 de 3 caracteres, normalizadas para uppercase
- Moedas de origem e destino não podem ser iguais
- Taxa deve ser maior que zero (validada via `bccomp`)
- **Data deve estar em UTC** — invariante derivado da ADR-003

**Testes:** `tests/Unit/Shared/Domain/ValueObjects/ExchangeRateTest.php` — ✅ 100% de cobertura.

---

#### ✅ `ProjectId` — `app/Contexts/Shared/Domain/ValueObjects/ProjectId.php`

Classe `final readonly` imutável que representa a identidade única de um Projeto. Utilizada como referência entre Bounded Contexts (`SpendManagement` → `ProjectDelivery`) sem acoplamento direto de classes, conforme ADR-0004. Utiliza `Ramsey\Uuid` para geração e validação de UUIDs v4.

**Operações implementadas:** `generate(): self`, `toString(): string`, `equals(ProjectId): bool`.

**Testes:** `tests/Unit/Shared/Domain/ValueObjects/ProjectIdTest.php` — ✅ 100% de cobertura.

---

#### ✅ `UserId` — `app/Contexts/Shared/Domain/ValueObjects/UserId.php`

Classe `final readonly` imutável que representa a identidade única de um Usuário. Utilizada na `Expense` para identificar `submitterId` e `approverId` — garantindo a invariante de segregação de papéis (aprovador ≠ submissor).

**Por que `UserId` genérico e não `SubmitterId`/`ApproverId` separados:** um usuário pode ser submissor em uma despesa e aprovador em outra — o papel é contextual, não intrínseco ao usuário.

**Operações implementadas:** `generate(): self`, `toString(): string`, `equals(UserId): bool`.

**Testes:** `tests/Unit/Shared/Domain/ValueObjects/UserIdTest.php` — ✅ 100% de cobertura.

---

#### ✅ `ExpenseStatus` — `app/Contexts/Shared/Domain/ValueObjects/ExpenseStatus.php`

Backed Enum (`string`) que representa os estados do ciclo de vida de uma despesa.

**Casos:** `DRAFT = 'draft'`, `SUBMITTED = 'submitted'`, `APPROVED = 'approved'`, `REJECTED = 'rejected'`, `PAID = 'paid'`.

**Testes:** `tests/Unit/Shared/Domain/ValueObjects/ExpenseStatusTest.php` — ✅ 100% de cobertura.

---

#### ✅ `DistributionType` — `app/Contexts/Shared/Domain/ValueObjects/DistributionType.php`

Backed Enum (`string`) que representa o tipo de distribuição de custo de uma linha de despesa.

**Casos:** `FIXED_AMOUNT = 'fixed_amount'`, `PERCENTAGE = 'percentage'`.

**Testes:** `tests/Unit/Shared/Domain/ValueObjects/DistributionTypeTest.php` — ✅ 100% de cobertura.

---

#### ✅ `Receipt` — `app/Contexts/Shared/Domain/ValueObjects/Receipt.php`

Classe `final readonly` imutável que representa o comprovante fiscal anexado a uma despesa.

**Atributos obrigatórios:** `fileReference` (string), `documentValue` (Money > 0).

**Atributos opcionais:** `documentNumber` (?string), `issuerIdentifier` (?string), `issuedAt` (?DateTimeImmutable UTC).

**Testes:** `tests/Unit/Shared/Domain/ValueObjects/ReceiptTest.php` — ✅ 100% de cobertura.

---

### SpendManagement

#### ✅ `ExpenseLine` — `app/Contexts/SpendManagement/Domain/Entities/ExpenseLine.php`

Primeira **Entidade** do projeto. Representa uma fatia do rateio de uma despesa apontando para um projeto específico.

**Atributos:**
- `id` — UUID único e imutável
- `projectId` — `ProjectId`
- `amount` — `Money` sempre positivo
- `distributionType` — `DistributionType`
- `exchangeRate` — `?ExchangeRate` opcional

**Operações implementadas:**
- Getters: `getId()`, `getProjectId()`, `getAmount()`, `getDistributionType()`, `getExchangeRate()`
- Alteração: `changeAmount(Money)`, `changeProject(ProjectId)`, `changeExchangeRate(?ExchangeRate)`
- Identidade: `equals(ExpenseLine): bool` — compara pelo **ID**

**Testes:** `tests/Unit/SpendManagement/Domain/Entities/ExpenseLineTest.php` — ✅ 100% de cobertura.
Teste refatorado com `setUp()` e `$projectId` como propriedade da classe.

---

| Componente | Tipo | Status |
|---|---|---|
| `ExpenseStatus` | Enum | ✅ Concluído |
| `DistributionType` | Enum | ✅ Concluído |
| `Receipt` | Value Object | ✅ Concluído |
| `ExpenseLine` | Entidade | ✅ Concluído |
| `Expense` | Aggregate Root | 🔜 Próximo |
| `CostDistribution` | Value Object | ⏳ A seguir |
| `IExpenseRepository` | Interface | ⏳ Não iniciado |

### Demais Bounded Contexts

| Contexto | Status |
|---|---|
| `BudgetAllocation` | ⏳ Não iniciado |
| `FiscalCompliance` | ⏳ Não iniciado |
| `Fundraising` | ⏳ Não iniciado |
| `ProjectDelivery` | ⏳ Não iniciado |

---

## 5. Decisões de Design do `Expense` Aggregate Root

Decisões tomadas em sessão de modelagem — prontas para implementação:

- **Construtor recebe:** `id` (UUID), `receipt` (Receipt), `totalAmount` (Money), `submitterId` (UserId)
- **Status inicial:** sempre `DRAFT` — definido internamente, não recebido como parâmetro
- **`ExpenseLine`:** começa como array vazio — adicionadas via `addLine()` após criação
- **`approverId`:** definido no momento da aprovação, não na criação
- **Invariante de segregação:** `approverId` deve ser diferente de `submitterId`
- **Invariante de consistência:** soma das `ExpenseLine` deve igualar `totalAmount`
- **Invariante de estado:** `addLine()` só permitido em `DRAFT`

---

## 6. Convenções e Padrões Estabelecidos no Código

- **TDD obrigatório** — testes escritos antes da implementação (Red → Green → Refactor)
- **Testes escritos em inglês** (nomes de métodos e asserções)
- **Um único motivo de falha por teste**
- **`setUp()` do PHPUnit** — elimina repetição; propriedades de suporte (ex: `$projectId`) também extraídas
- **Helper methods privados nos testes** (ex: `utcDate()`)
- **Mensagens de exceção em português**
- **Commits atômicos e semânticos:** `feat`, `fix`, `test`, `refactor`, `docs`, `chore`, `style`
- **`git commit --amend`** — corrige último commit antes do push
- **Self-imports desnecessários removidos**
- **Getters com prefixo `get`**
- **Parâmetros opcionais sempre no final** com `?Type $param = null`
- **DRY em validações** — extraídas para métodos privados

---

## 7. Próximos Passos de Engenharia

1. **`Expense`** — Aggregate Root com state machine, invariantes de domínio e emissão de Domain Events
2. **`CostDistribution`** — Value Object de resultado do rateio com Penny Rounding Rule
3. **`IExpenseRepository`** — Interface de repositório na camada de Domain
