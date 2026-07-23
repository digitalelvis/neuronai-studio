<?php

namespace DigitalElvis\NeuronAIStudio\Codegen;

class CodegenGuard
{
    public static function enabled(): bool
    {
        return (bool) config('neuronai-studio.codegen.enabled', false);
    }

    public static function canExport(): bool
    {
        return static::enabled() && (bool) config('neuronai-studio.codegen.export', false);
    }

    public static function canPreview(): bool
    {
        return static::enabled() && (bool) config('neuronai-studio.codegen.preview', false);
    }

    public static function ensureEnabled(): void
    {
        if (! static::enabled()) {
            throw new CodegenDisabledException(
                'CodeGen is disabled. Enable neuronai-studio.codegen.enabled (or set NEURONAI_STUDIO_CODEGEN_ENABLED=true).'
            );
        }
    }

    public static function ensureExport(): void
    {
        if (! static::canExport()) {
            throw new CodegenDisabledException(
                'CodeGen export is disabled. Enable neuronai-studio.codegen.enabled and codegen.export (or set NEURONAI_STUDIO_CODEGEN_ENABLED and NEURONAI_STUDIO_CODEGEN_EXPORT).'
            );
        }
    }

    public static function ensurePreview(): void
    {
        if (! static::canPreview()) {
            throw new CodegenDisabledException(
                'CodeGen preview is disabled. Enable neuronai-studio.codegen.enabled and codegen.preview (or set NEURONAI_STUDIO_CODEGEN_ENABLED and NEURONAI_STUDIO_CODEGEN_PREVIEW).'
            );
        }
    }
}
