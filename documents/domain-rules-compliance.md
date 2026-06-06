# Regras de Domínio, Fiscais e Compliance Matemático

> **Visão Geral:** Este documento define as regras invariantes, fórmulas matemáticas e critérios de elegibilidade contábil que o *core* do **ONGManager** deve impor para garantir a integridade dos dados auditáveis perante os órgãos reguladores (SII Chile, MROSC Brasil) e doadores internacionais.

---

## 1. Precisão Financeira e Arredondamento (Penny Rounding Rule)

Para mitigar erros de ponto flutuante comuns em tipos primitivos (`float`, `double`), todas as operações monetárias do sistema utilizam o conceito de objeto de valor `Money`, onde o valor é representado por um inteiro de alta precisão em cêntimos (padrão de 128 bits ou equivalente na base de dados).

### Conversão Multimoeda e Normalização

Sempre que uma distribuição de custos envolva moedas distintas da moeda base do recibo fiscal, o domínio aplica a taxa de câmbio (`ExchangeRate`) válida para a data da operação.

Se a dízima da conversão cambial gerar uma fração residual de cêntimos, é aplicada a **Penny Rounding Rule**:

1. O sistema calcula a diferença residual $D$.
2. Se $D \le 0.05$ (na moeda base do gasto), a transação é considerada válida.
3. A diferença residual $D$ é automaticamente creditada ou debitada na linha de despesa de maior valor absoluto dentro do rateio de despesas (`ExpenseLine`), ou transferida para uma conta interna de conciliação de "Ajuste Operacional" da própria ONG.

---

## 2. Consistência Matemática do Rateio de Custos

Para qualquer despesa submetida para aprovação, a soma das suas distribuições (`CostDistribution`) para os projetos de destino deve equivaler exatamente ao valor total da despesa.

### Cenário A: Distribuição Percentual

Se a partilha for definida percentualmente entre múltiplos projetos:

$$ \sum_{i=1}^{n} Percentagem_i = 100\% $$

### Cenário B: Distribuição por Valor Fixo Misto (Multimoeda)

Se um recibo emitido na Moeda Base ($M_B$) for rateado entre projetos com alocações fixas em Moedas Estrangeiras ($M_E$), o domínio normaliza todas as fatias para a moeda base utilizando a taxa de câmbio (`ExchangeRate`) da data:

$$ \sum \left( Valor\_Fixo_{M_E} \times ExchangeRate \right) + \sum Valor\_Fixo_{M_B} = Total\_Recibo_{M_B} $$

---

## 3. Motor de Transparência e Eficiência Operacional

Para provar matematicamente a eficiência de gestão das organizações parceiras, o sistema calcula métricas de impacto diretamente no banco de dados.

### Taxa de Overhead Operacional (Overhead Rate)

Identifica a percentagem dos fundos utilizada para a manutenção administrativa em relação à execução das atividades fim.

$$ OverheadRate = \frac{\sum ExpenseLine_{Overhead}}{\sum ExpenseLine_{Total}} $$

### Unidades de Benefício Social (Impact Units)

Converte a poupança gerada pela eficiência operacional em unidades físicas de benefício para a comunidade (por exemplo, porções de refeição adicionais), multiplicando a poupança pelo fator de impacto ($ImpactFactor$) contratualizado:

$$ Savings = LimitOrçamental - GastoEfetivo $$

$$ ImpactUnits = Savings \times ImpactFactor $$

---

## 4. Valoração do Trabalho Voluntário como Ativo Económico

Para fins de balanço social e demonstração do Retorno Social do Investimento (SROI), o trabalho de voluntariado deve ser precificado no domínio de acordo com a especialização técnica aplicada:

$$ EconomicValue = HorasRegistadas \times TaxaHorariaMercado_{SkillSet} $$

- **General (Geral):** Tarefas de apoio administrativo e logística básica.
- **Professional (Profissional):** Tarefas que exijam habilitação profissional técnica (ex: enfermeiro, psicólogo).
