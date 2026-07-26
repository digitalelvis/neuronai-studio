# Export & production

## What

CodeGen turns studio DB definitions into typed PHP under `NEURONAI_STUDIO_EXPORT_PATH` (default `app/Neuron`). Gated by `codegen.enabled` / `export` / `preview` (defaults tied to `APP_ENV=local`).

## Commands

```bash
php artisan neuronai-studio:export agent {id}
php artisan neuronai-studio:export workflow {id}
```

Tools: Export from tool editor UI (`ToolExporter`).

## Checklist

- [ ] `canExport` true before advising disk writes
- [ ] Namespace/path env correct
- [ ] After export, customize with Neuron AI skills if needed
- [ ] Runtime registries work with CodeGen off in production

## Documentation (canonical)

- [Export & Production](../../../docs/guides/export-and-production.md)
- [Registry & Codegen](../../../docs/guides/tools/registry-and-codegen.md)
- [Artisan Commands](../../../docs/reference/artisan-commands.md)
