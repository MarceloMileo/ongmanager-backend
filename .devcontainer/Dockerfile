# Usamos a imagem oficial da Microsoft para DevContainers com PHP 8.3
FROM mcr.microsoft.com/devcontainers/php:1-8.3-bullseye

# Instala dependências do sistema necessárias para o Laravel, PostgreSQL e RabbitMQ
RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y \
        libpq-dev \
        librabbitmq-dev \
        libzip-dev \
        unzip \
        gnupg \
        software-properties-common \
        curl \
    && apt-get clean -y && rm -rf /var/lib/apt/lists/*

# Instala extensões nativas do PHP (PostgreSQL, BCMath para cálculos de alta precisão, Zip, Sockets para RabbitMQ)
RUN docker-php-ext-install pdo pdo_pgsql bcmath zip sockets

# Instala extensões PECL (Redis e AMQP para RabbitMQ)
RUN pecl install redis amqp \
    && docker-php-ext-enable redis amqp

# Instala o Terraform CLI (Para IaC posterior)
RUN curl -fsSL https://apt.releases.hashicorp.com/gpg | gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] https://apt.releases.hashicorp.com $(lsb_release -cs) main" | tee /etc/apt/sources.list.d/hashicorp.list \
    && apt-get update && apt-get install -y terraform

# Instala o SOPS CLI (Para gestão de segredos criptografados)
RUN curl -LO https://github.com/getsops/sops/releases/download/v3.8.1/sops-v3.8.1.linux.amd64 \
    && mv sops-v3.8.1.linux.amd64 /usr/local/bin/sops \
    && chmod +x /usr/local/bin/sops

# Instala o Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer