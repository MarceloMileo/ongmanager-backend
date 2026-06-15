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

Classe `final readonly` imutável que representa a identidade única de um Projeto. Utilizada como referência entre Bounded Contexts (`SpendManagement` → `ProjectDelivery`) sem acoplamento direto de classes, conforme ADR-0004. Utiliza `Ramsey\Uuid` (dependência nativa do Laravel) para geração e validação de UUIDs v4.

**Operações implementadas:** `generate(): self` (static factory method), `toString(): string`, `equals(ProjectId): bool`.

**Invariantes do construtor:**
- Valor deve ser um UUID válido (validado via `Uuid::isValid()`)
- UUID normalizado para lowercase internamente

**Testes:** `tests/Unit/Shared/Domain/ValueObjects/ProjectIdTest.php` — ✅ 100% de cobertura.
Primeiro ciclo TDD completo (Red → Green → Refactor) do projeto.

---

#### ✅ `ExpenseStatus` — `app/Contexts/Shared/Domain/ValueObjects/ExpenseStatus.php`

Backed Enum (`string`) que representa os estados do ciclo de vida de uma despesa.

**Casos:** `DRAFT = 'draft'`, `SUBMITTED = 'submitted'`, `APPROVED = 'approved'`, `REJECTED = 'rejected'`, `PAID = 'paid'`.

**Por que Backed Enum e não Enum puro:** o valor precisa cruzar fronteiras (banco via Eloquent cast, eventos via serialização JSON). `ExpenseStatus::from('draft')` reconstrói o estado a partir do banco de forma trivial.

**Testes:** `tests/Unit/Shared/Domain/ValueObjects/ExpenseStatusTest.php` — ✅ 100% de cobertura.
Casos cobertos: valor correto para cada caso, reconstrução via `from()`, rejeição de valor inválido com `\ValueError`.

---

#### ✅ `Receipt` — `app/Contexts/Shared/Domain/ValueObjects/Receipt.php`

Classe `final readonly` imutável que representa o comprovante fiscal anexado a uma despesa. Obrigatório na criação de uma `Expense` — um comprovante é um fato histórico imutável.

**Atributos obrigatórios:** `fileReference` (string), `documentValue` (Money > 0).

**Atributos opcionais:** `documentNumber` (?string), `issuerIdentifier` (?string), `issuedAt` (?DateTimeImmutable UTC).

**Invariantes do construtor:**
- `fileReference` não pode ser vazio
- `documentValue` deve ser maior que zero (comprovante com valor zero ou negativo não faz sentido fiscal)
- `issuedAt`, quando fornecido, deve estar em UTC (ADR-003)
- Validação de filesystem (arquivo existe) é responsabilidade da camada de Infrastructure, não do domínio

**Testes:** `tests/Unit/Shared/Domain/ValueObjects/ReceiptTest.php` — ✅ 100% de cobertura.
Casos cobertos: criação com obrigatórios, rejeição de fileReference vazio, rejeição de valor negativo, rejeição de valor zero, opcionais null por padrão, opcionais fornecidos, imutabilidade.

---

### SpendManagement — Em progresso

| Componente | Tipo | Status |
|---|---|---|
| `ExpenseStatus` | Enum | ✅ Concluído |
| `Receipt` | Value Object | ✅ Concluído |
| `ExpenseLine` | Entidade | 🔜 Próximo |
| `Expense` | Aggregate Root | ⏳ A seguir |
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

## 5. Convenções e Padrões Estabelecidos no Código

- **TDD obrigatório** — testes escritos antes da implementação (Red → Green → Refactor)
- **Testes escritos em inglês** (nomes de métodos e asserções)
- **Um único motivo de falha por teste** — asserções de comportamento e imutabilidade em testes separados
- **Helper methods privados nos testes** para reduzir repetição (ex: `utcDate()`)
- **Comentários de domínio nos testes** — testes que cobrem comportamentos não-óbvios devem ter docblock explicando o porquê no contexto do negócio
- **Mensagens de exceção em português** — todas as exceções de domínio são lançadas em pt-BR
- **Commits atômicos e semânticos:** `feat`, `fix`, `test`, `refactor`, `docs`, `chore` — testes e implementação em commits separados
- **Self-imports desnecessários removidos** — classes do mesmo namespace não precisam de `use`
- **Getters com prefixo `get`** — padrão adotado em todo o projeto (ex: `getAmountInCents()`, `getFileReference()`)

---

## 6. Próximos Passos de Engenharia

1. **`ExpenseLine`** — primeira Entidade do projeto; tem ID próprio (UUID), aponta para `ProjectId`, suporta rateio `FIXED_AMOUNT` ou `PERCENTAGE`, pode carregar `ExchangeRate` quando multimoeda
2. **`Expense`** — Aggregate Root com state machine e invariantes de domínio
3. **`CostDistribution`** — Value Object de resultado do rateio com Penny Rounding Rule
4. **`IExpenseRepository`** — Interface de repositório na camada de Domain
