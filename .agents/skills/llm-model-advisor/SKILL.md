---
name: llm-model-advisor
description: >
  Analisa contexto de agente, prompt, tools e requisitos de workload para comparar
  modelos LLM e recomendar o melhor custo-benefício. Use quando o usuário pedir
  escolha de modelo, comparação de LLMs, benchmark de modelos, troca de provider,
  avaliação de custo/latência/qualidade, ou otimização de modelo para um agente.
  Para criar/revisar o prompt em si, use prompt-engineer.
---

# Especialista em Modelos LLM

Avalia **qual modelo** usar dado o agente, prompt, tools, canal e restrições de negócio — não redige prompts (delegue a [prompt-engineer](../prompt-engineer/SKILL.md)).

## Quando aplicar

| Modo | Gatilho |
|------|---------|
| **Escolher** | Novo agente, migração de provider, dúvida "qual modelo usar" |
| **Comparar** | A vs B, tier flash vs pro, open vs closed |
| **Otimizar** | Custo alto, latência, regressão de qualidade após troca |
| **Validar** | Modelo atual ainda é o melhor para o workload |

## Fluxo obrigatório

```
1. DESCOBRIR → 2. MAPEAR WORKLOAD → 3. PONTUAR → 4. COMPARAR → 5. RECOMENDAR → 6. PLANO DE VALIDAÇÃO
```

### 1. Descobrir (bloqueante se faltar contexto crítico)

**Regra:** sem contexto suficiente, **pergunte antes de recomendar**. Não chute modelo genérico.

Colete ou infira do código/docs/conversa:

| Dimensão | O que precisa saber |
|----------|---------------------|
| **Agente** | Papel, tipo (router, executor, gerador, extrator), sub-agentes |
| **Prompt** | Tamanho estimado (system + few-shots), complexidade de regras |
| **Tools** | Quantidade, JSON schema, frequência esperada de tool calls |
| **Entrada** | Texto, imagem, PDF, áudio; tamanho médio por turno |
| **Saída** | Texto livre, JSON estrito, handoff, multi-step |
| **Canal** | WhatsApp, API, batch; tolerância a latência |
| **Volume** | Req/dia, picos, sessões longas (contexto acumulado) |
| **Restrições** | Orçamento, região/dados, provider já contratado, SLA |
| **Baseline** | Modelo atual, problemas observados (alucinação, custo, timeout) |

Perguntas estruturadas: [discovery-questions.md](references/discovery-questions.md)

**Prioridade de perguntas** (se tempo limitado):
1. Tipo de tarefa e criticidade de erro
2. Multimodal? JSON/tools? Multi-step?
3. Orçamento e latência aceitável
4. Modelo/provider atual e o que não funciona

### 2. Mapear workload → perfil de capacidade

Classifique o agente em **arquétipos** (pode combinar):

| Arquétipo | Exige do modelo |
|-----------|-----------------|
| **Router/classificador** | Baixa latência, boa aderência a categorias, tool calling confiável |
| **Conversacional** | Tom natural, contexto longo, objeções/ambiguidade |
| **Raciocínio complexo** | Chain-of-thought, regras conflitantes, domínio técnico |
| **Extração estruturada** | JSON/schema estável, OCR/visão se documento |
| **Geração longa** | Janela grande, coerência em textos extensos |
| **Batch/offline** | Custo/token, menos sensível a latência |

Identifique **requisitos hard** (eliminatórios):
- Visão nativa vs só texto
- Function/tool calling
- Janela de contexto mínima
- Latência máxima (ex.: chat síncrono < 3s TTFT)
- Compliance (dados não podem sair de região/provider)

Critérios detalhados: [evaluation-criteria.md](references/evaluation-criteria.md)

### 3. Pontuar candidatos

Para cada modelo candidato, avalie 1–5 em:

| Critério | Peso default* |
|----------|---------------|
| Qualidade na tarefa | Alto |
| Tool calling / JSON | Alto (se usa tools) |
| Latência (TTFT + total) | Médio–Alto (chat) / Baixo (batch) |
| Custo por sessão | Médio–Alto |
| Janela de contexto | Médio (prompt grande ou histórico longo) |
| Multimodal | Eliminatório ou Alto |
| Estabilidade/comportamento | Médio |
| Disponibilidade no stack | Médio |

\*Ajuste pesos conforme descoberta — documente o ajuste na entrega.

**Custo estimado:** `(tokens_in × preço_in) + (tokens_out × preço_out) × volume`. Se preços desconhecidos, indique ordem de magnitude relativa (flash < pro) e peça confirmação.

### 4. Comparar

Apresente **2–4 candidatos** viáveis — não lista infinita.

Para cada par relevante, explicite:
- Onde A ganha / onde B ganha
- Trade-off principal (ex.: −40% custo, +risco em JSON complexo)
- Se a diferença justifica migração ou só tier downgrade em sub-tarefa

Árvore de decisão rápida: [model-selection-matrix.md](references/model-selection-matrix.md)

### 5. Recomendar

Sempre entregue:

```markdown
## Recomendação
**Modelo:** [provider/model]
**Confiança:** Alta | Média | Baixa — [motivo]
**Alternativa:** [modelo] — quando usar

## Por quê (3–5 bullets)
- [capacidade ↔ requisito do agente]

## Trade-offs aceitos
- [o que se sacrifica vs alternativa]

## Config sugerida
| Parâmetro | Valor | Motivo |
|-----------|-------|--------|
| temperature | ... | ... |
| max_tokens | ... | ... |
| provider | ... | ... |

## Implementação neste projeto
- Classe: `app/Agents/...`
- Override: `protected string $model` ou `config/vizra-adk.php`
- Env: `VIZRA_ADK_DEFAULT_*`

## Riscos residuais
- [comportamentos que só eval em produção revela]
```

**Padrões de tier** (heurística, validar com eval):
- **Flash/lite/mini** → router, FAQ, classificação, extração simples, alto volume
- **Pro/sonnet/opus** → raciocínio multi-regra, geração longa crítica, baixa tolerância a erro
- **Modelo dedicado visão** → OCR/PDF complexo quando texto-only falha

### 6. Plano de validação

Proponha eval antes de trocar em produção:

1. **3–5 cenários reais** do agente (happy path + edge + adversarial leve)
2. **Métricas:** acurácia de rota/JSON, taxa de tool call correta, latência p95, custo/sessão
3. **Método:** replay de conversas reais (anonimizadas) ou golden set
4. **Critério de go/no-go:** ex. "JSON válido ≥ 98%, latência p95 < 4s"
5. **Rollback:** manter modelo anterior configurável por env/feature flag

## Contexto deste projeto

| Item | Local |
|------|-------|
| Default provider/model | `config/vizra-adk.php` → `VIZRA_ADK_DEFAULT_*` |
| Override por agente | `protected string $model` em `app/Agents/` |
| Framework | Vizra ADK + Prism PHP |
| Providers suportados | openai, anthropic, google/gemini, openrouter, groq, etc. |
| Prompts | `resources/prompts/{agent}/default.blade.php` |
| Docs de agentes | `docs/ai/agents/` |

Ao analisar agente existente: leia classe PHP, prompt blade, tools registradas e doc em `docs/ai/agents/` antes de recomendar.

## Anti-padrões

| Evitar | Usar |
|--------|------|
| "Use o modelo mais inteligente" | Match arquétipo + restrições + custo |
| Recomendar sem perguntar volume/canal | Discovery primeiro |
| Ignorar tool calling na comparação | Avaliar separadamente |
| Trocar modelo para compensar prompt ruim | Sugerir prompt-engineer se prompt for gargalo |
| Lista de 10 modelos sem ranking | Top 2–4 com trade-offs explícitos |
| Preços desatualizados como fato | Ordem relativa + pedir confirmação de pricing |
| Mesmo modelo para router e gerador longo | Tier por sub-tarefa quando fizer sentido |

## Recursos

- [discovery-questions.md](references/discovery-questions.md) — perguntas por dimensão
- [evaluation-criteria.md](references/evaluation-criteria.md) — critérios e pesos
- [model-selection-matrix.md](references/model-selection-matrix.md) — árvore de decisão
