# Playbook de DevOps e Comandos do Desenvolvedor

> **Visão Geral:** Este guia consolida todos os comandos operacionais, de infraestrutura e de diagnóstico necessários para inicializar, manter e debugar o ambiente de desenvolvimento do **ONGManager Backend** utilizando Docker, DevContainers e Laravel 11.

---

## 1. Ciclo de Vida do Contêiner (Codespaces / VS Code Local)

Sempre que houver alterações estruturais no arquivo `.devcontainer/Dockerfile` ou no `docker-compose.yml`, é necessário reconstruir o contêiner de desenvolvimento para aplicar os novos módulos PHP ou utilitários de sistema.

### Forçar a Reconstrução (Rebuild)

1. Abra o **VS Code** (Web ou Desktop).
2. Pressione `F1` (ou `Ctrl+Shift+P` / `Cmd+Shift+P`).
3. Digite e selecione: `Codespaces: Rebuild Container` (ou `Dev Containers: Reopen in Container` caso esteja local).

---

## 2. Instalação Limpa do Laravel em Diretório Não-Vazio

Por padrão, o Composer impede a execução do comando `create-project` caso existam arquivos de infraestrutura (como `.devcontainer` e `docker-compose.yml`) na raiz. Use a estratégia de extração temporária para mitigar esse problema:

```bash
# 1. Baixa o Laravel 11 em um diretório isolado
composer create-project laravel/laravel laravel-temp --prefer-dist

# 2. Move os arquivos visíveis para a raiz do projeto
mv laravel-temp/* .

# 3. Localiza e move os arquivos ocultos (ex: .env.example, .gitignore padrão)
find laravel-temp -maxdepth 1 -name ".*" ! -name "." ! -name ".." -exec mv {} . \;

# 4. Remove o diretório temporário agora vazio
rm -rf laravel-temp
```

---

## 3. Configuração Inicial da Aplicação

Após clonar o repositório ou reinstalar o Laravel, execute a seguinte sequência para garantir que a aplicação possui as chaves corretas e que lerá o banco de dados configurado no arquivo `.env`:

```bash
# 1. Copia o arquivo de ambiente de exemplo (caso não tenha o .env ativo)
cp .env.example .env

# 2. Limpa o cache de configuração para forçar a leitura do novo .env do disco
php artisan config:clear

# 3. Gera a chave criptográfica única da aplicação (APP_KEY)
php artisan key:generate
```

---

## 4. Banco de Dados e Migrações (PostgreSQL 16)

A integração com o banco PostgreSQL rodando no container Docker vizinho deve ser validada e executada utilizando a CLI do Artisan.

```bash
# Executa as migrações (criar tabelas padrão do Laravel)
php artisan migrate

# Verifica o status atual da conexão e das tabelas do banco de dados
php artisan db:show

# Desfaz o último lote de migração (Rollback)
php artisan migrate:rollback

# Reseta o banco por completo e reexecuta todas as migrations (⚠️ Cuidado: apaga os dados)
php artisan migrate:fresh
```

---

## 5. Diagnósticos e Saúde do Ambiente

Se encontrar erros de conexões ou falta de drivers durante o desenvolvimento, utilize estes comandos diretamente do terminal integrado do DevContainer para isolar a causa raiz:

### Validação de Extensões do PHP

Garante que o driver de conexão com o PostgreSQL e os pacotes de alta precisão estão instalados no runtime do PHP CLI:

```bash
# Deve retornar 'pdo_pgsql'
php -m | grep pdo

# Deve retornar 'bcmath' (necessário para o motor de rateio de despesas)
php -m | grep bcmath
```

### Validação de Resolução de DNS Interno do Docker

Garante que o container da aplicação consegue enxergar os serviços adicionais definidos no `docker-compose.yml`:

```bash
# Testa comunicação com o banco de dados
ping -c 3 postgres

# Testa comunicação com o Redis
ping -c 3 redis

# Testa comunicação com o RabbitMQ
ping -c 3 rabbitmq
```

---

## 6. Execução de Testes Automatizados (PHPUnit / TDD)

Usamos testes automatizados para blindar as invariantes de negócio e cálculos do ONGManager. Utilize os comandos abaixo de acordo com a necessidade do fluxo de desenvolvimento.

### Usando o Artisan do Laravel (Interface formatada)

```bash
# Executa toda a suíte de testes do projeto
php artisan test

# Executa apenas os testes de uma classe ou arquivo específico
php artisan test --filter MoneyTest

# Executa um teste específico baseado no nome do método
php artisan test --filter test_should_create_instance_successfully
```

### Usando o binário direto do PHPUnit (Ideal para CI/CD e debug)

```bash
# Executa toda a suíte de testes
./vendor/bin/phpunit

# Executa apenas um arquivo de teste específico
./vendor/bin/phpunit tests/Unit/Shared/Domain/ValueObjects/MoneyTest.php

# Executa com mapeamento de cobertura de testes (Code Coverage)
# Nota: requer driver de cobertura instalado ou ativo (ex: pcov ou xdebug)
./vendor/bin/phpunit --coverage-text
```
