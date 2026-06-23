# Matriz de seleção

Heurísticas — **sempre validar** com eval no workload real. Preços e rankings mudam; capacidades relativas entre tiers são mais estáveis.

## Árvore de decisão (primeiro passo)

```
Precisa visão/PDF/imagem?
├─ SIM → candidatos multimodais (gemini *, gpt-4o*, claude *vision*)
└─ NÃO → continua

É router/classificador com tools?
├─ SIM → priorizar tier rápido/barato (flash, haiku, gpt-4o-mini)
└─ NÃO → continua

Geração longa ou raciocínio multi-regra crítico?
├─ SIM → tier pro (gemini pro, sonnet/opus, gpt-4*)
└─ NÃO → tier rápido costuma bastar

Volume muito alto + tarefa simples?
└─ SIM → menor tier que passe no eval; considerar batch/async
```

## Mapeamento arquétipo → tier típico

| Arquétipo | Tier preferido | Evitar |
|-----------|----------------|--------|
| Router / intent | Flash, mini, haiku | Opus para só classificar |
| FAQ / suporte leve | Flash, mini | Pro sem necessidade |
| Extração campo de PDF | Flash multimodal ou pro se layout complexo | Modelo só texto |
| Geração documento longo | Pro, sonnet+ | Flash com max_tokens baixo |
| JSON complexo aninhado | Pro ou modelo com bom structured output | Tier mínimo sem eval |
| Pipeline offline | Mais barato que passe qualidade | Pagar latência premium |

## Provider: quando considerar cada um

| Provider | Bom para | Atenção |
|----------|----------|---------|
| **Google Gemini** | Multimodal, custo, latência; default do projeto | Verificar quotas/região |
| **OpenAI** | Tool calling maduro, ecossistema | Custo tier superior |
| **Anthropic** | Instruções longas, nuance, escrita | Custo, latência em tiers grandes |
| **OpenRouter** | Testar vários modelos com uma API | Latência extra, routing |
| **Groq** | Latência extrema em modelos suportados | Catálogo limitado |

## Estratégias comuns

### Tier híbrido (recomendado em pipelines)

- **Router** em modelo rápido
- **Sub-agentes críticos** em modelo maior
- Ex.: `agent_support_router` flash → `agent_resource_generation` pro

### Downgrade seguro

1. Baseline no modelo atual (métricas + golden set)
2. Candidate tier inferior
3. Go se métricas críticas ≥ threshold; senão manter ou híbrido

### Upgrade necessário

Sinais:
- JSON/tool call falha > ~2–5% em produção
- Timeouts frequentes (prompt + histórico grande)
- Erros de domínio inaceitáveis mesmo com prompt revisado

### Mesmo provider, outro tier

Primeira troca a testar — menor risco de integração (Prism/Vizra já configurado).

## Configuração sugerida por tipo

| Tipo | temperature | max_tokens |
|------|-------------|------------|
| Router / classificação | 0–0.3 | Baixo (só decisão) |
| Extração JSON | 0–0.2 | Limitado ao schema |
| Conversacional | 0.5–0.8 | Médio |
| Geração longa | 0.3–0.7 | Alto (com teto de custo) |

Documente sempre o **porquê** — não copie defaults sem ligar ao agente.

## Implementação no Vizra ADK

Ordem de precedência do modelo:

1. `protected string $model` no agente (se não vazio)
2. `config('vizra-adk.default_model')` + `default_provider`
3. Env: `VIZRA_ADK_DEFAULT_MODEL`, `VIZRA_ADK_DEFAULT_PROVIDER`

Para testes A/B: env por agente, feature flag, ou override runtime se suportado.
