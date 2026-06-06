# ADR 0003: Estratégia de Internacionalização (i18n) e Localização (L10n)

**Status:** ✅ Aprovado

---

## Contexto

Como o ONGManager é uma plataforma SaaS internacional projetada inicialmente para atender organizações não governamentais e doadores no Brasil e no Chile, o sistema precisa gerenciar múltiplas localizações de forma nativa e integrada.

A complexidade reside em lidar com:

- Traduções estáticas de mensagens e validações da API.
- Persistência de dados cadastrais dinâmicos que exigem múltiplos idiomas (ex: categorias de despesas, nomes de projetos).
- Localização fiscal de regras de negócio altamente variáveis de cada país (MROSC no Brasil, SII no Chile).
- Sincronia de data e hora entre escritórios globais de doadores e operações locais em campo.

---

## Decisões

### 1. Tradução de Mensagens da API via Middleware

As respostas de erro, mensagens de validação e textos do sistema serão traduzidos utilizando o mecanismo nativo de internacionalização do Laravel (`/lang`).

- O idioma da requisição será determinado por um middleware global que lê o cabeçalho HTTP `Accept-Language`.
- Se o cabeçalho estiver ausente ou contiver um idioma não suportado, o sistema recorrerá ao idioma padrão configurado no perfil do Tenant (a ONG) ou ao fallback geral (`en`).

### 2. Isolação de Regras Fiscais usando o Strategy Pattern

Não utilizaremos condicionais espalhados no core do domínio para tratar variações de países. Em vez disso, usaremos o padrão de projeto Strategy resolvido em tempo de execução no contexto de `FiscalCompliance`.

- A interface do port `IFiscalComplianceValidator` definirá os contratos.
- O sistema resolverá e injetará a implementação correspondente (ex: `ChileanFiscalStrategy` ou `BrazilianFiscalStrategy`) baseando-se no código de país cadastrado no Tenant do usuário autenticado.

### 3. Armazenamento Multilíngue Dinâmico com PostgreSQL JSONB

Para tabelas que exigem tradução de campos textuais dinâmicos (como o nome de uma categoria de despesa ou a descrição de um benefício social), utilizaremos colunas do tipo `JSONB` no PostgreSQL.

- O formato de persistência seguirá a estrutura de chave-valor simples: `{"pt_BR": "Alimentação", "es_CL": "Alimentación", "en": "Food"}`.
- No Laravel, utilizaremos o mapeamento nativo de Casts do Eloquent para serializar e ler esses dados de forma transparente na camada de infraestrutura.

### 4. Unificação de Timezone no Banco de Dados (UTC)

Toda e qualquer data e hora gravada no banco de dados PostgreSQL deve seguir estritamente o fuso horário UTC.

- O timezone do Laravel (`config/app.php`) será mantido em UTC.
- A conversão de exibição para o horário local da operação ou do doador será resolvida inteiramente na camada do Cliente (SaaS Web / Mobile App) ou parametrizada com base no fuso horário configurado nas preferências da organização.

---

## Consequências

### ✅ Positivas:

- O core do domínio fica totalmente desacoplado de regras de impostos locais e formatos de idiomas.
- O uso de `JSONB` reduz o uso de tabelas de tradução auxiliares complexas (como padrões clássicos de tradução por polimorfismo), aproveitando a indexação de performance do PostgreSQL para buscas eficientes.
- Consistência de auditoria impecável para timestamps, evitando conflitos de fechamento de meses contábeis entre diferentes fusos horários.

### ⚠️ Negativas:

- Consultas diretas no banco de dados que filtram por campos traduzidos no PostgreSQL exigirão sintaxe JSON específica, o que pode aumentar a complexidade das queries de infraestrutura em relatórios ad-hoc.
