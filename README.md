<div align="center">

# ONGManager — Backend

**Plataforma SaaS B2B para Gestão, Governança e Transparência no Terceiro Setor**

[![Status](https://img.shields.io/badge/status-em%20desenvolvimento%20ativo-brightgreen)](https://github.com/MarceloMileo/ongmanager-backend)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![License](https://img.shields.io/badge/license-CC%20BY--NC%204.0-lightgrey)](./LICENSE)

[LinkedIn](https://www.linkedin.com/in/marcelo-miléo-54187521/) · [Reportar um Bug](https://github.com/MarceloMileo/ongmanager-backend/issues)

</div>

---

## Sobre o Projeto

O **ONGManager** é uma plataforma SaaS B2B internacional voltada para o Terceiro Setor, projetada para resolver dores reais de governança financeira, compliance fiscal multinacional e transparência para doadores.

O sistema foi concebido como um produto real — não um projeto de estudo genérico — com foco em:

- **Precisão financeira absoluta** — motor de rateio multimoeda com algoritmo de Penny Rounding, eliminando erros de ponto flutuante em transações contábeis
- **Compliance multinacional nativo** — suporte a MROSC (Brasil) e Ley de Donaciones/SII (Chile) com isolamento de regras fiscais por país via Strategy Pattern
- **Auditoria em tempo real** — rastreabilidade completa de despesas e fundos restritos com trilha imutável
- **Transparência para doadores** — Portal de Impacto com métricas de eficiência operacional e valoração econômica do voluntariado

---

## Arquitetura e Decisões Técnicas

Este projeto aplica na prática um conjunto de práticas e padrões de engenharia de software que raramente coexistem em projetos reais do setor:

### Domain-Driven Design (DDD)
O código é organizado em **Bounded Contexts** estritamente isolados sob `app/Contexts/`, com camadas de Domínio, Aplicação e Infraestrutura bem definidas. O domínio é **PHP puro** — zero dependências do Laravel nas regras de negócio.

```
app/Contexts/
├── Shared/                  # Shared Kernel — VOs reutilizáveis
│   └── Domain/ValueObjects/ # Money, ExchangeRate, ProjectId, UserId...
├── SpendManagement/         # Gestão de Gastos e Reembolsos
├── BudgetAllocation/        # Execução Orçamentária e Bloqueios
├── FiscalCompliance/        # Auditoria e Compliance Fiscal
├── Fundraising/             # Captação e Doações
└── ProjectDelivery/         # Operações e Beneficiários
```

### Test-Driven Development (TDD)
Todo o código de domínio é desenvolvido seguindo o ciclo **Red → Green → Refactor**. Os testes são escritos antes da implementação, garantindo que cada classe nasce com cobertura completa e comportamento documentado.

### Decisões Arquiteturais (ADRs)
Todas as decisões relevantes de arquitetura estão documentadas em **Architecture Decision Records** na pasta `documents/`, incluindo justificativas, consequências e trade-offs.

---

## Stack Técnica

| Camada | Tecnologia |
|---|---|
| **Backend** | PHP 8.3 + Laravel 13 |
| **Banco de Dados** | PostgreSQL 16 (Schema-per-Tenant para isolamento multitenant) |
| **Cache / Filas** | Redis 7 + RabbitMQ 3 |
| **Infraestrutura** | Docker + DevContainers + Terraform (AWS) |
| **Observabilidade** | OpenTelemetry → Grafana Cloud (Loki, Prometheus, Tempo) |
| **Segredos** | Mozilla SOPS + AWS KMS (GitOps puro) |
| **Testes** | PHPUnit via Laravel Test Runner |

---

## Ambiente de Desenvolvimento

### Pré-requisitos

- [Docker](https://www.docker.com/) instalado
- [VS Code](https://code.visualstudio.com/) com a extensão [Dev Containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers)

### Inicialização

```bash
# 1. Clone o repositório
git clone https://github.com/MarceloMileo/ongmanager-backend.git
cd ongmanager-backend

# 2. Abra no VS Code e selecione "Reopen in Container"
# O ambiente Docker será construído automaticamente

# 3. Configure a aplicação
cp .env.example .env
php artisan config:clear
php artisan key:generate

# 4. Execute as migrações
php artisan migrate
```

### Executando os Testes

```bash
# Toda a suíte
php artisan test

# Apenas testes de domínio
php artisan test --filter MoneyTest
php artisan test --filter ExpenseTest

# Com cobertura de código (requer pcov ou xdebug)
./vendor/bin/phpunit --coverage-text
```

---

## Status de Implementação

### Shared Kernel

| Componente | Tipo | Status |
|---|---|---|
| `Money` | Value Object | ✅ Concluído |
| `ExchangeRate` | Value Object | ✅ Concluído |
| `ProjectId` | Value Object | ✅ Concluído |
| `UserId` | Value Object | ✅ Concluído |
| `Receipt` | Value Object | ✅ Concluído |
| `ExpenseStatus` | Backed Enum | ✅ Concluído |
| `DistributionType` | Backed Enum | ✅ Concluído |

### SpendManagement

| Componente | Tipo | Status |
|---|---|---|
| `ExpenseLine` | Entidade | ✅ Concluído |
| `Expense` | Aggregate Root | 🔄 Em andamento |
| `CostDistribution` | Value Object | ⏳ Pendente |
| `IExpenseRepository` | Interface | ⏳ Pendente |

### Bounded Contexts

| Contexto | Status |
|---|---|
| `SpendManagement` | 🔄 Em andamento |
| `BudgetAllocation` | ⏳ Pendente |
| `FiscalCompliance` | ⏳ Pendente |
| `Fundraising` | ⏳ Pendente |
| `ProjectDelivery` | ⏳ Pendente |

---

## Documentação

- [`documents/ADR_0001`](./documents/ADR_0001-base-architectory-and-infrastructure.md) — Arquitetura e Infraestrutura Base
- [`documents/ADR_0002`](./documents/ADR_0002-ddd-modular-monolith-structure.md) — Estrutura de Bounded Contexts (DDD)
- [`documents/ADR_0003`](./documents/ADR_0003-internatiolization-and-location-strategy.md) — Estratégia de i18n e L10n
- [`documents/ADR_0004`](./documents/ADR_0004-spend-management-tactical-design.md) — Modelagem Tática do SpendManagement

---

## Autor

**Marcelo Mileo**
Desenvolvedor PHP com foco em arquitetura de software, DDD e engenharia de produto.

[![LinkedIn](https://img.shields.io/badge/LinkedIn-Marcelo%20Mileo-0077B5?logo=linkedin&logoColor=white)](https://www.linkedin.com/in/marcelo-miléo-54187521/)

---

## Licença

Este projeto está licenciado sob [CC BY-NC 4.0](./LICENSE) — uso não comercial permitido com atribuição. Para licenciamento comercial, entre em contato via LinkedIn.
