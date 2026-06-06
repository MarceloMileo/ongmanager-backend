# ADR 0002: Estrutura de Diretórios para Bounded Contexts (DDD)

**Status:** ✅ Aprovado

---

## Contexto

O Laravel possui uma estrutura de diretórios muito acoplada à arquitetura MVC clássica (`app/Models`, `app/Http/Controllers`). Num ecossistema complexo como o **ONGManager**, misturar lógica contábil, validações fiscais de múltiplos países e dados de voluntários num único namespace global gera acoplamento extremo e viola o princípio da responsabilidade única.

Precisamos de uma estrutura de diretórios modular que separe os nossos *Bounded Contexts* ao mesmo tempo que respeita as convenções de inicialização do Laravel 11.

---

## Decisões

Adotaremos uma arquitetura de Monólito Modular organizada dentro do diretório `app/Contexts/`. Cada contexto delimitado funcionará como um módulo independente estruturado em camadas:

```text
app/
├── Contexts/
│   ├── SpendManagement/               # Bounded Context: Gestão de Gastos e Reembolsos
│   │   ├── Domain/                    # Camada de Domínio (Entidades puras, VO, Aggregates, Events)
│   │   │   ├── Entities/
│   │   │   ├── ValueObjects/
│   │   │   ├── Events/
│   │   │   └── Repositories/          # Interfaces (Contratos) de repositório
│   │   ├── Application/               # Camada de Aplicação (Use Cases, Commands, DTOs)
│   │   │   ├── UseCases/
│   │   │   └── Commands/
│   │   └── Infrastructure/            # Camada de Infraestrutura (Eloquent, SQL, Controllers, APIs)
│   │       ├── Http/
│   │       │   ├── Controllers/
│   │       │   └── Requests/
│   │       ├── Models/                # Modelos Eloquent utilizados apenas para persistência
│   │       ├── Repositories/          # Implementações concretas dos repositórios
│   │       └── Providers/             # Providers para registar serviços específicos do módulo
│   │
│   ├── BudgetAllocation/              # Bounded Context: Execução Orçamental e Bloqueios
│   ├── FiscalCompliance/              # Bounded Context: Auditoria e Localizações Fiscais
│   ├── Fundraising/                   # Bounded Context: Captação e Doações
│   └── ProjectDelivery/               # Bounded Context: Operações e Beneficiários
```

### Regras de Acoplamento e Dependência entre Módulos

- **A Camada de Domínio é Pura:** O código dentro da pasta `Domain/` de qualquer módulo não pode herdar ou depender de classes do ecossistema do Laravel (como Eloquent ou Controllers). É PHP puro.
- **Dependência Invertida:** Os Use Cases na camada de `Application` interagem apenas com as interfaces definidas no `Domain/Repositories/`. A persistência física (Eloquent) vive na `Infrastructure/` e é injetada via IoC.
- **Comunicação Inter-módulos:** A comunicação entre os módulos dar-se-á estritamente através do envio de Eventos de Domínio assíncronos via barramento (Laravel Queue/Redis) para evitar o acoplamento de escrita direta em tabelas alheias.

---

## Consequências

### ✅ Positivas:

- Isolamento completo de regras de negócio específicas (ex: alterações na fiscalidade chilena afetam apenas o módulo `FiscalCompliance`).
- Facilidade de transição: caso um módulo cresça em excesso no futuro, ele poderá ser facilmente extraído para um microsserviço independente, pois as suas fronteiras de código e persistência estão perfeitamente mapeadas.

### ⚠️ Negativas:

- Maior número de classes e complexidade na navegação inicial da árvore de diretórios para programadores habituados ao MVC simples.
