# Perguntas de descoberta

Use como checklist. Pergunte só o que não puder inferir do código, docs ou conversa.

## Agente e prompt

- Qual o **objetivo mensurável** do agente? (ex.: rotear intenção, gerar recurso jurídico)
- É **router**, executor direto, ou pipeline multi-agente?
- Tamanho do **system prompt** (~tokens)? Tem few-shots pesados?
- Regras **conflitantes** ou domínio regulado (jurídico, financeiro, saúde)?
- O prompt já foi otimizado ou é candidato a revisão (→ prompt-engineer)?

## Tools e saída

- Quantas **tools**? Com que frequência o modelo deve chamá-las?
- Saída precisa ser **JSON/schema estrito** ou texto livre?
- **Multi-step** (várias tool calls por turno)? Limite de steps?
- O que acontece quando tool **falha** — o modelo inventa ou escala?

## Entrada e canal

- Só **texto** ou também imagem/PDF/áudio?
- Tamanho médio de mensagem do usuário? Histórico longo na sessão?
- Canal: **WhatsApp** (latência sensível), API síncrona, job assíncrono?
- Usuário espera resposta em quantos **segundos**?

## Volume e custo

- **Requisições/dia** ou sessões/mês?
- Orçamento mensal alvo ou custo máximo por conversa?
- Pico de tráfego (horário comercial, campanhas)?

## Restrições técnicas

- Providers **já contratados** (OpenAI, Google, Anthropic, OpenRouter)?
- Dados podem sair para **cloud externa**? Requisito de região?
- **SLA** de disponibilidade do provider?
- Infra atual: timeout HTTP (`VIZRA_ADK_HTTP_TIMEOUT`), filas, retries?

## Baseline e problemas

- **Modelo atual**? Provider?
- O que **não funciona** hoje? (alucinação, JSON quebrado, lento, caro, timeout)
- Houve **regressão** após mudança de modelo/prompt?
- Existe **golden set** ou logs de conversas para replay?

## Priorização quando o usuário tem pouco tempo

Pergunte nesta ordem:

1. Tipo de tarefa + **criticidade de erro**
2. Tools/JSON/multimodal/**multi-step** (sim/não)
3. **Latência** aceitável + **volume**
4. **Orçamento** ou "custo importa quanto?"
5. Modelo atual + **dor principal**

Se ainda faltar item eliminatório (ex.: precisa visão e candidato é texto-only), pergunte antes de fechar recomendação.
