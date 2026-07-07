# Stream Adapters — External Integration Guide

NeuronAI Studio allows you to expose agents and workflows to external frontend applications (React, Next.js, Vue, mobile apps) using dedicated wire-protocol streaming endpoints.

## Config

External integration is configured in `config/neuronai-studio.php`:

```php
'stream_adapters' => [
    'enabled' => env('NEURONAI_STUDIO_INTEGRATE_ENABLED', true),
    'route_prefix' => env('NEURONAI_STUDIO_INTEGRATE_PREFIX', 'api/neuronai'),
    'middleware' => ['api'], // Override as needed, e.g. ['api', 'auth:sanctum']
    'protocols' => [
        'vercel' => ['enabled' => true],
        'agui' => ['enabled' => true],
    ],
],
```

## Available Endpoints

When `stream_adapters.enabled` is set to `true`, the following integration endpoints are registered:

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `{prefix}/agents/{agent}/stream/{protocol}` | Stream an Agent response |
| `POST` | `{prefix}/workflows/{workflow}/stream/{protocol}` | Stream a Workflow execution |
| `POST` | `{prefix}/workflows/traces/{trace}/resume/{protocol}` | Resume a paused Workflow (Human node) |

Supported `{protocol}` values: `vercel`, `agui`.

## Architecture Isolation

The external integration routes are completely separate from the internal Studio UI and playground. They run under their own prefix and middleware, allowing you to secure external client access (e.g. with `auth:sanctum` or API tokens) without affecting the Studio administration dashboard.

## Next Steps

- [Vercel AI SDK Integration Guide](vercel-ai-sdk.md)
- [AG-UI Protocol Guide](ag-ui.md)
