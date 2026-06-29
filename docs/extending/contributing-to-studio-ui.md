# Contributing to Studio UI

Guide for developers modifying the React frontend bundles in NeuronAI Studio.

## Prerequisites

- Node.js 18+
- npm
- Local demo app or path repository setup

See [Demo App](../getting-started/demo-app.md).

## Development workflow

```bash
# From package root
npm install
npm run dev    # watch mode
# or
npm run build  # production build

# Publish to demo app public directory
php artisan vendor:publish --tag=neuronai-studio-assets --force
```

Refresh the browser after each build.

## Bundle map

| Directory | Bundle | Livewire host |
|-----------|--------|---------------|
| `resources/js/studio-canvas/` | `workflow-canvas.bundle.js` | `Workflows/Editor` |
| `resources/js/studio-chat/` | `studio-chat.bundle.js` | `Agents/Playground`, workflow test |
| `resources/js/studio-forms/` | `studio-forms.bundle.js` | `Agents/Edit`, `Tools/Edit` |
| `resources/js/studio-traces/` | (separate) | `Workflows/TraceDetail` |

## Livewire bridge

React components call Livewire methods:

```javascript
window.Livewire.find(componentId).call('saveGraph', graphJson);
```

When adding new UI features, ensure corresponding Livewire component methods exist on the PHP side.

## Code style

- Match existing React patterns (functional components, hooks)
- Keep bundles isolated — avoid cross-imports between canvas/chat/forms unless necessary
- CSS lives in bundle-specific files (`canvas.css`, etc.) plus global `studio-ui.css`

## Testing UI changes

1. Build assets
2. Publish to demo app
3. Test in browser across agent form, tool builder, workflow canvas, and playground
4. Run PHP tests: `composer test`

## Pull request checklist

- [ ] `npm run build` succeeds
- [ ] Assets republished if JS/CSS changed
- [ ] Livewire methods documented if added
- [ ] Docs updated if user-facing behavior changed
- [ ] Screenshot tag added to `docs/assets/screenshots/PENDING.md` if UI changed

## See also

- [Frontend Bundles](../reference/frontend-bundles.md)
- [CONTRIBUTING.md](../../CONTRIBUTING.md)
