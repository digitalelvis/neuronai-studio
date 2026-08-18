# Multi-tenancy (optional)

NeuronAI Studio is **single-tenant by default**. Enable tenancy when the host app already has organizations, teams, or stancl/tenancy and needs Studio authoring + runtime isolated per tenant.

Studio does **not** register tenants, switch tenants in the UI, or read `X-Tenant-Id` / subdomains. The host resolves the current tenant.

Conversation **owner** (`StudioInvoke::forOwner`, `ownerable_*` on threads) is a different concept: the customer/user of a chat, not the organization.

## Enable (shared database)

1. Publish config if you have not already:

```bash
php artisan vendor:publish --tag=neuronai-studio-config
php artisan migrate
```

2. Implement `DigitalElvis\NeuronAIStudio\Tenancy\TenantResolver`:

```php
namespace App\Studio;

use DigitalElvis\NeuronAIStudio\Tenancy\TenantResolver;

final class OrganizationTenantResolver implements TenantResolver
{
    public function id(): ?string
    {
        $org = auth()->user()?->currentOrganization;

        return $org !== null ? (string) $org->getKey() : null;
    }
}
```

3. Point Studio at it **and** make sure your middleware (session, tenant, auth) runs **before** Studio routes so `id()` is populated:

```env
NEURONAI_STUDIO_TENANCY=true
NEURONAI_STUDIO_TENANCY_DRIVER=shared
NEURONAI_STUDIO_TENANCY_RESOLVER=App\Studio\OrganizationTenantResolver
```

Or in `config/neuronai-studio.php`:

```php
'tenancy' => [
    'enabled' => true,
    'driver' => 'shared',
    'resolver' => \App\Studio\OrganizationTenantResolver::class,
],
```

When enabled, Studio / integrate / MCP / usage HTTP **abort 403** if the resolver returns `null`. Do not add `neuronai-studio.tenant` to the host `middleware` array — the package appends it.

## What is isolated

With `driver=shared`, these tables gain `tenant_id` (null = **global** catalog):

- Authoring: agents, workflows, knowledge bases, tools, MCP servers, MCP endpoints, variables
- Runtime: threads, runs, traces (usage export follows the run scope)

Child rows (documents, chat messages, spans, evals) inherit isolation through the parent.

Writes from HTTP **always stamp the current tenant**. Globals are created only when tenancy is off, from seeders, or:

```php
use DigitalElvis\NeuronAIStudio\Tenancy\StudioTenancy;

StudioTenancy::central(function () {
    // create platform templates / variables
});
```

Queue jobs restore tenant from the persisted run or knowledge base. You do not need extra job middleware for Studio's own jobs.

## Slug override

Slugs (and variable names) are unique **per tenant**, not globally. Lookup prefers the tenant row, then the global:

| Context | `support-bot` rows | `findBySlug('support-bot')` |
|---------|--------------------|-----------------------------|
| Tenant A | A + global | A's agent |
| Tenant B | global only | global agent |
| Tenant A | none | 404 / null |

Tenant A never sees Tenant B.

## Stancl / database-per-tenant

Do **not** add `stancl/tenancy` to Studio's Composer file. On the host:

1. Set `NEURONAI_STUDIO_TENANCY=true` and `NEURONAI_STUDIO_TENANCY_DRIVER=database`.
2. Use the bundled resolver:

```env
NEURONAI_STUDIO_TENANCY_RESOLVER=DigitalElvis\NeuronAIStudio\Tenancy\StanclTenantResolver
```

3. Mount Studio **on tenant domains**, not the central domain (central has no tenant → 403).
4. Copy or publish Studio migrations into Stancl's **tenant** migration path so each tenant database has Studio tables.

With `driver=database`, Studio does **not** filter by `tenant_id` (the database already is the silo). Global catalog across tenant databases is out of scope — install templates inside each tenant.

For Stancl **single-database** tenancy, keep `driver=shared` and resolve `tenant()->getTenantKey()`.

## RAG collections

On `driver=shared`, tenant-owned knowledge bases prefix file names, Pinecone namespaces, and collection/index identifiers with a sanitized `{tenant_id}__`. Global knowledge bases are unprefixed. Qdrant `collection_url` is left as configured (absolute URL).

## Jobs / CLI

```php
StudioTenancy::run('org_123', function () {
    // artisan / listeners that must see tenant data
});
```

`StudioTenancy::central()` is for platform-wide writes (globals only). Creating Studio models while tenancy is on and no tenant is in context throws `TenantRequiredException`.
