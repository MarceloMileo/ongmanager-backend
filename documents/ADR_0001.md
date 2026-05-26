ADR 0001: Arquitetura e Infraestrutura Base (Commit Zero)

Status

Aprovado

Contexto

O ONGManager é um produto SaaS B2B internacional voltado para o Terceiro Setor, necessitando de alta conformidade fiscal (SII no Chile e MROSC no Brasil), resiliência para operações em campo remoto e consistência matemática extrema em conversões cambiais e rateios de custos.

Para viabilizar o desenvolvimento rápido do MVP sem introduzir complexidade operacional desnecessária ou acoplamento físico prematuro, precisávamos definir a arquitetura de software, a topologia de repositórios no Git e a infraestrutura de desenvolvimento e produção.

Decisões

1. Padrão Arquitetural: Monólito Modular

Adotaremos o padrão de Monólito Modular utilizando o ecossistema do Laravel (PHP 8.3).

Justificativa: Diferente de uma arquitetura de microsserviços puros (que exigiria bancos de dados distribuídos e comunicação de rede complexa), o monólito modular organiza o domínio através de namespaces estritos (Bounded Contexts) dentro de uma única base de dados relacional. Isto garante transações ACID para a contabilidade e permite desacoplamento temporal através de filas assíncronas (Laravel Jobs/RabbitMQ).

2. Topologia de Repositórios: Polyrepo

O projeto será dividido inicialmente em três repositórios independentes no GitHub Pro:

ongmanager-backend: Core do sistema em Laravel + Terraform.

ongmanager-web: Interface administrativa e Portal do Doador em React.

ongmanager-mobile: Aplicação de campo offline-first em React Native.

Justificativa: Garante fronteiras rígidas de integração, impede que falhas em pipelines de front-end afetem deploys críticos de segurança do back-end, e força o respeito estrito ao contrato exposto pela API (OpenAPI).

3. Ambiente de Desenvolvimento: DevContainers e Docker

O ambiente local será totalmente orquestrado através do Docker e definido via especificação de DevContainers (.devcontainer).

Justificativa: Garante paridade absoluta entre a máquina de desenvolvimento na nuvem (GitHub Codespaces) e o computador físico de qualquer programador. O ambiente de desenvolvimento passa a ser versionado como código.

4. Infraestrutura como Código (IaC): Terraform (HCL)

Toda a infraestrutura de produção na AWS será provisionada estritamente através do Terraform.

Justificativa: Garante que ambientes de Homologação (Staging) e Produção sejam criados de forma idêntica e automatizada a partir do pipeline de CI/CD, eliminando processos manuais suscetíveis a erro humano.

5. Observabilidade: OpenTelemetry e Grafana Cloud

A telemetria da aplicação será instrumentada com a especificação OpenTelemetry (OTel), enviando dados de Logs (Loki), Métricas (Prometheus) e Traces (Tempo) para a stack do Grafana Cloud.

Justificativa: Evita o acoplamento proprietário aos serviços da AWS (CloudWatch/X-Ray) e fornece visibilidade holística para o rastreamento ponta-a-ponta de requisições financeiras lentas ou falhas.

6. Gestão de Segredos: Mozilla SOPS e AWS KMS

Os ficheiros de configuração de variáveis de ambiente (.env.production, etc.) serão armazenados cifrados diretamente no repositório de código utilizando o Mozilla SOPS, integrado ao serviço AWS KMS.

Justificativa: Aplica o conceito de GitOps puro. Alterações em variáveis de ambiente tornam-se visíveis e auditáveis via Pull Requests no histórico do Git, enquanto os valores reais permanecem inacessíveis para agentes não autorizados.

Consequências

Positivas:

Paridade total de ambientes de desenvolvimento.

Forte isolamento de domínio a nível de código no Laravel.

Pipelines de deploy independentes e rápidos para cada plataforma cliente.

Trilha de auditoria completa para código, infraestrutura e credenciais de ambiente.

Negativas:

Necessidade de gerir múltiplos repositórios (Pull Requests separados para alterações que cruzam o back-end e front-end).

Exigência de instalação local do Docker em computadores de desenvolvimento.