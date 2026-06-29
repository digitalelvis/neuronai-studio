<?php

namespace ElvisLopesDigital\NeuronAIStudio\Support;

class PlaygroundContext
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public static function augmentInstructions(string $instructions, ?array $context): string
    {
        if ($context === null || $context === []) {
            return $instructions;
        }

        $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $instructions."\n\n---\nRuntime context (use this data when answering):\n```json\n{$json}\n```";
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    public static function normalize(?array $context): array
    {
        if (! is_array($context)) {
            return [];
        }

        if (isset($context['state']) && is_array($context['state'])) {
            return $context['state'];
        }

        return $context;
    }
}
