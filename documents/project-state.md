ONGManager - Estado Atual do Projeto e Guia de Restauração (Restore Point)

Este documento funciona como a "verdade absoluta" do projeto ONGManager Backend. Ele deve ser mantido atualizado a cada evolução técnica. Caso o contexto de desenvolvimento ou a sessão de IA sejam perdidos, a leitura deste arquivo é suficiente para restaurar o estado exato da aplicação e continuar a engenharia de software de onde paramos.

1. Visão Geral do Produto e Mercado

Produto: ONGManager B2B SaaS (Produção/Enterprise-ready, não MVP).

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

3. Configuração do Ambiente de Desenvolvimento Local (Docker)

Os arquivos abaixo devem ser criados e persistidos na raiz do repositório ongmanager-backend.

Arquivo: .devcontainer/Dockerfile

FROM [mcr.microsoft.com/devcontainers/php:1-8.3-bullseye](https://mcr.microsoft.com/devcontainers/php:1-8.3-bullseye)

# Desativa o repositório quebrado da Yarn para evitar falhas de chave GPG expirada no Bullseye
RUN rm -f /etc/apt/sources.list.d/yarn.list \
    && apt-get update && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y \
        libpq-dev \
        librabbitmq-dev \
        libzip-dev \
        unzip \
        gnupg \
        curl \
    && apt-get clean -y && rm -rf /var/lib/apt/lists/*

# Instala as extensões necessárias para banco de dados, alta precisão matemática e filas
RUN docker-php-ext-install pdo_pgsql bcmath zip sockets

RUN pecl install redis amqp \
    && docker-php-ext-enable redis amqp

# Instala Terraform CLI
RUN curl -fsSL [https://apt.releases.hashicorp.com/gpg](https://apt.releases.hashicorp.com/gpg) | gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] [https://apt.releases.hashicorp.com](https://apt.releases.hashicorp.com) bullseye main" | tee /etc/apt/sources.list.d/hashicorp.list \
    && apt-get update && apt-get install -y terraform

# Instala Mozilla SOPS
RUN curl -LO [https://github.com/getsops/sops/releases/download/v3.8.1/sops-v3.8.1.linux.amd64](https://github.com/getsops/sops/releases/download/v3.8.1/sops-v3.8.1.linux.amd64) \
    && mv sops-v3.8.1.linux.amd64 /usr/local/bin/sops \
    && chmod +x /usr/local/bin/sops

# Instala Composer
RUN curl -sS [https://getcomposer.org/installer](https://getcomposer.org/installer) | php -- --install-dir=/usr/local/bin --filename=composer


Arquivo: .devcontainer/devcontainer.json

{
  "name": "ONGManager Backend (Laravel)",
  "dockerComposeFile": "../docker-compose.yml",
  "service": "app",
  "workspaceFolder": "/workspaces/ongmanager-backend",
  "customizations": {
    "vscode": {
      "settings": {
        "php.suggest.basic": false,
        "editor.formatOnSave": true
      },
      "extensions": [
        "bmewburn.vscode-intelephense-client",
        "hashicorp.terraform",
        "signageos.signageos-vscode-sops",
        "eamodio.gitlens",
        "EditorConfig.EditorConfig"
      ]
    }
  },
  "remoteUser": "vscode",
  "features": {
    "ghcr.io/devcontainers/features/github-cli:1": {}
  },
  "postCreateCommand": "echo 'Ambiente ONGManager restaurado com sucesso!'"
}


Arquivo: docker-compose.yml

version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: .devcontainer/Dockerfile
    volumes:
      - .:/workspaces/ongmanager-backend:cached
    command: /bin/sh -c "while sleep 1000; do :; done"
    depends_on:
      - postgres
      - rabbitmq
      - redis

  postgres:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ongmanager
      POSTGRES_USER: devuser
      POSTGRES_PASSWORD: devpassword
    volumes:
      - db-data:/var/lib/postgresql/data
    ports:
      - "5432:5432"

  rabbitmq:
    image: rabbitmq:3-management-alpine
    restart: unless-stopped
    environment:
      RABBITMQ_DEFAULT_USER: devuser
      RABBITMQ_DEFAULT_PASS: devpassword
    ports:
      - "5672:5672"
      - "15672:15672"
    volumes:
      - rabbitmq-data:/var/lib/rabbitmq

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    ports:
      - "6379:6379"
    volumes:
      - redis-data:/data

volumes:
  db-data:
  rabbitmq-data:
  redis-data:


4. Playbook de Operação Rápida

Como Reconstruir o Ambiente Local do Zero

No VS Code / Codespaces, pressione F1 -> Codespaces: Rebuild Container.

Como Instalar o Laravel 11 em Diretório Contendo o Docker

composer create-project laravel/laravel laravel-temp --prefer-dist
mv laravel-temp/* .
find laravel-temp -maxdepth 1 -name ".*" ! -name "." ! -name ".." -exec mv {} . \;
rm -rf laravel-temp


Configurações Iniciais e Execução do Banco de Dados

No arquivo .env gerado, certifique-se de definir as credenciais do PostgreSQL:

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=ongmanager
DB_USERNAME=devuser
DB_PASSWORD=devpassword


Execute as migrações:

php artisan config:clear
php artisan key:generate
php artisan migrate


Comandos de Diagnóstico e Saúde do Ambiente

# Validação das Extensões PHP CLI
php -m | grep pdo_pgsql   # Deve retornar pdo_pgsql
php -m | grep bcmath      # Deve retornar bcmath

# Validação do DNS Interno do Docker
ping -c 3 postgres        # Deve responder com sucesso
ping -c 3 redis           # Deve responder com sucesso


5. Próximos Passos de Engenharia

Modelagem Contábil e Alta Precisão: Desenvolver o Objeto de Valor (Value Object) Money imutável em PHP puro na camada de Domínio, protegendo contra erros de ponto flutuante utilizando extensões matemáticas de alta precisão (bcmath).

Setup do PHPUnit / Pest: Configurar o framework de testes e construir o primeiro teste unitário de consistência matemática para o rateio de custos de despesas.

Mapeamento do Bounded Context SpendManagement: Criar as estruturas de diretórios especificadas na ADR 0002.