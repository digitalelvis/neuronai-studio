# M5 Analítica e Faturamento — Context

**Gathered:** 2026-07-15  
**Milestone:** M5 → target release `v0.4.0` on `v0.3.x`  
**Status:** Tasks ready — Execute from CE-T1  
**Tasks index:** [tasks.md](./tasks.md)
**Specs:** [`cost-estimation`](../cost-estimation/spec.md) · [`usage-export-api`](../usage-export-api/spec.md) · [`usage-analytics`](../usage-analytics/spec.md)  
**Designs:** [CE](../cost-estimation/design.md) · [UE](../usage-export-api/design.md) · [UA](../usage-analytics/design.md)

---

## Feature Boundary

M5 entrega **estimativa de custo** e **API/eventos de uso para o host faturar**, com uma superfície mínima no Studio (Dashboard Livewire existente + badges de tokens no Debugger). Não entrega BI enterprise, página dedicada “Usage”, nem faturamento/cobrança real (Stripe etc.).

---

## Implementation Decisions

### Escopo do milestone (1C)

- **P1 no mesmo milstone:** `cost-estimation` + `usage-export-api`.
- **`usage-analytics` é mínimo:** evoluir o Dashboard Livewire atual e badges no Neuron Debugger; página dedicada / filtros avançados / BI ficam fora (M6 ou deferred).
- Critério de conclusão M5: host consegue consultar/agregar uso + custo estimado via API; operador do Studio vê totais de tokens/custo no Dashboard e tokens no Debugger.

### Superfície Studio (2A)

- Reusar `Dashboard` Livewire + view `livewire/dashboard` — não criar rota/área “Usage”.
- Neuron Debugger (`studio-traces`): mostrar badges de tokens (já no JSON); custo estimado quando disponível.
- Densidade baixa: cards/stats no Dashboard (janela recente fixa, ex. 7/30 dias), não gráficos complexos.

### Fundação de dados (compartilhada)

- Tokens já existem em `StudioRun` / `StudioTraceSpan` via `TelemetryTracker`.
- **Novo:** persistir `provider` + `model` em spans LLM (e, se útil, no run agregando o modelo dominante ou lista) — sem isso custo e export são imprecisos.
- Pricing via config (`neuronai-studio.usage.pricing` ou similar): preço por 1k prompt / 1k completion, por `provider.model`, com currency (USD default).
- Custo é **estimado** (não fatura do provider); host pode sobrescrever tabela de preços.

### Export / billing surface

- API REST agregada sob o prefixo de integração (`stream_adapters.route_prefix`, default `api/neuronai`), middleware do host — espelha padrão M4.
- Payload agregado: tokens + `estimated_cost` por janela, entity (agent/workflow), opcionalmente por model.
- Eventos Laravel opcionais (P2) para o host assinar e gravar no próprio ledger — não obrigatório no MVP.

### Agent's Discretion (resolved in Design)

- Dashboard window: **30 days**.
- Cost: denormalized `estimated_cost` on span + run at write time.
- Export JSON: see `usage-export-api/design.md` (`GET usage`, `GET usage/runs/{run}`).
- Pricing seeds: approximate catalog defaults in config; docs mark as estimates.
- Nested workflow metering: `parent_run_id` + rollup; exclude children from window aggregates.
- Meter gaps closed in CE: `stream`/`streamHandler`, `LlmNodeExecutor` chat/stream.

---

## Deferred Ideas (explicit)

- Página dedicada Usage com filtros avançados / charts.
- Multi-tenant / user attribution no uso.
- Token tracking de embeddings / RAG como linha de custo separada.
- Integração com billing providers (Stripe, Asaas, etc.).
- Alertas de quota / budgets.
- Custo “real” via APIs de billing dos providers.
- Duplicate LLM spans on parent workflow trace (v1 rolls totals only).
