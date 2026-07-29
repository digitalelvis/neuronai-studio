<?php

namespace DigitalElvis\NeuronAIStudio\Support;

class StudioTranslator
{
    /**
     * Translate a package key, falling back to $fallback (or the key) when missing.
     *
     * @param  array<string, mixed>  $replace
     */
    public static function get(string $key, ?string $fallback = null, array $replace = []): string
    {
        $full = str_starts_with($key, 'neuronai-studio::') ? $key : 'neuronai-studio::'.$key;

        if (trans()->has($full)) {
            return trans($full, $replace);
        }

        return $fallback ?? $key;
    }
}
