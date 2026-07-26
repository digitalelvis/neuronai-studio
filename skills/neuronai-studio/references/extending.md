# Extending Studio

## Custom workflow nodes

Register in `AppServiceProvider::boot()`:

```php
NeuronAIStudio::registerNode('send_email', SendEmailExecutor::class, [
    'label' => 'Send Email',
    'icon' => 'mail',
    'category' => 'integration',
]);
```

Executor implements the node runtime contract; metadata drives the canvas palette. Optional `toolable` / `tool_exposure` for Tool Mode — see docs.

## Custom providers

Extend provider integration via package extension points documented in custom-providers guide (Neuron provider config remains in `config/neuron.php`).

## Studio UI

Contributing to Livewire/React studio UI: follow contributing guide; rebuild assets and republish `neuronai-studio-assets`.

## Documentation (canonical)

- [Custom Node Types](../../../docs/extending/custom-node-types.md)
- [Custom Providers](../../../docs/extending/custom-providers.md)
- [Contributing to Studio UI](../../../docs/extending/contributing-to-studio-ui.md)
- [Frontend Bundles](../../../docs/reference/frontend-bundles.md)
