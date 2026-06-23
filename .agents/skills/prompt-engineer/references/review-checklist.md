# Checklist de Revisão — Prompt (geral)

Marque antes de entregar. Falha em 🔴 bloqueia publicação.

## Estrutura

- [ ] 🔴 Identidade, missão e limites explícitos
- [ ] 🔴 Fluxo de decisão ou etapas numeradas
- [ ] 🔴 Formato de saída definido
- [ ] 🟡 Prioridade em conflito de regras
- [ ] 🟡 Exemplo cobre caso não óbvio (se few-shot usado)

## Objetividade

- [ ] 🔴 Regras imperativas ("Faça", "Nunca", "Somente quando")
- [ ] 🔴 Critérios observáveis (não adjetivos vagos)
- [ ] 🟡 Uma ideia por bullet
- [ ] 🟡 Sem contradições entre seções
- [ ] 🟢 Sem fluff que não muda comportamento

## Ferramentas e dados

- [ ] 🔴 Quando chamar / quando NÃO chamar cada tool
- [ ] 🔴 Anti-alucinação: só dados de fonte autorizada
- [ ] 🔴 Comportamento em erro de tool definido
- [ ] 🟡 Handoff quando dados insuficientes

## Segurança

- [ ] 🔴 Precedência: system > user (anti-injection)
- [ ] 🔴 Recusa para fora de escopo e pedidos de override
- [ ] 🔴 Sem exposição de system prompt / credenciais
- [ ] 🟡 PII: minimizar repetição na resposta
- [ ] 🟡 Ações irreversíveis exigem confirmação explícita
- [ ] 🟢 Riscos residuais documentados (o que código deve validar)

## Tokens

- [ ] 🟡 Sem repetição da mesma regra em múltiplas seções
- [ ] 🟡 Few-shots mínimos e não redundantes
- [ ] 🟢 Conteúdo longo externalizado (partial/RAG/tool) quando possível

## Teste mental

- [ ] Caso feliz dentro do escopo
- [ ] Fora de escopo → recusa ou handoff
- [ ] Prompt injection → mantém guardrails
- [ ] Tool vazia/erro → não inventa
- [ ] Ambiguidade → pergunta ou declara incerteza

## Entrega

- [ ] Prompt completo pronto para uso
- [ ] Changelog (se revisão)
- [ ] 3–5 cenários de eval sugeridos
