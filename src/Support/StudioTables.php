<?php

namespace ElvisLopesDigital\NeuronAIStudio\Support;

class StudioTables
{
    public static function prefix(): string
    {
        return (string) config('neuronai-studio.table_prefix', 'neuronai_studio_');
    }

    public static function name(string $table): string
    {
        return self::prefix().$table;
    }
}
