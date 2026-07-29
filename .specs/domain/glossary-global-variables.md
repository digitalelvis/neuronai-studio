# Glossary: Global Variables

Domain terms for the Studio variable vault. Keep this file updated as grilling / design locks language.

| Term | Definition |
|------|------------|
| **Studio Variable** | Named, app-scoped value stored in the Studio database and usable in supported configuration fields. Not a workflow state key and not a Laravel `.env` entry (though it may complement one). |
| **Variable Vault** | The Studio collection of Studio Variables (CRUD Settings surface + persistence). One vault per host install in MVP. |
| **Credential (type)** | Variable type for secrets (API keys, tokens). UI masks the value; storage encrypts at rest; runtime resolves to plaintext only in-process for the call. |
| **Generic (type)** | Variable type for non-secret configuration (base URLs, model names, flags as strings). Value may be shown in UI; no encryption requirement. |
| **Variable Input** | Reusable form control (Langflow-style) that lets the author enter a **literal** or **bind** a Studio Variable (globe picker), with optional mask for sensitive fields. |
| **Variable Reference** | Stable persisted pointer from a field to a Studio Variable by name. Config wire format: **`var:NAME`**. Prompt/state placeholders: **`{{ var.NAME }}`**. Never stores the resolved secret in entity config. |
| **Literal Value** | Direct string entered in Variable Input without binding a Studio Variable. |
| **Apply to Fields** | Langflow feature that auto-assigns a variable as default for a catalog of field types. **Out of MVP** for NeuronAI Studio. |
| **Field Type Catalog** | Named set of integration fields (e.g. “OpenAI API Key”, “Google API Key”) used by Apply to Fields. Deferred with that feature. |
| **Env Bridge** | Existing pattern: store env *names* (`token_env`, `key_env`) or `env:` / `{{ env.VAR }}` strings; host `.env` holds the secret. Remains supported alongside the vault. |
| **Runtime Resolution** | Process of turning a Variable Reference (and/or Env Bridge) into a concrete string before a provider, tool, MCP, or RAG call. |
| **App-scoped** | Variables belong to the single Laravel application / Studio install. No per-user or per-team isolation in MVP. |
| **Multi-tenancy (future)** | Later ability to isolate vaults (or rows) per tenant/team. Vault APIs should avoid “global singleton” hardcoding so this can land without a rewrite. |
