FROM mcr.microsoft.com/devcontainers/php:1-8.3-bullseye

# Corrigido: Desativamos o repositório quebrado da Yarn para evitar falhas de chave GPG expirada no Bullseye
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

RUN docker-php-ext-install pdo pdo_pgsql bcmath zip sockets

RUN pecl install redis amqp \
    && docker-php-ext-enable redis amqp

RUN curl -fsSL https://apt.releases.hashicorp.com/gpg | gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] https://apt.releases.hashicorp.com bullseye main" | tee /etc/apt/sources.list.d/hashicorp.list \
    && apt-get update && apt-get install -y terraform

RUN curl -LO https://github.com/getsops/sops/releases/download/v3.8.1/sops-v3.8.1.linux.amd64 \
    && mv sops-v3.8.1.linux.amd64 /usr/local/bin/sops \
    && chmod +x /usr/local/bin/sops

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer