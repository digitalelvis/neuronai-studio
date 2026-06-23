# Otimização de Tokens

Meta: **menos tokens, mesmo comportamento observável**.

## Ordem de corte (do mais seguro ao mais arriscado)

1. **Fluff** — adjetivos, repetições, explicações que o modelo já sabe
2. **Duplicatas** — mesma regra em múltiplas seções → uma ocorrência
3. **Prosa → estrutura** — parágrafos viram bullets ou tabelas
4. **Few-shots** — remover exemplos redundantes; manter edge cases
5. **Condensação lexical** — frases mais curtas sem perder imperativos
6. **Externalizar** — conteúdo estático longo → partial/RAG/tool (último recurso no system prompt)

## Regra de ouro

> Se remover o trecho não muda o comportamento em nenhum dos cenários de eval, remova.

## Técnicas por seção

| Seção | Técnica |
|-------|---------|
| Identidade | 2–4 linhas; sem história da empresa inteira |
| Fluxo | Etapas numeradas; sem narrativa |
| Tools | Tabela when/when-not em vez de parágrafos |
| Guardrails | Lista negativa compacta |
| Exemplos | Máx. 1–3; só padrões não óbvios |

## Few-shot eficiente

**Bom** — cobre decisão difícil:

```markdown
Usuário: "Cancela tudo agora"
Assistente: [confirma identidade + pede confirmação explícita antes da tool]
```

**Ruim** — repete o óbvio:

```markdown
Usuário: "Olá"
Assistente: "Olá! Como posso ajudar?"
```

## Formatação compacta

Preferir:

```markdown
| Situação | Ação |
|----------|------|
| Tool OK | Resumir resultado; citar fonte |
| Tool erro | Informar falha; não inventar |
| Sem dados | Pedir X ou escalar |
```

Evitar repetir a mesma tabela em prosa antes e depois.

## Estimativa

Ao otimizar, reporte:

- Tokens ~antes → ~depois (ordem de grandeza: chars/4 ou tokenizer do projeto)
- Comportamentos preservados (lista)
- Comportamentos em risco (se houve corte agressivo)

## O que NÃO cortar

- Imperativos "Nunca" / "Somente quando"
- Precedência em conflito de regras
- Condições de tool call de alto risco
- Anti-alucinação (fonte de verdade)
- Formato de saída obrigatório (JSON schema, campos)
