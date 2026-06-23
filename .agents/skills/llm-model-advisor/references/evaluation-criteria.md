# Critérios de avaliação

## Requisitos eliminatórios (filtro inicial)

Descarte candidatos que falharem qualquer item **hard**:

| Requisito | Pergunta |
|-----------|----------|
| Modalidade | Precisa visão/áudio nativo? |
| Tool calling | Suporta function calling no provider usado? |
| Contexto | Prompt + histórico cabem na janela com margem (~20%)? |
| Latência | TTFT e tempo total cabem no SLA do canal? |
| Compliance | Provider/região permitidos? |
| Disponibilidade | Modelo acessível no stack (Prism/Vizra/OpenRouter)? |

## Dimensões de pontuação (1–5)

### Qualidade na tarefa
- Aderência a instruções complexas
- Precisão em domínio específico (jurídico, suporte, etc.)
- Resistência a ambiguidade sem inventar
- Coerência em respostas longas

### Tool calling / structured output
- Escolhe tool correta no primeiro turno
- Argumentos válidos vs schema
- Não chama tool quando deveria responder direto (e vice-versa)
- JSON parseável sem repair em código

### Latência
- TTFT aceitável para chat síncrono
- Tempo total com N tool calls
- Variância (p95 vs média)

### Custo
- Custo por turno típico (input + output tokens)
- Custo em pico de volume
- Relação custo/qualidade vs tier superior

### Janela de contexto
- System prompt + tools definitions + histórico
- RAG chunks adicionais, se houver

### Estabilidade
- Comportamento consistente entre versões minor
- Menos "drift" em temperatura baixa
- Rate limits e throttling do provider

### Operacional
- Facilidade de observar (tracing, logs)
- Fallback se provider cair
- Feature flags / troca por env

## Pesos por perfil de agente

Ajuste explicitamente na entrega:

| Perfil | Pesos altos |
|--------|-------------|
| Router | Latência, tool calling, custo |
| Suporte conversacional | Qualidade, tom, contexto |
| Gerador longo | Qualidade, janela, coerência |
| Extrator JSON/OCR | Structured output, multimodal |
| Batch/offline | Custo, qualidade |
| Agente crítico (jurídico/financeiro) | Qualidade, structured output, estabilidade |

## Estimativa de tokens (quando não houver telemetria)

| Componente | Heurística |
|------------|------------|
| System prompt | contar chars ÷ 4 (PT/EN misto) |
| Tool definitions | somar schemas; pode ser 500–3k+ tokens |
| Histórico | média turnos × tokens/turno |
| Output | `max_tokens` configurado ou média observada |

Custo/sessão ≈ `(tokens_in × price_in + tokens_out × price_out)`.

## Sinais de que o gargalo não é o modelo

Encaminhe para prompt-engineer ou código se:

- Mesmo modelo bom em eval simples falha em produção com prompt enorme
- JSON quebra por instruções ambíguas, não por capacidade do modelo
- Alucinação por falta de "fonte de verdade" no prompt
- Segurança depende só do LLM (falta guard determinístico em PHP)

## Métricas de eval sugeridas

| Métrica | Como medir |
|---------|------------|
| Routing accuracy | % intenção → sub-agente correto |
| Tool selection | % tool correta no primeiro call |
| Schema validity | % JSON válido sem pós-processamento |
| Task success | checklist humano ou LLM-as-judge com rubrica |
| Latência p50/p95 | logs/Telescope |
| Custo/conversa | tokens × preço |
| Regressão | diff vs baseline em golden set |
