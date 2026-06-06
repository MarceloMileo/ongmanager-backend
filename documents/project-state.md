ONGManager - Estado Atual do Projeto e Guia de Restauração (Restore Point)

Este documento funciona como a "verdade absoluta" do projeto ONGManager Backend. Ele deve ser mantido atualizado a cada evolução técnica. Caso o contexto de desenvolvimento ou a sessão de IA sejam perdidos, a leitura deste arquivo é suficiente para restaurar o estado exato da aplicação e continuar a engenharia de software de onde paramos.

1. Visão Geral do Produto e Mercado

Produto: ONGManager B2B SaaS (Produção/Enterprise-ready, pronto para o mercado).

Foco: Gestão, governança e eficiência operacional para o Terceiro Setor.

Compliance: MROSC (Brasil) e SII (Chile).

Diferenciais: Engine de rateio multimoeda com consistência matemática absoluta, rastreabilidade de doações "carimbadas" (fundo restrito), auditoria em tempo real, controle de voluntariado e mensuração de impacto social convertido em unidades financeiras e operacionais.

2. Decisões Arquiteturais Consolidadas (ADRs)

ADR 0001: Infraestrutura e Topologia

Monólito Modular: Backend em Laravel 11 (PHP 8.3) com separação estrita de contextos de negócio (Namespaces dedicados) em vez de microsserviços físicos prematuros.

Polyrepo: Divisão rígida no GitHub Pro:

ongmanager-backend (Este repositório - Core contábil + IaC).

ongmanager-web (React SaaS Frontend).

ongmanager-mobile (React Native app offline-first).

Infraestrutura como Código (IaC): Terraform para provisionamento automatizado AWS (ECS Fargate, RDS PostgreSQL, VPC, ElastiCache Redis, Amazon MQ).

Observabilidade: OpenTelemetry (OTel) + Loki/Prometheus/Tempo integrados ao Grafana Cloud.

Gestão de Segredos: Mozilla SOPS integrado com AWS KMS (segredos cifrados diretamente no repositório, garantindo GitOps puro).

ADR 0002: Arquitetura de Software e DDD

Fronteiras de Domínio: Organização física sob app/Contexts/{Contexto}/.

Módulos Iniciais Mapeados:

SpendManagement (Gestão de Gastos e Reembolsos).

BudgetAllocation (Execução Orçamentária e Bloqueios).

FiscalCompliance (Auditoria e Localizações Fiscais).

Fundraising (Captação e Doações).

ProjectDelivery (Operações e Beneficiários).

Independência de Camadas: Domínio puro (Domain/) sem heranças de frameworks. Inversão de dependência usando interfaces de repositórios. Persistência de infraestrutura isolada com Eloquent. Comunicação intermódulos assíncrona por Eventos de Domínio no Redis/RabbitMQ.

ADR 0003: Estratégia de Internacionalização (i18n) e Localização (L10n)

Localização de Respostas da API: Implementação de Middleware global que intercepta as requisições, lê o cabeçalho Accept-Language e configura o locale da aplicação via Laravel localization.

Isolamento de Regras Fiscais: Uso de Strategy Pattern resolvido em tempo de execução com base no país do Tenant para aplicar regras tributárias correspondentes (MROSC no Brasil, SII no Chile).

Persistência de Textos Multilíngues: Colunas que requerem traduções dinâmicas no PostgreSQL utilizarão o tipo de dado JSONB para armazenamento flexível de localizações no formato chave-valor.

Fuso Horário Global: Persistência de datas e horas em UTC no banco de dados. Apresentação local resolvida na camada de exibição (Client-side).

3. Configuração do Ambiente de Desenvolvimento Local (Docker)

Os arquivos de infraestrutura .devcontainer/Dockerfile, .devcontainer/devcontainer.json e docker-compose.yml foram configurados com sucesso para suportar o desenvolvimento unificado no GitHub Codespaces e máquinas físicas locais. O ambiente contém:

PHP 8.3 CLI com extensões pdo_pgsql, bcmath, zip, sockets e drivers PECL redis e amqp.

PostgreSQL 16 Alpine.

Redis 7 Alpine.

RabbitMQ 3 Management.

Terraform CLI e Mozilla SOPS CLI pré-instalados.

4. Status de Implementação e Código do Core

Progresso de Infraestrutura

Instalação do Laravel 11: Concluída e unificada na estrutura do contêiner.

Banco de Dados PostgreSQL: Migrações iniciais executadas com sucesso. Conexão local validada.

Progresso de Domínio (Shared Kernel)

Value Object Money (app/Contexts/Shared/Domain/ValueObjects/Money.php): Implementado com sucesso. Trata-se de uma classe rica e imutável que gerencia valores monetários em inteiros (centavos), utiliza a extensão matemática de precisão arbitrária bcmath para cálculos e implementa o algoritmo de alocação proporcional de Martin Fowler para evitar perdas de centavos residuais em rateios.

Testes de Unidade do Money (tests/Unit/Shared/Domain/ValueObjects/MoneyTest.php): Criados em inglês e executados com sucesso. 100% de cobertura de testes nas operações de imutabilidade, rejeição de formatos inválidos, consistência de arredondamento e alocação.

5. Próximos Passos de Engenharia

Implementação do Objeto de Valor ExchangeRate: Desenhar e programar a estrutura de conversão de moedas.

Mapeamento do Bounded Context SpendManagement: Iniciar a criação da modelagem tática de Despesas (Expense), Linhas de Despesa (ExpenseLine) e Distribuição de Custos (CostDistribution).
